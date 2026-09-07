<?php

namespace App\Support;

use App\Models\StudentPeriodStatus;
use Illuminate\Support\Collection;

class StudentPeriodActivity
{
    public static function excludedStudentRecordIds(
        int $schoolId,
        int $academicYearId,
        ?int $semesterId = null
    ): Collection {
        return StudentPeriodStatus::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', StudentPeriodStatus::EXCLUDED_STATUSES)
            ->when($semesterId, function ($query) use ($semesterId) {
                $query->where(function ($query) use ($semesterId) {
                    $query->whereNull('semester_id')
                        ->orWhere('semester_id', '<=', $semesterId);
                });
            })
            ->pluck('student_record_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public static function filterIncluded(
        Collection $studentRecordIds,
        int $schoolId,
        int $academicYearId,
        ?int $semesterId = null
    ): Collection {
        return $studentRecordIds
            ->diff(self::excludedStudentRecordIds($schoolId, $academicYearId, $semesterId))
            ->values();
    }
}
