<?php

namespace App\Mail;

use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentGradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AssignmentSubmission $submission,
        public User $recipient,
        public User $student,
        public string $audience,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Assignment graded: '.$this->submission->assignment->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.assignments.graded');
    }

    public function attachments(): array
    {
        return [];
    }
}
