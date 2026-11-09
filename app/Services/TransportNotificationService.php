<?php

namespace App\Services;

use App\Mail\TransportArrivalMail;
use App\Models\TransportEvent;
use App\Models\TransportNotificationDelivery;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransportNotificationService
{
    public function notifyParents(TransportEvent $event): array
    {
        $event->loadMissing('student.parents');
        $queued = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($event->student->parents->where('school_id', $event->school_id)->unique('id') as $parent) {
            $key = "transport:{$event->id}:{$parent->id}";
            $delivery = TransportNotificationDelivery::query()->firstOrCreate(
                ['deduplication_key' => $key],
                ['transport_event_id' => $event->id, 'recipient_id' => $parent->id, 'status' => 'pending']
            );
            if (! $delivery->wasRecentlyCreated) {
                $skipped++;

                continue;
            }
            if (! filter_var($parent->email, FILTER_VALIDATE_EMAIL)) {
                $delivery->update(['status' => 'failed', 'error' => 'Recipient has no valid email address.']);
                $failed++;

                continue;
            }
            try {
                Mail::to($parent->email)->send(new TransportArrivalMail($event, $parent));
                $delivery->update(['status' => 'queued']);
                $queued++;
            } catch (Throwable $exception) {
                $delivery->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
                report($exception);
                $failed++;
            }
        }

        return compact('queued', 'failed', 'skipped');
    }
}
