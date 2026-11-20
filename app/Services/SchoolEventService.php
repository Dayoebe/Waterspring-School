<?php

namespace App\Services;

use App\Models\ParentRecord;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolEventService
{
    public function publish(SchoolEvent $event, User $actor): SchoolEvent
    {
        $this->assertManager($event, $actor);
        if ($event->starts_at->isPast()) {
            throw ValidationException::withMessages(['event' => 'A past event cannot be published.']);
        }
        if ($event->response_deadline && $event->response_deadline->isAfter($event->starts_at)) {
            throw ValidationException::withMessages(['event' => 'The response deadline must be before the event starts.']);
        }

        return DB::transaction(function () use ($event, $actor): SchoolEvent {
            $event->update(['status' => 'published', 'published_at' => now(), 'published_by' => $actor->id]);
            $this->syncParticipants($event);
            app(AuditTrail::class)->record((int) $event->school_id, 'events', 'event.published', 'A school event was published.', $event, ['status' => 'draft'], ['status' => 'published'], actor: $actor);

            return $event->fresh(['classes']);
        });
    }

    public function syncParticipants(SchoolEvent $event): int
    {
        $event->loadMissing('school');
        if ((! $event->requires_rsvp && ! $event->requires_consent && ! $event->requires_emergency_contact) || ! array_intersect($event->audiences ?: ['all'], ['all', 'student', 'parent'])) {
            return 0;
        }
        $classIds = $event->classes()->pluck('my_classes.id');
        $recordIds = collect();
        if ($classIds->isNotEmpty()) {
            foreach ($classIds as $classId) {
                $recordIds = $recordIds->merge(StudentRecord::activeStudentRecordIdsForSchoolAcademicYear((int) $event->school_id, $event->school?->academic_year_id, (int) $classId));
            }
        } else {
            $recordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear((int) $event->school_id, $event->school?->academic_year_id);
        }
        $studentIds = StudentRecord::query()->whereIn('id', $recordIds->unique())->pluck('user_id')->filter()->unique();
        foreach ($studentIds as $studentId) {
            SchoolEventResponse::query()->firstOrCreate(
                ['school_event_id' => $event->id, 'student_id' => $studentId],
                ['participation_status' => 'pending', 'consent_status' => $event->requires_consent ? 'pending' : 'not_required'],
            );
        }

        return $studentIds->count();
    }

    public function respond(SchoolEvent $event, User $student, User $guardian, array $data): SchoolEventResponse
    {
        if ($event->status !== 'published' || $event->starts_at->isPast() || ($event->response_deadline && $event->response_deadline->isPast())) {
            throw ValidationException::withMessages(['response' => 'Responses are closed for this event.']);
        }
        $link = ParentRecord::query()->active()->where('user_id', $guardian->id)->where('student_id', $student->id)->whereNotNull('verified_at')->first();
        if (! $link || (int) $guardian->school_id !== (int) $event->school_id || (int) $student->school_id !== (int) $event->school_id) {
            throw ValidationException::withMessages(['response' => 'You are not authorised to respond for this student.']);
        }
        if ($event->requires_consent && ! $link->can_consent_events) {
            throw ValidationException::withMessages(['consent' => 'Your guardian relationship does not permit event consent.']);
        }

        $participation = (string) ($data['participation_status'] ?? '');
        $consent = $event->requires_consent ? (string) ($data['consent_status'] ?? '') : 'not_required';
        if (! in_array($participation, ['attending', 'not_attending'], true) || ! in_array($consent, ['not_required', 'granted', 'declined'], true)) {
            throw ValidationException::withMessages(['response' => 'Select a valid participation and consent response.']);
        }
        if ($event->requires_consent && $consent === 'declined') {
            $participation = 'not_attending';
        }
        if ($event->requires_emergency_contact && $participation === 'attending' && (blank($data['emergency_contact_name'] ?? null) || blank($data['emergency_contact_phone'] ?? null))) {
            throw ValidationException::withMessages(['emergency_contact' => 'An emergency contact name and phone number are required.']);
        }

        return DB::transaction(function () use ($event, $student, $guardian, $data, $participation, $consent): SchoolEventResponse {
            $lockedEvent = SchoolEvent::query()->lockForUpdate()->findOrFail($event->id);
            $response = SchoolEventResponse::query()->where('school_event_id', $event->id)->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            if ($participation === 'attending' && $lockedEvent->capacity) {
                $confirmed = SchoolEventResponse::query()->where('school_event_id', $event->id)->where('participation_status', 'attending')->where('id', '!=', $response->id)->count();
                if ($confirmed >= $lockedEvent->capacity) {
                    $participation = 'waitlisted';
                }
            }
            $old = ['participation_status' => $response->participation_status, 'consent_status' => $response->consent_status];
            $response->update([
                'guardian_id' => $guardian->id,
                'participation_status' => $participation,
                'consent_status' => $consent,
                'consented_by' => $event->requires_consent ? $guardian->id : null,
                'consented_at' => $event->requires_consent ? now() : null,
                'emergency_contact_name' => filled($data['emergency_contact_name'] ?? null) ? trim($data['emergency_contact_name']) : null,
                'emergency_contact_phone' => filled($data['emergency_contact_phone'] ?? null) ? trim($data['emergency_contact_phone']) : null,
                'emergency_contact_relationship' => filled($data['emergency_contact_relationship'] ?? null) ? trim($data['emergency_contact_relationship']) : null,
                'guardian_note' => filled($data['guardian_note'] ?? null) ? trim($data['guardian_note']) : null,
                'responded_at' => now(),
            ]);
            app(AuditTrail::class)->record((int) $event->school_id, 'events', 'event.response.recorded', 'A guardian recorded an event response.', $response, $old, ['participation_status' => $participation, 'consent_status' => $consent], ['event_id' => $event->id, 'student_id' => $student->id], $guardian);

            return $response->fresh();
        });
    }

    public function cancel(SchoolEvent $event, User $actor, string $reason): void
    {
        $this->assertManager($event, $actor);
        if ($event->status !== 'published') {
            throw ValidationException::withMessages(['cancellationReason' => 'Only a published event can be cancelled.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['cancellationReason' => 'Give a reason for cancelling the event.']);
        }
        $oldStatus = $event->status;
        $event->update(['status' => 'cancelled', 'cancellation_reason' => trim($reason), 'cancelled_at' => now(), 'cancelled_by' => $actor->id]);
        app(AuditTrail::class)->record((int) $event->school_id, 'events', 'event.cancelled', 'A school event was cancelled.', $event, ['status' => $oldStatus], ['status' => 'cancelled', 'reason' => trim($reason)], actor: $actor);
    }

    public function markAttendance(SchoolEventResponse $response, User $actor, string $status): void
    {
        $this->assertManager($response->event, $actor);
        if ($response->event->starts_at->isFuture() || ! in_array($response->event->status, ['published', 'completed'], true)) {
            throw ValidationException::withMessages(['attendance' => 'Attendance can be confirmed only after a published event has started.']);
        }
        if (! in_array($status, ['present', 'absent', 'excused'], true)) {
            throw ValidationException::withMessages(['attendance' => 'Select a valid attendance status.']);
        }
        $response->update(['attendance_status' => $status, 'attendance_confirmed_by' => $actor->id, 'attendance_confirmed_at' => now()]);
    }

    public function complete(SchoolEvent $event, User $actor): void
    {
        $this->assertManager($event, $actor);
        if ($event->status !== 'published' || $event->starts_at->isFuture()) {
            throw ValidationException::withMessages(['event' => 'A published event can be completed only after it has started.']);
        }
        $oldStatus = $event->status;
        $event->update(['status' => 'completed']);
        app(AuditTrail::class)->record((int) $event->school_id, 'events', 'event.completed', 'A school event was marked complete.', $event, ['status' => $oldStatus], ['status' => 'completed'], actor: $actor);
    }

    private function assertManager(SchoolEvent $event, User $actor): void
    {
        abort_unless((int) $event->school_id === (int) $actor->school_id && $actor->can('manage school events'), 403);
    }
}
