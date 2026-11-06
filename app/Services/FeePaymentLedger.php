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

        return DB::transaction(function () use (
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

            return $transaction;
        }, 3);
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
}
