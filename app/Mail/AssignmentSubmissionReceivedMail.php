<?php

namespace App\Mail;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentSubmissionReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AssignmentSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Assignment submitted: '.$this->submission->assignment->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.assignments.submitted');
    }

    public function attachments(): array
    {
        return [];
    }
}
