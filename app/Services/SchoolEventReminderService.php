<?php

namespace App\Services;

use App\Jobs\SendSchoolEventReminder;
use App\Models\ParentRecord;
use App\Models\SchoolEvent;
use App\Models\SchoolEventReminderDelivery;
use App\Models\SchoolEventResponse;

class SchoolEventReminderService
{
    public function queueDue(int $hours = 24): array
    {
        $queued = 0;
        $skipped = 0;
        SchoolEvent::query()->where('status', 'published')->whereBetween('starts_at', [now(), now()->addHours(max(1, $hours))])
            ->with('responses')->each(function (SchoolEvent $event) use (&$queued, &$skipped): void {
                $reminderType = 'upcoming:'.$event->starts_at->format('YmdHi');
                foreach ($event->responses->whereIn('participation_status', ['pending', 'attending', 'waitlisted']) as $response) {
                    foreach ($this->guardians($event, $response) as $guardian) {
                        $delivery = SchoolEventReminderDelivery::query()->firstOrCreate(
                            ['school_event_response_id' => $response->id, 'guardian_id' => $guardian->id, 'reminder_type' => $reminderType],
                            ['school_event_id' => $event->id, 'status' => 'queued', 'queued_at' => now()],
                        );
                        if (! $delivery->wasRecentlyCreated && $delivery->status !== 'failed') {
                            $skipped++;

                            continue;
                        }
                        if ($delivery->status === 'failed') {
                            $delivery->update(['status' => 'queued', 'failed_at' => null, 'failure_code' => null, 'queued_at' => now()]);
                        }
                        SendSchoolEventReminder::dispatch($delivery->id);
                        $queued++;
                    }
                }
            });

        return compact('queued', 'skipped');
    }

    private function guardians(SchoolEvent $event, SchoolEventResponse $response)
    {
        return ParentRecord::query()->active()->where('student_id', $response->student_id)->whereNotNull('verified_at')
            ->when($event->requires_consent, fn ($query) => $query->where('can_consent_events', true))
            ->whereHas('guardian', fn ($query) => $query->where('school_id', $event->school_id)->where('locked', false)->whereNotNull('email'))
            ->with('guardian')->get()->pluck('guardian')->filter()->unique('id');
    }
}
