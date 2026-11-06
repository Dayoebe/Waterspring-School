<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    use RetriesQueuedMail;
    use SerializesModels;

    /**
     * New status.
     */
    public string $status;

    /**
     * Message/ reason.
     */
    public ?string $reason;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($status, $reason = null)
    {
        $this->status = $status;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Application Status Changed',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content()
    {
        return new Content(
            markdown: 'emails.account-application.status-changed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
