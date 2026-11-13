<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\Fee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeeApprovedParentMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public function __construct(public Fee $fee, public User $recipient)
    {
        $this->fee->loadMissing('feeCategory.school');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New school fee announcement: '.$this->fee->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.fees.approved-parent-notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
