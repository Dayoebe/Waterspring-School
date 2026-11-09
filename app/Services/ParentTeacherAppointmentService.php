<?php

namespace App\Services;

use App\Models\AppointmentAvailabilitySlot;
use App\Models\ParentTeacherAppointment;
use App\Models\ParentTeacherAppointmentNote;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class ParentTeacherAppointmentService
{
    public function createSlot(User $actor, User $teacher, array $data): AppointmentAvailabilitySlot
    {
        if ((int) $actor->school_id !== (int) $teacher->school_id) {
            throw new DomainException('The teacher must belong to your school.');
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        if ($startsAt->isPast() || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new DomainException('Availability must have a valid future start and end time.');
        }

        return DB::transaction(function () use ($actor, $teacher, $data, $startsAt, $endsAt): AppointmentAvailabilitySlot {
            User::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $overlaps = AppointmentAvailabilitySlot::query()
                ->where('teacher_id', $teacher->id)
                ->where('status', 'available')
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();
            if ($overlaps) {
                throw new DomainException('This availability overlaps another active slot.');
            }

            return AppointmentAvailabilitySlot::query()->create([
                'school_id' => $actor->school_id,
                'teacher_id' => $teacher->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'mode' => $data['mode'],
                'location' => $this->nullable($data['location'] ?? null),
                'notes' => $this->nullable($data['notes'] ?? null),
                'status' => 'available',
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    public function request(User $parent, int $studentId, AppointmentAvailabilitySlot $slot, string $reason): ParentTeacherAppointment
    {
        if ((int) $parent->school_id !== (int) $slot->school_id) {
            throw new DomainException('The appointment slot belongs to another school.');
        }
        if (! app(AppointmentAccess::class)->canBook($parent, $studentId, (int) $slot->teacher_id)) {
            throw new DomainException('This teacher is not assigned to the selected child.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new DomainException('Please provide a brief meeting reason.');
        }

        return DB::transaction(function () use ($parent, $studentId, $slot, $reason): ParentTeacherAppointment {
            $locked = AppointmentAvailabilitySlot::query()->lockForUpdate()->findOrFail($slot->id);
            if ($locked->status !== 'available' || $locked->starts_at->isPast()) {
                throw new DomainException('This appointment slot is no longer available.');
            }
            if ($locked->appointments()->whereIn('status', ['requested', 'confirmed'])->exists()) {
                throw new DomainException('This appointment slot has already been requested.');
            }

            return ParentTeacherAppointment::query()->create([
                'school_id' => $parent->school_id,
                'appointment_availability_slot_id' => $locked->id,
                'parent_id' => $parent->id,
                'teacher_id' => $locked->teacher_id,
                'student_id' => $studentId,
                'reason' => trim($reason),
                'status' => 'requested',
                'requested_at' => now(),
            ]);
        }, 3);
    }

    public function respond(ParentTeacherAppointment $appointment, User $actor, string $status): void
    {
        $this->assertSameSchool($appointment, $actor);
        if (! in_array($status, ['confirmed', 'declined'], true)) {
            throw new DomainException('Select a supported appointment response.');
        }
        if ($appointment->status !== 'requested') {
            throw new DomainException('This appointment request has already been handled.');
        }
        $access = app(AppointmentAccess::class);
        if ((int) $appointment->teacher_id !== (int) $actor->id && ! $access->isLeadership($actor)) {
            throw new DomainException('Only the assigned teacher or school leadership can respond.');
        }

        $appointment->update([
            'status' => $status,
            'responded_at' => now(),
            'responded_by' => $actor->id,
        ]);
    }

    public function cancel(ParentTeacherAppointment $appointment, User $actor): void
    {
        $this->assertSameSchool($appointment, $actor);
        if ((int) $appointment->parent_id !== (int) $actor->id) {
            throw new DomainException('Only the requesting parent can cancel this appointment.');
        }
        if (! in_array($appointment->status, ['requested', 'confirmed'], true)) {
            throw new DomainException('This appointment can no longer be cancelled.');
        }
        $appointment->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function complete(ParentTeacherAppointment $appointment, User $actor): void
    {
        $this->assertSameSchool($appointment, $actor);
        if ($appointment->status !== 'confirmed') {
            throw new DomainException('Only confirmed appointments can be completed.');
        }
        if ($appointment->slot()->where('starts_at', '<=', now())->doesntExist()) {
            throw new DomainException('The appointment cannot be completed before its scheduled time.');
        }
        if ((int) $appointment->teacher_id !== (int) $actor->id && ! app(AppointmentAccess::class)->isLeadership($actor)) {
            throw new DomainException('Only the assigned teacher or school leadership can complete this appointment.');
        }
        $appointment->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function addNote(ParentTeacherAppointment $appointment, User $actor, string $type, string $note): ParentTeacherAppointmentNote
    {
        $this->assertSameSchool($appointment, $actor);
        if (! in_array($type, ['meeting_note', 'follow_up'], true)) {
            throw new DomainException('Select a supported note type.');
        }
        if ((int) $appointment->teacher_id !== (int) $actor->id && ! app(AppointmentAccess::class)->isLeadership($actor)) {
            throw new DomainException('Only the assigned teacher or school leadership can add meeting notes.');
        }
        if (mb_strlen(trim($note)) < 3) {
            throw new DomainException('Appointment notes must contain at least three characters.');
        }

        return ParentTeacherAppointmentNote::query()->create([
            'parent_teacher_appointment_id' => $appointment->id,
            'school_id' => $appointment->school_id,
            'author_id' => $actor->id,
            'author_name' => $actor->name,
            'type' => $type,
            'note' => trim($note),
        ]);
    }

    public function cancelSlot(AppointmentAvailabilitySlot $slot, User $actor): void
    {
        if ((int) $slot->school_id !== (int) $actor->school_id) {
            throw new DomainException('The appointment slot belongs to another school.');
        }
        if ((int) $slot->teacher_id !== (int) $actor->id && ! app(AppointmentAccess::class)->isLeadership($actor)) {
            throw new DomainException('Only the teacher or school leadership can cancel this slot.');
        }
        if ($slot->appointments()->whereIn('status', ['requested', 'confirmed'])->exists()) {
            throw new DomainException('Handle the active appointment before cancelling this slot.');
        }
        $slot->update(['status' => 'cancelled']);
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertSameSchool(ParentTeacherAppointment $appointment, User $actor): void
    {
        if ((int) $appointment->school_id !== (int) $actor->school_id) {
            throw new DomainException('The appointment belongs to another school.');
        }
    }
}
