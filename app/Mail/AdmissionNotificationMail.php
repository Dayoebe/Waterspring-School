<?php

namespace App\Mail;

use App\Models\AdmissionRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionRegistration $admission,
        public string $notificationType,
        public ?string $temporaryPassword = null,
    ) {
        $this->admission->loadMissing(['school', 'myClass', 'section', 'enrolledUser', 'enrolledStudentRecord']);
    }

    public function envelope(): Envelope
    {
        $schoolName = $this->admission->school?->name ?: config('app.name');
        $subject = match ($this->notificationType) {
            'approved' => 'Admission Approved - '.$schoolName,
            'rejected' => 'Admission Application Update - '.$schoolName,
            default => 'Admission Application Received - '.$schoolName,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admissions.notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
