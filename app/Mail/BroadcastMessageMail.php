<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\BroadcastMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastMessageMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public BroadcastMessage $broadcastMessage;

    public User $recipient;

    public function __construct(BroadcastMessage $broadcastMessage, User $recipient)
    {
        $this->broadcastMessage = $broadcastMessage;
        $this->recipient = $recipient;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->broadcastMessage->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.broadcast.message',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
