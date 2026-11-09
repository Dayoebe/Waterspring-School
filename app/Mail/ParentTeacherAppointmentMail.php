<?php

namespace App\Mail;

use App\Mail\Concerns\RetriesQueuedMail;
use App\Models\ParentTeacherAppointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParentTeacherAppointmentMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, RetriesQueuedMail, SerializesModels;

    public function __construct(
        public ParentTeacherAppointment $appointment,
        public User $recipient,
        public string $notificationType,
    ) {
        $this->appointment->loadMissing(['slot', 'parent', 'teacher', 'student']);
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'requested' => 'Parent-teacher meeting request',
            'confirmed' => 'Parent-teacher meeting confirmed',
            'declined' => 'Parent-teacher meeting update',
            'cancelled' => 'Parent-teacher meeting cancelled',
            'completed' => 'Parent-teacher meeting follow-up',
            default => 'Parent-teacher meeting reminder',
        };

        return new Envelope(subject: $subject.' - '.$this->appointment->student->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.appointments.notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
