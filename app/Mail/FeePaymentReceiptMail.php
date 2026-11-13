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

class FeePaymentReceiptMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public function __construct(
        public FeePaymentTransaction $transaction,
        public User $recipient,
    ) {
        $this->transaction->loadMissing(['invoice', 'invoiceRecord.fee', 'school']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'School fee payment receipt '.$this->transaction->receipt_number);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.fees.payment-receipt');
    }

    public function attachments(): array
    {
        return [];
    }
}
