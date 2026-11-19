<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayWishMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $celebrantName,
        public string $senderName,
        public string $mailSubject,
        public string $personalMessage,
        public string $schoolName,
        public string $logoUrl,
        public ?string $fromName = null,
        public ?string $fromEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = $this->fromEmail && filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)
            ? new Address($this->fromEmail, $this->fromName ?: $this->schoolName)
            : null;

        return new Envelope(from: $from, subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthdays.wish',
            text: 'emails.birthdays.wish-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
