<?php

namespace App\Services;

use App\Mail\FeePaymentReceiptMail;
use App\Models\FeePaymentNotificationDelivery;
use App\Models\FeePaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class FeePaymentNotificationService
{
    public function queueReceipt(FeePaymentTransaction $transaction): void
    {
        $transaction->loadMissing('invoice.user.parents');
        $student = $transaction->invoice->user;

        collect([$student])
            ->merge($student->parents)
            ->filter(fn (User $recipient) => (int) $recipient->school_id === (int) $transaction->school_id)
            ->filter(fn (User $recipient) => ! $recipient->email_is_placeholder && filter_var($recipient->email, FILTER_VALIDATE_EMAIL))
            ->unique(fn (User $recipient) => strtolower($recipient->email))
            ->each(fn (User $recipient) => $this->queueForRecipient($transaction, $recipient));
    }

    private function queueForRecipient(FeePaymentTransaction $transaction, User $recipient): void
    {
        $key = implode(':', [
            'payment-receipt',
            $transaction->id,
            hash('sha256', strtolower($recipient->email)),
        ]);

        $delivery = FeePaymentNotificationDelivery::query()->firstOrCreate(
            ['deduplication_key' => $key],
            [
                'fee_payment_transaction_id' => $transaction->id,
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'event' => 'payment_receipt',
                'status' => 'pending',
            ]
        );

        if (! $delivery->wasRecentlyCreated && $delivery->status === 'queued') {
            return;
        }

        try {
            Mail::to($recipient->email)->queue(new FeePaymentReceiptMail($transaction, $recipient));
            $delivery->update(['status' => 'queued', 'queued_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            report($exception);
        }
    }
}
