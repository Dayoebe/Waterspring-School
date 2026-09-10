<?php

namespace App\Services;

use App\Mail\StudentAttendanceAlertMail;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Mail;

class AttendanceNotificationService
{
    public function notifyParents(AttendanceRecord $record): array
    {
        if (! in_array($record->status, ['absent', 'late', 'excused'], true)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $record->loadMissing([
            'attendanceSession.myClass',
            'attendanceSession.section',
            'studentRecord.user.parents',
        ]);

        $student = $record->studentRecord?->user;
        if (! $student) {
            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach ($student->parents->unique('id') as $parent) {
            if ($parent->locked || blank($parent->email)) {
                continue;
            }

            try {
                Mail::to($parent->email)->send(new StudentAttendanceAlertMail($record, $parent));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }
}
