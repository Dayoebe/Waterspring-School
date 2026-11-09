<?php

namespace App\Services;

use App\Mail\ParentTeacherAppointmentMail;
use App\Models\AppointmentNotificationDelivery;
use App\Models\ParentTeacherAppointment;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentNotificationService
{
    public function notifyParticipants(ParentTeacherAppointment $appointment, string $event, string $eventKey): array
    {
        $appointment->loadMissing(['parent', 'teacher']);
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach (collect([$appointment->parent, $appointment->teacher])->filter()->unique('id') as $recipient) {
            $key = implode(':', ['appointment', $appointment->id, $event, $eventKey, $recipient->id]);
            $delivery = AppointmentNotificationDelivery::query()->firstOrCreate(
                ['deduplication_key' => $key],
                [
                    'parent_teacher_appointment_id' => $appointment->id,
                    'recipient_id' => $recipient->id,
                    'event' => $event,
                    'status' => 'pending',
                ]
            );
            if (! $delivery->wasRecentlyCreated) {
                $skipped++;

                continue;
            }
            if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                $delivery->update(['status' => 'failed', 'error' => 'Recipient has no valid email address.']);
                $failed++;

                continue;
            }

            try {
                Mail::to($recipient->email)->send(new ParentTeacherAppointmentMail($appointment, $recipient, $event));
                $delivery->update(['status' => 'queued']);
                $sent++;
            } catch (Throwable $exception) {
                $delivery->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
                report($exception);
                $failed++;
            }
        }

        return compact('sent', 'failed', 'skipped');
    }
}
