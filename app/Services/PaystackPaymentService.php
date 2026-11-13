<?php

namespace App\Services;

use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentAttempt;
use App\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaystackPaymentService
{
    public function __construct(
        private readonly PaystackClient $client,
        private readonly FeePaymentLedger $ledger,
        private readonly FeePaymentNotificationService $notifications,
    ) {}

    public function initialize(FeeInvoiceRecord $record, User $payer, int|float|string $amount): FeePaymentAttempt
    {
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
            'provider' => 'paystack',
            'reference' => 'WSP-PS-'.strtoupper((string) Str::ulid()),
            'amount' => $money,
            'currency' => strtoupper((string) config('app.currency')),
            'payer_email' => $email,
            'status' => 'created',
        ]);

        try {
            $data = $this->client->initialize([
                'email' => $email,
                'amount' => (string) $amountMinor,
                'currency' => $attempt->currency,
                'reference' => $attempt->reference,
                'callback_url' => config('services.paystack.callback_url') ?: route('fee-payments.paystack.callback'),
                'metadata' => [
                    'payment_attempt_id' => $attempt->id,
                    'fee_invoice_id' => $attempt->fee_invoice_id,
                    'fee_invoice_record_id' => $attempt->fee_invoice_record_id,
                    'school_id' => $attempt->school_id,
                ],
            ]);

            $authorizationUrl = filter_var($data['authorization_url'] ?? null, FILTER_VALIDATE_URL);
            if (! $authorizationUrl || ! str_starts_with($authorizationUrl, 'https://')) {
                throw new DomainException('Paystack returned an invalid checkout address.');
            }

            $attempt->update([
                'access_code' => $data['access_code'] ?? null,
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

    public function verifyAndFulfill(string $reference): FeePaymentAttempt
    {
        $attempt = FeePaymentAttempt::query()->where('reference', $reference)->firstOrFail();
        if ($attempt->fee_payment_transaction_id) {
            $attempt->load('transaction');
            $this->queueReceiptSafely($attempt);

            return $attempt;
        }

        $data = $this->client->verify($reference);
        $validationError = $this->validationError($attempt, $data);
        if ($validationError !== null) {
            $attempt->update([
                'status' => 'review_required',
                'verified_at' => now(),
                'gateway_response' => $data['gateway_response'] ?? null,
                'failure_reason' => $validationError,
                'provider_payload' => $this->safePayload($data),
            ]);
            throw new DomainException($validationError);
        }

        if (($data['status'] ?? null) !== 'success') {
            $attempt->update([
                'status' => 'not_successful',
                'verified_at' => now(),
                'gateway_response' => $data['gateway_response'] ?? null,
                'failure_reason' => 'Paystack has not confirmed a successful payment.',
                'provider_payload' => $this->safePayload($data),
            ]);

            return $attempt->refresh();
        }

        $fulfilled = DB::transaction(function () use ($attempt, $data): FeePaymentAttempt {
            $lockedAttempt = FeePaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->fee_payment_transaction_id) {
                return $lockedAttempt;
            }

            try {
                $transaction = $this->ledger->recordGatewayPayment(
                    FeeInvoiceRecord::query()->findOrFail($lockedAttempt->fee_invoice_record_id),
                    $lockedAttempt->amount->getMinorAmount()->toInt(),
                    'paystack',
                    $lockedAttempt->reference,
                    isset($data['id']) ? (string) $data['id'] : null,
                    ['payment_attempt_id' => $lockedAttempt->id]
                );
            } catch (DomainException $exception) {
                $lockedAttempt->update([
                    'status' => 'review_required',
                    'verified_at' => now(),
                    'failure_reason' => $exception->getMessage(),
                    'provider_payload' => $this->safePayload($data),
                ]);
                throw $exception;
            }

            $lockedAttempt->update([
                'provider_transaction_id' => isset($data['id']) ? (int) $data['id'] : null,
                'fee_payment_transaction_id' => $transaction->id,
                'status' => 'paid',
                'verified_at' => now(),
                'paid_at' => $this->paidAt($data),
                'gateway_response' => $data['gateway_response'] ?? null,
                'failure_reason' => null,
                'provider_payload' => $this->safePayload($data),
            ]);

            return $lockedAttempt;
        }, 3);

        $fulfilled->load('transaction');
        $this->queueReceiptSafely($fulfilled);

        return $fulfilled;
    }

    private function validationError(FeePaymentAttempt $attempt, array $data): ?string
    {
        if (($data['reference'] ?? null) !== $attempt->reference) {
            return 'Paystack returned a different payment reference. The payment needs review.';
        }
        if ((int) ($data['amount'] ?? -1) !== $attempt->amount->getMinorAmount()->toInt()) {
            return 'The amount confirmed by Paystack does not match the amount requested. The payment needs review.';
        }
        if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($attempt->currency)) {
            return 'The currency confirmed by Paystack does not match the invoice currency. The payment needs review.';
        }

        return null;
    }

    private function safePayload(array $data): array
    {
        return collect($data)->only([
            'id', 'status', 'reference', 'amount', 'currency', 'gateway_response',
            'paid_at', 'paidAt', 'channel', 'fees',
        ])->all();
    }

    private function paidAt(array $data): Carbon
    {
        try {
            return Carbon::parse($data['paid_at'] ?? $data['paidAt'] ?? now());
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
