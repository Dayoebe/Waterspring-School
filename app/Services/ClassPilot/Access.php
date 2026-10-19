<?php

namespace App\Services\ClassPilot;

use App\Models\MyClass;
use App\Models\StudentRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Access
{
    public function teacherClasses(User $user): Builder
    {
        $generalSubjectIds = DB::table('subject_teacher')->where('user_id', $user->id)
            ->where('school_id', $user->school_id)->where('is_general', true)->pluck('subject_id');
        $generalClassIds = DB::table('class_subject')->whereIn('subject_id', $generalSubjectIds)->select('my_class_id');

        return MyClass::query()->whereHas('classGroup', fn (Builder $q) => $q->where('school_id', $user->school_id))
            ->where(function (Builder $q) use ($user, $generalClassIds) {
                $q->whereHas('teachers', fn (Builder $teachers) => $teachers->where('users.id', $user->id))
                    ->orWhereIn('my_classes.id', DB::table('subject_teacher')->where('user_id', $user->id)->where('school_id', $user->school_id)->whereNotNull('my_class_id')->select('my_class_id'))
                    ->orWhereIn('my_classes.id', $generalClassIds);
            });
    }

    public function teacherSubjects(User $user, int $classId): Builder
    {
        abort_unless($this->teacherClasses($user)->whereKey($classId)->exists() || $user->hasAnyRole(['super-admin', 'super_admin', 'principal', 'admin']), 403);
        $query = Subject::query()->where('school_id', $user->school_id)->active()->whereHas('classes', fn (Builder $q) => $q->where('my_classes.id', $classId));
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['principal', 'admin'])) {
            $query->whereHas('teachers', fn (Builder $q) => $q->where('users.id', $user->id)->where(fn ($p) => $p->where('subject_teacher.my_class_id', $classId)->orWhere('subject_teacher.is_general', true)));
        }

        return $query;
    }

    public function studentRecord(User $user): StudentRecord
    {
        return StudentRecord::query()->where('user_id', $user->id)->whereHas('user', fn ($q) => $q->where('school_id', $user->school_id))->firstOrFail();
    }

    public function childRecord(User $parent, int $studentRecordId): StudentRecord
    {
        $childUserIds = $parent->children()
            ->where('users.school_id', $parent->school_id)
            ->whereNull('users.deleted_at')
            ->pluck('users.id');

        return StudentRecord::query()
            ->whereKey($studentRecordId)
            ->whereIn('user_id', $childUserIds)
            ->whereHas('user', fn ($query) => $query->where('school_id', $parent->school_id))
            ->firstOrFail();
    }
}
