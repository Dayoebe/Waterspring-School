<?php

namespace App\Services;

use App\Models\ParentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuardianLinkService
{
    public const RELATIONSHIP_TYPES = [
        'mother', 'father', 'guardian', 'grandparent', 'sibling', 'other',
    ];

    public function link(User $guardian, User $student, User $actor, array $attributes = []): ParentRecord
    {
        $this->validatePeople($guardian, $student, $actor);

        return DB::transaction(function () use ($guardian, $student, $actor, $attributes): ParentRecord {
            User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $existingLinks = ParentRecord::query()
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->get();

            $link = ParentRecord::query()->firstOrNew([
                'user_id' => $guardian->id,
                'student_id' => $student->id,
            ]);

            if ($link->exists) {
                return $link;
            }

            $link->fill([
                'relationship_type' => $this->relationshipType($attributes['relationship_type'] ?? 'guardian'),
                'is_primary_contact' => $existingLinks->isEmpty(),
                'receives_digest' => (bool) ($attributes['receives_digest'] ?? false),
                'can_view_academics' => (bool) ($attributes['can_view_academics'] ?? true),
                'can_view_attendance' => (bool) ($attributes['can_view_attendance'] ?? true),
                'can_view_portfolio' => (bool) ($attributes['can_view_portfolio'] ?? false),
                'can_consent_events' => (bool) ($attributes['can_consent_events'] ?? false),
                'active_from' => $attributes['active_from'] ?? today(),
                'active_until' => $attributes['active_until'] ?? null,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);
            $link->save();

            app(AuditTrail::class)->record(
                $student->school_id,
                'guardians',
                'guardian.linked',
                'A verified guardian relationship was created.',
                $link,
                newValues: [
                    'relationship_type' => $link->relationship_type,
                    'is_primary_contact' => $link->is_primary_contact,
                    'receives_digest' => $link->receives_digest,
                    'can_view_academics' => $link->can_view_academics,
                    'can_view_attendance' => $link->can_view_attendance,
                    'can_view_portfolio' => $link->can_view_portfolio,
                    'can_consent_events' => $link->can_consent_events,
                ],
                metadata: ['guardian_id' => $guardian->id, 'student_id' => $student->id],
                actor: $actor,
                subjectLabel: $student->name,
            );

            return $link;
        }, 3);
    }

    private function validatePeople(User $guardian, User $student, User $actor): void
    {
        if (! $guardian->hasRole('parent') || ! $student->hasRole('student')) {
            throw ValidationException::withMessages(['student' => 'A guardian can only be linked to a student account.']);
        }

        if (! $guardian->school_id || $guardian->school_id !== $student->school_id || $guardian->school_id !== $actor->school_id) {
            throw ValidationException::withMessages(['student' => 'Guardian and student must belong to the same school.']);
        }

        if (! $actor->can('update parent') && ! $actor->can('approve admission registration')) {
            throw ValidationException::withMessages(['student' => 'You are not authorised to manage guardian relationships.']);
        }

        if (! $student->studentRecord()->exists()) {
            throw ValidationException::withMessages(['student' => 'The selected student does not have a student record.']);
        }
    }

    private function relationshipType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, self::RELATIONSHIP_TYPES, true) ? $type : 'guardian';
    }
}
