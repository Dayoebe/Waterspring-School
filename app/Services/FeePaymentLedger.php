<?php

namespace App\Services;

use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentReconciliation;
use App\Models\FeePaymentTransaction;
use App\Models\User;
use Brick\Money\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FeePaymentLedger
{
    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'pos' => 'POS terminal',
        'cheque' => 'Cheque',
        'online' => 'Online gateway',
    ];

    public const RECONCILIATION_STATUSES = [
        'matched' => 'Matched',
        'unmatched' => 'Unmatched',
        'disputed' => 'Disputed',
    ];

    public function recordPayment(
        FeeInvoiceRecord $record,
        int|float|string $amount,
        User $cashier,
        string $paymentMethod,
        ?string $externalReference = null,
        ?string $notes = null,
        ?string $provider = null,
        ?string $providerReference = null
    ): FeePaymentTransaction {
        if (! array_key_exists($paymentMethod, self::PAYMENT_METHODS)) {
            throw new DomainException('Select a supported payment method.');
        }

        $money = Money::of((string) $amount, config('app.currency'));
        $amountMinor = $money->getMinorAmount()->toInt();

        if ($amountMinor <= 0) {
            throw new DomainException('Payment amount must be greater than zero.');
        }

        $transaction = DB::transaction(function () use (
            $record,
            $amountMinor,
            $cashier,
            $paymentMethod,
            $externalReference,
            $notes,
            $provider,
            $providerReference
        ): FeePaymentTransaction {
            $lockedRecord = FeeInvoiceRecord::query()
                ->with('feeInvoice.user')
                ->lockForUpdate()
                ->findOrFail($record->id);
            $schoolId = (int) $lockedRecord->feeInvoice->user->school_id;

            if ((int) $cashier->school_id !== $schoolId) {
                throw new DomainException('The cashier and invoice must belong to the same school.');
            }

            $dueMinor = (int) $lockedRecord->getRawOriginal('amount')
                + (int) $lockedRecord->getRawOriginal('fine')
                - (int) $lockedRecord->getRawOriginal('waiver');
            $paidMinor = $this->netPaidMinor($lockedRecord->id);

            if ($paidMinor + $amountMinor > $dueMinor) {
                throw new DomainException('Payment cannot be higher than the outstanding balance.');
            }

            $transaction = FeePaymentTransaction::query()->create([
                'school_id' => $schoolId,
                'fee_invoice_id' => $lockedRecord->fee_invoice_id,
                'fee_invoice_record_id' => $lockedRecord->id,
                'type' => 'payment',
                'amount' => Money::ofMinor($amountMinor, config('app.currency')),
                'currency' => strtoupper((string) config('app.currency')),
                'receipt_number' => $this->receiptNumber('PAY', $schoolId),
                'payment_method' => $paymentMethod,
                'external_reference' => $this->nullableTrimmed($externalReference),
                'provider' => $this->nullableTrimmed($provider),
                'provider_reference' => $this->nullableTrimmed($providerReference),
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'payer_name' => $lockedRecord->feeInvoice->user->name,
                'notes' => $this->nullableTrimmed($notes),
                'metadata' => ['source' => 'portal'],
                'recorded_ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
                'occurred_at' => now(),
            ]);

            $this->syncPaidProjection($lockedRecord->id);

            app(AuditTrail::class)->record(
                $schoolId,
                'finance',
                'payment.recorded',
                'A payment was added to the immutable fee ledger.',
                $transaction,
                [],
                ['receipt_number' => $transaction->receipt_number, 'amount' => (string) $transaction->amount],
                ['payment_method' => $paymentMethod, 'fee_invoice_id' => $lockedRecord->fee_invoice_id],
                $cashier,
                $transaction->payer_name,
            );

            return $transaction;
        }, 3);

        $this->queueReceiptAfterCommit($transaction);

        return $transaction;
    }

    public function recordGatewayPayment(
        FeeInvoiceRecord $record,
        int $amountMinor,
        string $provider,
        string $providerReference,
        ?string $externalReference = null,
        array $metadata = []
    ): FeePaymentTransaction {
        $provider = trim($provider);
        $providerReference = trim($providerReference);

        if ($amountMinor <= 0 || $provider === '' || $providerReference === '') {
            throw new DomainException('The verified gateway payment is incomplete.');
        }

        $transaction = DB::transaction(function () use (
            $record,
            $amountMinor,
            $provider,
            $providerReference,
            $externalReference,
            $metadata
        ): FeePaymentTransaction {
            $existing = FeePaymentTransaction::query()
                ->where('provider', $provider)
                ->where('provider_reference', $providerReference)
                ->first();

            if ($existing) {
                if ((int) $existing->fee_invoice_record_id !== (int) $record->id
                    || $existing->amount->getMinorAmount()->toInt() !== $amountMinor) {
                    throw new DomainException('The gateway reference is already attached to a different payment.');
                }

                return $existing;
            }

            $lockedRecord = FeeInvoiceRecord::query()
                ->with('feeInvoice.user')
                ->lockForUpdate()
                ->findOrFail($record->id);
            $schoolId = (int) $lockedRecord->feeInvoice->user->school_id;
            $dueMinor = (int) $lockedRecord->getRawOriginal('amount')
                + (int) $lockedRecord->getRawOriginal('fine')
                - (int) $lockedRecord->getRawOriginal('waiver');
            $paidMinor = $this->netPaidMinor($lockedRecord->id);

            if ($paidMinor + $amountMinor > $dueMinor) {
                throw new DomainException('This verified payment is higher than the current outstanding balance and needs review.');
            }

            $transaction = FeePaymentTransaction::query()->create([
                'school_id' => $schoolId,
                'fee_invoice_id' => $lockedRecord->fee_invoice_id,
                'fee_invoice_record_id' => $lockedRecord->id,
                'type' => 'payment',
                'amount' => Money::ofMinor($amountMinor, config('app.currency')),
                'currency' => strtoupper((string) config('app.currency')),
                'receipt_number' => $this->receiptNumber('PAY', $schoolId),
                'payment_method' => 'online',
                'external_reference' => $this->nullableTrimmed($externalReference),
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'cashier_id' => null,
                'cashier_name' => ucfirst($provider).' gateway',
                'payer_name' => $lockedRecord->feeInvoice->user->name,
                'notes' => null,
                'metadata' => ['source' => 'verified_gateway'] + $metadata,
                'recorded_ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
                'occurred_at' => now(),
            ]);

            $this->syncPaidProjection($lockedRecord->id);

            app(AuditTrail::class)->record(
                $schoolId,
                'finance',
                'payment.recorded',
                'A verified online payment was added to the immutable ledger.',
                $transaction,
                [],
                ['receipt_number' => $transaction->receipt_number, 'amount' => (string) $transaction->amount],
                ['provider' => $provider, 'provider_reference' => $providerReference],
                subjectLabel: $transaction->payer_name,
            );

            return $transaction;
        }, 3);

        $this->queueReceiptAfterCommit($transaction);

        return $transaction;
    }

    public function reverse(
        FeePaymentTransaction $payment,
        User $cashier,
        string $reason
    ): FeePaymentTransaction {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new DomainException('A reversal reason of at least five characters is required.');
        }

        return DB::transaction(function () use ($payment, $cashier, $reason): FeePaymentTransaction {
            $lockedPayment = FeePaymentTransaction::query()
                ->with('invoice.user')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->isReversal()) {
                throw new DomainException('A reversal transaction cannot be reversed.');
            }

            if ((int) $cashier->school_id !== (int) $lockedPayment->school_id) {
                throw new DomainException('The cashier and payment must belong to the same school.');
            }

            if (FeePaymentTransaction::query()->where('reverses_transaction_id', $lockedPayment->id)->exists()) {
                throw new DomainException('This payment has already been reversed.');
            }

            FeeInvoiceRecord::query()
                ->whereKey($lockedPayment->fee_invoice_record_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reversal = FeePaymentTransaction::query()->create([
                'school_id' => $lockedPayment->school_id,
                'fee_invoice_id' => $lockedPayment->fee_invoice_id,
                'fee_invoice_record_id' => $lockedPayment->fee_invoice_record_id,
                'type' => 'reversal',
                'amount' => $lockedPayment->amount,
                'currency' => $lockedPayment->currency,
                'receipt_number' => $this->receiptNumber('REV', (int) $lockedPayment->school_id),
                'payment_method' => 'reversal',
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'payer_name' => $lockedPayment->payer_name,
                'reverses_transaction_id' => $lockedPayment->id,
                'notes' => $reason,
                'metadata' => ['original_receipt' => $lockedPayment->receipt_number],
                'recorded_ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
                'occurred_at' => now(),
            ]);

            $this->syncPaidProjection($lockedPayment->fee_invoice_record_id);

            app(AuditTrail::class)->record(
                (int) $lockedPayment->school_id,
                'finance',
                'payment.reversed',
                'A payment was reversed with a retained financial transaction.',
                $reversal,
                ['receipt_number' => $lockedPayment->receipt_number, 'amount' => (string) $lockedPayment->amount],
                ['receipt_number' => $reversal->receipt_number, 'amount' => (string) $reversal->amount],
                ['reason' => $reason, 'fee_invoice_id' => $lockedPayment->fee_invoice_id],
                $cashier,
                $lockedPayment->payer_name,
            );

            return $reversal;
        }, 3);
    }

    public function reconcile(
        FeePaymentTransaction $transaction,
        User $reconciler,
        string $status,
        ?string $statementReference = null,
        ?string $notes = null
    ): FeePaymentReconciliation {
        if (! array_key_exists($status, self::RECONCILIATION_STATUSES)) {
            throw new DomainException('Select a supported reconciliation status.');
        }

        if ((int) $transaction->school_id !== (int) $reconciler->school_id) {
            throw new DomainException('The reconciler and payment must belong to the same school.');
        }

        return FeePaymentReconciliation::query()->create([
            'fee_payment_transaction_id' => $transaction->id,
            'status' => $status,
            'statement_reference' => $this->nullableTrimmed($statementReference),
            'notes' => $this->nullableTrimmed($notes),
            'reconciled_by' => $reconciler->id,
            'reconciler_name' => $reconciler->name,
        ]);
    }

    public function netPaidMinor(int $recordId): int
    {
        $query = FeePaymentTransaction::query()->where('fee_invoice_record_id', $recordId);

        return (int) $query
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'reversal' THEN -amount ELSE amount END), 0) AS net_paid")
            ->value('net_paid');
    }

    private function syncPaidProjection(int $recordId): void
    {
        DB::table('fee_invoice_records')
            ->where('id', $recordId)
            ->update([
                'paid' => $this->netPaidMinor($recordId),
                'updated_at' => now(),
            ]);
    }

    private function receiptNumber(string $prefix, int $schoolId): string
    {
        return sprintf(
            'WSP-%s-%d-%s-%s',
            $prefix,
            $schoolId,
            now()->format('Ymd'),
            strtoupper(substr((string) Str::ulid(), -10))
        );
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function queueReceiptAfterCommit(FeePaymentTransaction $transaction): void
    {
        DB::afterCommit(
            function () use ($transaction): void {
                try {
                    app(FeePaymentNotificationService::class)->queueReceipt($transaction);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        );
    }
}
