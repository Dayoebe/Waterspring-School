<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentAccess
{
    public function children(User $parent): Collection
    {
        return $parent->children()
            ->where('users.school_id', $parent->school_id)
            ->whereNull('users.deleted_at')
            ->with('studentRecord:id,user_id,my_class_id')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);
    }

    public function teacherIdsForChild(User $parent, int $studentId): Collection
    {
        $student = $this->children($parent)->firstWhere('id', $studentId);
        $classId = $student?->studentRecord?->my_class_id;
        if (! $classId) {
            return collect();
        }

        $classTeachers = DB::table('class_teacher')->where('class_id', $classId)->pluck('teacher_id');
        $subjectTeachers = DB::table('subject_teacher')
            ->where('school_id', $parent->school_id)
            ->where(function ($query) use ($classId): void {
                $query->where('my_class_id', $classId)
                    ->orWhere(function ($general) use ($classId): void {
                        $general->where('is_general', true)
                            ->whereIn('subject_id', DB::table('class_subject')->where('my_class_id', $classId)->select('subject_id'));
                    });
            })
            ->pluck('user_id');

        return $classTeachers->merge($subjectTeachers)->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function canBook(User $parent, int $studentId, int $teacherId): bool
    {
        return $this->teacherIdsForChild($parent, $studentId)->contains($teacherId)
            && User::query()->whereKey($teacherId)->where('school_id', $parent->school_id)->exists();
    }

    public function isLeadership(User $user): bool
    {
        return $user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']);
    }
}
