<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReply extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public ContactMessage $contactMessage;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Response: '.$this->contactMessage->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-message.reply-to-sender',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
