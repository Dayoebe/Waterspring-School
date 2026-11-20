<?php

namespace App\Console\Commands;

use App\Services\SchoolEventReminderService;
use Illuminate\Console\Command;

class SendSchoolEventReminders extends Command
{
    protected $signature = 'events:send-reminders {--hours=24}';

    protected $description = 'Queue replay-safe reminders for upcoming school events';

    public function handle(SchoolEventReminderService $reminders): int
    {
        $result = $reminders->queueDue((int) $this->option('hours'));
        $this->info("Event reminders queued: {$result['queued']}; duplicates skipped: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
