<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\TransportEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransportArrivalMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public function __construct(public TransportEvent $event, public User $recipient)
    {
        $this->event->loadMissing(['student', 'route', 'stop']);
    }

    public function envelope(): Envelope
    {
        $action = $this->event->event_type === 'dropped_off' ? 'drop-off recorded' : 'arrival recorded';

        return new Envelope(subject: "Transport {$action} - {$this->event->student->name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.transport.arrival');
    }

    public function attachments(): array
    {
        return [];
    }
}
