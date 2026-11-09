<?php

namespace App\Console\Commands;

use App\Models\ParentTeacherAppointment;
use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders {--hours=24}';

    protected $description = 'Queue one reminder per participant for upcoming confirmed parent-teacher appointments';

    public function handle(AppointmentNotificationService $notifications): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $appointments = ParentTeacherAppointment::query()->withoutGlobalScope('school')
            ->where('status', 'confirmed')
            ->whereHas('slot', fn ($query) => $query->whereBetween('starts_at', [now(), now()->addHours($hours)]))
            ->with(['slot', 'parent', 'teacher', 'student'])
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            $result = $notifications->notifyParticipants($appointment, 'reminder', $appointment->slot->starts_at->format('YmdHi'));
            $sent += $result['sent'];
        }

        $this->info("Queued {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }
}
