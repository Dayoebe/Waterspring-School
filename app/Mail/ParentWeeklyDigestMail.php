<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParentWeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your weekly family digest · '.$this->digest['school']['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.parent-digest.weekly', text: 'emails.parent-digest.weekly-text');
    }

    public function attachments(): array
    {
        return [];
    }
}
