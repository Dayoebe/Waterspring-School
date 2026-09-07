<?php

namespace App\Support;

use App\Models\ClassExamParticipation;
use Illuminate\Support\Collection;

class ClassExamActivity
{
    public static function excludedClassIds(
        int $schoolId,
        int $academicYearId,
        ?int $semesterId = null
    ): Collection {
        return ClassExamParticipation::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', ClassExamParticipation::NON_INTERNAL_STATUSES)
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->pluck('my_class_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public static function participation(
        int $schoolId,
        int $academicYearId,
        int $semesterId,
        int $classId
    ): ?ClassExamParticipation {
        return ClassExamParticipation::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->where('my_class_id', $classId)
            ->whereIn('status', ClassExamParticipation::NON_INTERNAL_STATUSES)
            ->first();
    }

    public static function classParticipates(
        int $schoolId,
        int $academicYearId,
        int $semesterId,
        int $classId
    ): bool {
        return self::participation($schoolId, $academicYearId, $semesterId, $classId) === null;
    }
}
