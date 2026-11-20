<?php

namespace App\Services;

use App\Models\MyClass;
use App\Models\ParentRecord;
use App\Models\StudentRecord;
use App\Models\User;

class StudentRecordAccessService
{
    public function canViewTimeline(User $viewer, User $student): bool
    {
        return $this->canView($viewer, $student, 'can_view_growth_timeline', 'view student growth timeline');
    }

    public function canViewPortfolio(User $viewer, User $student): bool
    {
        return $this->canView($viewer, $student, 'can_view_portfolio', 'manage student portfolio');
    }

    public function canContribute(User $actor, User $student): bool
    {
        if ((int) $actor->school_id !== (int) $student->school_id || ! $actor->can('contribute student records')) {
            return false;
        }
        if ($actor->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin'])) {
            return true;
        }

        $academicYearId = $actor->school?->academic_year_id;
        $recordId = $student->studentRecord?->id;
        if (! $academicYearId || ! $recordId) {
            return false;
        }

        return MyClass::query()->whereHas('teachers', fn ($query) => $query->whereKey($actor->id))
            ->get()->contains(fn (MyClass $class) => $class->hasStudent($recordId, $academicYearId));
    }

    public function permittedStudents(User $viewer, string $context)
    {
        if ($viewer->hasRole('student')) {
            return User::query()->whereKey($viewer->id);
        }
        if ($viewer->hasRole('parent')) {
            $authority = $context === 'portfolio' ? 'can_view_portfolio' : 'can_view_growth_timeline';
            $ids = ParentRecord::query()->active()->where('user_id', $viewer->id)->whereNotNull('verified_at')
                ->where($authority, true)->pluck('student_id');

            return User::query()->whereIn('id', $ids);
        }
        if ($viewer->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin'])) {
            return User::query()->where('school_id', $viewer->school_id)->role('student');
        }

        $yearId = $viewer->school?->academic_year_id;
        $classIds = MyClass::query()->whereHas('teachers', fn ($query) => $query->whereKey($viewer->id))->pluck('id');
        $recordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear((int) $viewer->school_id, $yearId)
            ->filter(fn ($recordId) => $classIds->contains(StudentRecord::query()->find($recordId)?->getClassForYear($yearId)?->id));

        return User::query()->whereHas('studentRecord', fn ($query) => $query->whereIn('id', $recordIds));
    }

    private function canView(User $viewer, User $student, string $authority, string $staffPermission): bool
    {
        if ((int) $viewer->school_id !== (int) $student->school_id) {
            return false;
        }
        if ($viewer->id === $student->id && $viewer->hasRole('student')) {
            return true;
        }
        if ($viewer->can($staffPermission)) {
            return $viewer->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']) || $this->canContribute($viewer, $student);
        }

        return ParentRecord::query()->active()->where('user_id', $viewer->id)->where('student_id', $student->id)
            ->whereNotNull('verified_at')->where($authority, true)->exists();
    }
}
