<?php

namespace App\Mail;

use App\Models\StudentSupportCase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentSupportCaseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StudentSupportCase $supportCase, public User $recipient, public string $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->event) {
            'opened' => 'Student support plan opened: '.$this->supportCase->student->name,
            'review_due' => 'Student support review due: '.$this->supportCase->student->name,
            'completed' => 'Student support plan completed: '.$this->supportCase->student->name,
            default => 'Student support plan updated: '.$this->supportCase->student->name,
        });
    }

    public function content(): Content { return new Content(markdown: 'emails.student-support.case-event'); }
    public function attachments(): array { return []; }
}
