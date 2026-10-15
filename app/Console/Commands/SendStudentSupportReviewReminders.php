<?php

namespace App\Console\Commands;

use App\Models\StudentSupportCase;
use App\Services\StudentSupportNotificationService;
use Illuminate\Console\Command;

class SendStudentSupportReviewReminders extends Command
{
    protected $signature = 'support:send-review-reminders';
    protected $description = 'Email parents and lead teachers when student support plans are due for review';

    public function handle(StudentSupportNotificationService $notifications): int
    {
        $cases = StudentSupportCase::withoutGlobalScopes()->whereNotIn('status', ['completed'])
            ->whereDate('review_on', '<=', today())->with(['student', 'parent', 'leadTeacher'])->get();
        $sent = 0; $failed = 0;
        foreach ($cases as $case) {
            $result = $notifications->notifyBoth($case, 'review_due', today()->toDateString());
            $sent += $result['sent']; $failed += $result['failed'];
        }
        $this->info("Review reminders sent: {$sent}; failed: {$failed}.");

        return self::SUCCESS;
    }
}
