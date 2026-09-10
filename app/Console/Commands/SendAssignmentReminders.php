<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\AssignmentNotificationService;
use Illuminate\Console\Command;

class SendAssignmentReminders extends Command
{
    protected $signature = 'assignments:send-reminders {--hours=24 : Hours before the deadline to send the reminder}';

    protected $description = 'Send deadline reminders and overdue alerts for unsubmitted assignments';

    public function handle(AssignmentNotificationService $notifications): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $now = now();
        $sent = 0;
        $failed = 0;

        Assignment::query()
            ->where('notifications_enabled', true)
            ->whereNotNull('published_at')
            ->where('due_at', '<=', $now->copy()->addHours($hours))
            ->with(['myClass', 'subject', 'teacher', 'recipients.user.parents', 'submissions:id,assignment_id,student_record_id'])
            ->chunkById(50, function ($assignments) use ($notifications, $now, &$sent, &$failed): void {
                foreach ($assignments as $assignment) {
                    $submittedStudentIds = $assignment->submissions->pluck('student_record_id')->map(fn ($id) => (int) $id);
                    $type = $assignment->due_at->lte($now) ? 'overdue_alert' : 'deadline_reminder';

                    foreach ($assignment->recipients as $studentRecord) {
                        if ($submittedStudentIds->contains((int) $studentRecord->id) || $studentRecord->user?->locked) {
                            continue;
                        }

                        $result = $notifications->sendReminder($assignment, $studentRecord, $type);
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
            });

        $this->info("Assignment reminders complete: {$sent} sent, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
