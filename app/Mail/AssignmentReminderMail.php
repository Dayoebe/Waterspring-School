<?php

namespace App\Mail;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assignment $assignment,
        public User $recipient,
        public User $student,
        public string $audience,
        public string $reminderType,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->reminderType === 'overdue_alert' ? 'Overdue assignment' : 'Assignment due soon';

        return new Envelope(subject: $prefix.': '.$this->assignment->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.assignments.reminder');
    }

    public function attachments(): array
    {
        return [];
    }
}
