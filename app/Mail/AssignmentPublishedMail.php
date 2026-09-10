<?php

namespace App\Mail;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assignment $assignment,
        public User $recipient,
        public string $audience,
        public array $studentNames,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New assignment: '.$this->assignment->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.assignments.published',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
