<?php

namespace App\Services;

use App\Models\AuthorizedPickupGuardian;
use App\Models\StudentTransportAssignment;
use App\Models\TransportEvent;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SchoolTransportService
{
    public function createRoute(User $actor, array $data): TransportRoute
    {
        return TransportRoute::query()->create([
            'school_id' => $actor->school_id,
            'name' => trim($data['name']),
            'code' => strtoupper(trim($data['code'])),
            'vehicle_label' => $this->nullable($data['vehicle_label'] ?? null),
            'driver_name' => $this->nullable($data['driver_name'] ?? null),
            'driver_phone' => $this->nullable($data['driver_phone'] ?? null),
            'is_active' => true,
        ]);
    }

    public function addStop(TransportRoute $route, User $actor, array $data): TransportStop
    {
        $this->assertRouteSchool($route, $actor);

        return $route->stops()->create([
            'school_id' => $route->school_id,
            'name' => trim($data['name']),
            'sequence' => $data['sequence'],
            'morning_time' => $data['morning_time'] ?: null,
            'afternoon_time' => $data['afternoon_time'] ?: null,
            'landmark' => $this->nullable($data['landmark'] ?? null),
            'is_active' => true,
        ]);
    }

    public function assignStudent(User $student, TransportRoute $route, TransportStop $stop, User $actor, string $direction): StudentTransportAssignment
    {
        if ((int) $student->school_id !== (int) $actor->school_id || ! $student->hasRole('student')) {
            throw new DomainException('Select a student from your school.');
        }
        $this->assertRouteSchool($route, $actor);
        if ((int) $stop->school_id !== (int) $actor->school_id || (int) $stop->transport_route_id !== (int) $route->id) {
            throw new DomainException('The selected stop does not belong to this route.');
        }
        if (! in_array($direction, ['morning', 'afternoon', 'both'], true)) {
            throw new DomainException('Select a supported transport direction.');
        }

        return DB::transaction(function () use ($student, $route, $stop, $actor, $direction): StudentTransportAssignment {
            User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            if (StudentTransportAssignment::query()->where('student_id', $student->id)->where('status', 'active')->exists()) {
                throw new DomainException('This student already has an active transport assignment.');
            }

            return StudentTransportAssignment::query()->create([
                'school_id' => $actor->school_id,
                'student_id' => $student->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $stop->id,
                'direction' => $direction,
                'status' => 'active',
                'starts_on' => now()->toDateString(),
                'assigned_by' => $actor->id,
            ]);
        }, 3);
    }

    public function deactivateAssignment(StudentTransportAssignment $assignment, User $actor): void
    {
        $this->assertSameSchool($assignment->school_id, $actor);
        $assignment->update(['status' => 'inactive', 'ends_on' => now()->toDateString()]);
    }

    public function authorizeGuardian(User $student, User $actor, array $data): array
    {
        if ((int) $student->school_id !== (int) $actor->school_id || ! $student->hasRole('student')) {
            throw new DomainException('Select a student from your school.');
        }

        $pin = (string) random_int(100000, 999999);
        $guardian = AuthorizedPickupGuardian::query()->create([
            'school_id' => $actor->school_id,
            'student_id' => $student->id,
            'user_id' => $data['user_id'] ?? null,
            'name' => trim($data['name']),
            'relationship' => trim($data['relationship']),
            'phone' => trim($data['phone']),
            'pickup_code_hash' => Hash::make($pin),
            'is_active' => true,
            'authorized_by' => $actor->id,
        ]);

        return ['guardian' => $guardian, 'pickup_code' => $pin];
    }

    public function revokeGuardian(AuthorizedPickupGuardian $guardian, User $actor): void
    {
        $this->assertSameSchool($guardian->school_id, $actor);
        $guardian->update(['is_active' => false]);
    }

    public function verifyPickup(AuthorizedPickupGuardian $guardian, User $actor, string $pickupCode, ?string $notes = null): TransportEvent
    {
        $this->assertSameSchool($guardian->school_id, $actor);

        $event = DB::transaction(function () use ($guardian, $actor, $pickupCode, $notes): ?TransportEvent {
            $lockedGuardian = AuthorizedPickupGuardian::query()->lockForUpdate()->findOrFail($guardian->id);
            if (! $lockedGuardian->is_active) {
                return null;
            }
            if ($lockedGuardian->verification_locked_until?->isFuture()) {
                throw new DomainException('Pickup verification is temporarily locked after repeated failed attempts.');
            }
            if (! Hash::check($pickupCode, $lockedGuardian->pickup_code_hash)) {
                $attempts = $lockedGuardian->failed_verification_attempts + 1;
                $lockedGuardian->update([
                    'failed_verification_attempts' => $attempts,
                    'verification_locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
                ]);

                return null;
            }

            $assignment = StudentTransportAssignment::query()
                ->where('student_id', $lockedGuardian->student_id)
                ->where('status', 'active')
                ->first();
            $event = $this->createEvent(
                $lockedGuardian->student_id,
                $actor,
                'pickup_verified',
                $assignment,
                $lockedGuardian,
                'secure_code',
                $notes
            );
            $lockedGuardian->update([
                'failed_verification_attempts' => 0,
                'verification_locked_until' => null,
                'last_verified_at' => now(),
            ]);

            return $event;
        });

        if (! $event) {
            throw new DomainException('Pickup verification failed. Confirm the active guardian and code.');
        }

        return $event;
    }

    public function recordMovement(StudentTransportAssignment $assignment, User $actor, string $eventType, ?string $notes = null): TransportEvent
    {
        $this->assertSameSchool($assignment->school_id, $actor);
        if ($assignment->status !== 'active') {
            throw new DomainException('Only active transport assignments can receive movement events.');
        }
        if (! in_array($eventType, ['boarded', 'arrived', 'dropped_off'], true)) {
            throw new DomainException('Select a supported transport event.');
        }

        return $this->createEvent($assignment->student_id, $actor, $eventType, $assignment, notes: $notes);
    }

    private function createEvent(
        int $studentId,
        User $actor,
        string $eventType,
        ?StudentTransportAssignment $assignment = null,
        ?AuthorizedPickupGuardian $guardian = null,
        ?string $verificationMethod = null,
        ?string $notes = null,
    ): TransportEvent {
        return TransportEvent::query()->create([
            'school_id' => $actor->school_id,
            'student_id' => $studentId,
            'student_transport_assignment_id' => $assignment?->id,
            'authorized_pickup_guardian_id' => $guardian?->id,
            'transport_route_id' => $assignment?->transport_route_id,
            'transport_stop_id' => $assignment?->transport_stop_id,
            'event_type' => $eventType,
            'verification_method' => $verificationMethod,
            'recorded_by' => $actor->id,
            'recorder_name' => $actor->name,
            'notes' => $this->nullable($notes),
            'occurred_at' => now(),
        ]);
    }

    private function assertRouteSchool(TransportRoute $route, User $actor): void
    {
        $this->assertSameSchool($route->school_id, $actor);
        if (! $route->is_active) {
            throw new DomainException('The selected transport route is inactive.');
        }
    }

    private function assertSameSchool(int $schoolId, User $actor): void
    {
        if ((int) $schoolId !== (int) $actor->school_id) {
            throw new DomainException('The transport record belongs to another school.');
        }
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
