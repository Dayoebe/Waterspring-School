<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditTrail
{
    public function record(
        ?int $schoolId,
        string $category,
        string $event,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?User $actor = null,
        ?string $subjectLabel = null,
    ): AuditEvent {
        $actor ??= auth()->user();

        return AuditEvent::query()->withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'category' => $category,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?: 'System',
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'description' => $description,
            'old_values' => $this->values($oldValues),
            'new_values' => $this->values($newValues),
            'metadata' => $this->values($metadata),
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
            'occurred_at' => now(),
        ]);
    }

    private function values(?array $values): ?array
    {
        return $values === [] ? null : $values;
    }
}
