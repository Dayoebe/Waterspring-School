<?php

namespace App\Mail;

use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolEventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SchoolEvent $event, public User $student, public User $guardian) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder: '.$this->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.events.reminder', text: 'emails.events.reminder-text');
    }

    public function attachments(): array
    {
        return [];
    }
}
