<?php

namespace App\Services;

use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentAttempt;
use App\Models\User;
use App\Support\FlutterwaveConfiguration;
use Brick\Money\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FlutterwavePaymentService
{
    public function __construct(
        private readonly FlutterwaveClient $client,
        private readonly FeePaymentLedger $ledger,
        private readonly FeePaymentNotificationService $notifications,
        private readonly FlutterwaveConfiguration $configuration,
    ) {}

    public function initialize(FeeInvoiceRecord $record, User $payer, int|float|string $amount): FeePaymentAttempt
    {
        $this->configuration->assertCheckoutReady();
        $record->loadMissing('feeInvoice.user');
        $student = $record->feeInvoice->user;

        if ((int) $payer->school_id !== (int) $student->school_id) {
            throw new DomainException('The payer and invoice must belong to the same school.');
        }

        $email = $this->paymentEmail($payer, $student);
        $money = Money::of((string) $amount, config('app.currency'));
        $amountMinor = $money->getMinorAmount()->toInt();
        $outstandingMinor = (int) $record->getRawOriginal('amount')
            + (int) $record->getRawOriginal('fine')
            - (int) $record->getRawOriginal('waiver')
            - (int) $record->getRawOriginal('paid');

        if ($amountMinor <= 0 || $amountMinor > $outstandingMinor) {
            throw new DomainException('Payment must be greater than zero and no higher than the outstanding balance.');
        }

        $attempt = FeePaymentAttempt::query()->create([
            'school_id' => $student->school_id,
            'fee_invoice_id' => $record->fee_invoice_id,
            'fee_invoice_record_id' => $record->id,
            'initiated_by' => $payer->id,
            'provider' => 'flutterwave',
            'reference' => 'WSP-FLW-'.strtoupper((string) Str::ulid()),
            'amount' => $money,
            'currency' => strtoupper((string) config('app.currency')),
            'payer_email' => $email,
            'status' => 'created',
        ]);

        try {
            $data = $this->client->createCheckout([
                'tx_ref' => $attempt->reference,
                'amount' => (string) $attempt->amount->getAmount(),
                'currency' => $attempt->currency,
                'redirect_url' => $this->configuration->callbackUrl(),
                'customer' => [
                    'email' => $email,
                    'name' => $payer->name,
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => 'School fee payment',
                ],
                'meta' => [
                    'payment_attempt_id' => $attempt->id,
                    'fee_invoice_id' => $attempt->fee_invoice_id,
                    'fee_invoice_record_id' => $attempt->fee_invoice_record_id,
                    'school_id' => $attempt->school_id,
                ],
            ]);

            $authorizationUrl = filter_var($data['link'] ?? null, FILTER_VALIDATE_URL);
            if (! $authorizationUrl || ! str_starts_with($authorizationUrl, 'https://')) {
                throw new DomainException('Flutterwave returned an invalid checkout address.');
            }

            $attempt->update([
                'authorization_url' => $authorizationUrl,
                'status' => 'initialized',
                'initialized_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'initialization_failed',
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            throw $exception;
        }

        return $attempt->refresh();
    }

    public function verifyAndFulfill(string $reference, int $transactionId): FeePaymentAttempt
    {
        $attempt = $this->findAttempt($reference);
        if ($attempt->fee_payment_transaction_id) {
            $attempt->load('transaction');
            $this->queueReceiptSafely($attempt);

            return $attempt;
        }

        $data = $this->client->verify($transactionId);
        $validationError = $this->validationError($attempt, $transactionId, $data);
        if ($validationError !== null) {
            $updates = [
                'status' => 'review_required',
                'verified_at' => now(),
                'gateway_response' => $data['processor_response'] ?? null,
                'failure_reason' => $validationError,
                'provider_payload' => $this->safePayload($data),
            ];
            if (($data['tx_ref'] ?? null) === $attempt->reference) {
                $updates['provider_transaction_id'] = $transactionId;
            }
            $attempt->update($updates);
            throw new DomainException($validationError);
        }

        if (($data['status'] ?? null) !== 'successful') {
            $attempt->update([
                'status' => 'not_successful',
                'provider_transaction_id' => $transactionId,
                'verified_at' => now(),
                'gateway_response' => $data['processor_response'] ?? null,
                'failure_reason' => 'Flutterwave has not confirmed a successful payment.',
                'provider_payload' => $this->safePayload($data),
            ]);

            return $attempt->refresh();
        }

        $fulfilled = DB::transaction(function () use ($attempt, $data, $transactionId): FeePaymentAttempt {
            $lockedAttempt = FeePaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->fee_payment_transaction_id) {
                return $lockedAttempt;
            }

            try {
                $transaction = $this->ledger->recordGatewayPayment(
                    FeeInvoiceRecord::query()->findOrFail($lockedAttempt->fee_invoice_record_id),
                    $lockedAttempt->amount->getMinorAmount()->toInt(),
                    'flutterwave',
                    (string) $data['flw_ref'],
                    (string) $transactionId,
                    [
                        'payment_attempt_id' => $lockedAttempt->id,
                        'payment_type' => $data['payment_type'] ?? null,
                    ]
                );
            } catch (DomainException $exception) {
                $lockedAttempt->update([
                    'status' => 'review_required',
                    'provider_transaction_id' => $transactionId,
                    'verified_at' => now(),
                    'failure_reason' => $exception->getMessage(),
                    'provider_payload' => $this->safePayload($data),
                ]);
                throw $exception;
            }

            $lockedAttempt->update([
                'provider_transaction_id' => $transactionId,
                'fee_payment_transaction_id' => $transaction->id,
                'status' => 'paid',
                'verified_at' => now(),
                'paid_at' => $this->paidAt($data),
                'gateway_response' => $data['processor_response'] ?? null,
                'failure_reason' => null,
                'provider_payload' => $this->safePayload($data),
            ]);

            return $lockedAttempt;
        }, 3);

        $fulfilled->load('transaction');
        $this->queueReceiptSafely($fulfilled);

        return $fulfilled;
    }

    public function findAttempt(string $reference): FeePaymentAttempt
    {
        return FeePaymentAttempt::query()
            ->where('provider', 'flutterwave')
            ->where('reference', $reference)
            ->firstOrFail();
    }

    private function validationError(FeePaymentAttempt $attempt, int $transactionId, array $data): ?string
    {
        if ((int) ($data['id'] ?? 0) !== $transactionId) {
            return 'Flutterwave returned a different transaction ID. The payment needs review.';
        }
        if (($data['tx_ref'] ?? null) !== $attempt->reference) {
            return 'Flutterwave returned a different payment reference. The payment needs review.';
        }
        if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($attempt->currency)) {
            return 'The currency confirmed by Flutterwave does not match the invoice currency. The payment needs review.';
        }
        if (! is_string($data['flw_ref'] ?? null) || trim($data['flw_ref']) === '') {
            return 'Flutterwave did not return a valid provider reference. The payment needs review.';
        }

        try {
            $verifiedAmount = Money::of((string) ($data['amount'] ?? ''), $attempt->currency);
        } catch (Throwable) {
            return 'Flutterwave returned an invalid payment amount. The payment needs review.';
        }

        if (! $verifiedAmount->isEqualTo($attempt->amount)) {
            return 'The amount confirmed by Flutterwave does not match the amount requested. The payment needs review.';
        }

        return null;
    }

    private function safePayload(array $data): array
    {
        return collect($data)->only([
            'id', 'tx_ref', 'flw_ref', 'status', 'amount', 'currency',
            'charged_amount', 'processor_response', 'payment_type', 'created_at',
        ])->all();
    }

    private function paidAt(array $data): Carbon
    {
        try {
            return Carbon::parse($data['created_at'] ?? now());
        } catch (Throwable) {
            return now();
        }
    }

    private function paymentEmail(User $payer, User $student): string
    {
        foreach ([$payer, $student] as $candidate) {
            if (! $candidate->email_is_placeholder && filter_var($candidate->email, FILTER_VALIDATE_EMAIL)) {
                return $candidate->email;
            }
        }

        throw new DomainException('Add a valid non-placeholder email address before starting an online payment.');
    }

    private function queueReceiptSafely(FeePaymentAttempt $attempt): void
    {
        try {
            $this->notifications->queueReceipt($attempt->transaction);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
