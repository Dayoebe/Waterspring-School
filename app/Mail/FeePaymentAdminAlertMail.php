<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\FeePaymentTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeePaymentAdminAlertMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public function __construct(
        public FeePaymentTransaction $transaction,
        public User $recipient,
    ) {
        $this->transaction->loadMissing(['invoice.user', 'invoiceRecord.fee', 'school']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fee payment received: '.$this->transaction->payer_name.' · '.$this->transaction->receipt_number
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.fees.payment-admin-alert');
    }

    public function attachments(): array
    {
        return [];
    }
}
