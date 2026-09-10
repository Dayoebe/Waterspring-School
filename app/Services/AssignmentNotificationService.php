<?php

namespace App\Services;

use App\Mail\AssignmentGradedMail;
use App\Mail\AssignmentReminderMail;
use App\Mail\AssignmentSubmissionReceivedMail;
use App\Models\Assignment;
use App\Models\AssignmentNotificationDelivery;
use App\Models\AssignmentSubmission;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class AssignmentNotificationService
{
    public function sendReminder(Assignment $assignment, StudentRecord $studentRecord, string $type): array
    {
        if (! $assignment->notifications_enabled || ! in_array($type, ['deadline_reminder', 'overdue_alert'], true)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $studentRecord->loadMissing('user.parents');
        $student = $studentRecord->user;
        if (! $student) {
            return ['sent' => 0, 'failed' => 0];
        }

        $deliveries = collect();
        if (filled($student->email)) {
            $deliveries->push([$student, 'student']);
        }
        foreach ($student->parents as $parent) {
            if (! $parent->locked && filled($parent->email)) {
                $deliveries->push([$parent, 'parent']);
            }
        }

        return $this->sendMany($deliveries, function (User $recipient, string $audience) use ($assignment, $student, $studentRecord, $type): array {
            $key = "assignment:{$assignment->id}:student:{$studentRecord->id}:{$type}:recipient:{$recipient->id}";

            return [new AssignmentReminderMail($assignment, $recipient, $student, $audience, $type), $type, $key];
        }, $assignment);
    }

    public function sendSubmissionReceived(AssignmentSubmission $submission): array
    {
        $submission->loadMissing(['assignment.teacher', 'studentRecord.user']);
        $assignment = $submission->assignment;
        $teacher = $assignment?->teacher;
        if (! $assignment?->notifications_enabled || ! $teacher || blank($teacher->email)) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->sendOne(
            $assignment,
            $teacher,
            new AssignmentSubmissionReceivedMail($submission),
            'submission_received',
            "assignment:{$assignment->id}:submission:{$submission->id}:submitted:recipient:{$teacher->id}",
        );
    }

    public function sendGradePublished(AssignmentSubmission $submission): array
    {
        $submission->loadMissing(['assignment', 'studentRecord.user.parents', 'grader']);
        $assignment = $submission->assignment;
        $student = $submission->studentRecord?->user;
        if (! $assignment?->notifications_enabled || ! $student) {
            return ['sent' => 0, 'failed' => 0];
        }

        $deliveries = collect();
        if (filled($student->email)) {
            $deliveries->push([$student, 'student']);
        }
        foreach ($student->parents as $parent) {
            if (! $parent->locked && filled($parent->email)) {
                $deliveries->push([$parent, 'parent']);
            }
        }

        return $this->sendMany($deliveries, function (User $recipient, string $audience) use ($assignment, $student, $submission): array {
            $key = "assignment:{$assignment->id}:submission:{$submission->id}:graded:recipient:{$recipient->id}";

            return [new AssignmentGradedMail($submission, $recipient, $student, $audience), 'grade_published', $key];
        }, $assignment);
    }

    protected function sendMany($deliveries, callable $factory, Assignment $assignment): array
    {
        $sent = 0;
        $failed = 0;
        foreach ($deliveries->unique(fn ($delivery) => $delivery[0]->id) as [$recipient, $audience]) {
            [$mail, $event, $key] = $factory($recipient, $audience);
            $result = $this->sendOne($assignment, $recipient, $mail, $event, $key);
            $sent += $result['sent'];
            $failed += $result['failed'];
        }

        return compact('sent', 'failed');
    }

    protected function sendOne(Assignment $assignment, User $recipient, Mailable $mail, string $event, string $key): array
    {
        $delivery = AssignmentNotificationDelivery::query()->firstOrCreate(
            ['deduplication_key' => $key],
            [
                'assignment_id' => $assignment->id,
                'recipient_id' => $recipient->id,
                'event' => $event,
                'sent_at' => now(),
            ],
        );

        if (! $delivery->wasRecentlyCreated) {
            return ['sent' => 0, 'failed' => 0];
        }

        try {
            Mail::to($recipient->email)->send($mail);

            return ['sent' => 1, 'failed' => 0];
        } catch (\Throwable $exception) {
            $delivery->delete();
            report($exception);

            return ['sent' => 0, 'failed' => 1];
        }
    }
}
