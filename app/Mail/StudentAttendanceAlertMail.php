<?php

namespace App\Mail;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentAttendanceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AttendanceRecord $attendanceRecord,
        public User $parent,
    ) {}

    public function build(): self
    {
        $studentName = $this->attendanceRecord->studentRecord?->user?->name ?? 'Your child';
        $status = ucfirst($this->attendanceRecord->status);

        return $this->subject("Attendance update: {$studentName} marked {$status}")
            ->view('emails.attendance.parent-alert');
    }
}
