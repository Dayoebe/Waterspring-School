<?php

namespace App\Services\Dashboard;

use App\Models\AdmissionRegistration;
use App\Models\AttendanceRecord;
use App\Models\FeeInvoice;
use App\Models\Result;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

final class DashboardMetrics
{
    public function pendingAdmissionsCount(User $user): int
    {
        if (! $user->school_id || ! $user->can('read admission registration')) {
            return 0;
        }

        return AdmissionRegistration::query()
            ->where('school_id', $user->school_id)
            ->where('status', 'pending')
            ->count();
    }

    public function overdueInvoicesCount(User $user): int
    {
        if (! $user->school_id || ! $user->can('read fee invoice')) {
            return 0;
        }

        return FeeInvoice::query()
            ->whereHas('user', fn ($query) => $query->where('school_id', $user->school_id))
            ->whereDate('due_date', '<', today())
            ->whereHas('feeInvoiceRecords', fn ($query) => $query->isDue())
            ->count();
    }

    public function attendanceRateForToday(User $user, Carbon $today): ?int
    {
        if (! $user->school_id || ! $user->can('read attendance')) {
            return null;
        }

        $records = AttendanceRecord::query()
            ->whereHas('attendanceSession', fn ($query) => $query
                ->where('school_id', $user->school_id)
                ->whereDate('attendance_date', $today))
            ->get(['status']);

        if ($records->isEmpty()) {
            return null;
        }

        $present = $records->whereIn('status', ['present', 'late'])->count();

        return (int) round(($present / $records->count()) * 100);
    }

    public function absentStudentsToday(User $user): int
    {
        if (! $user->school_id || ! $user->can('read attendance')) {
            return 0;
        }

        return AttendanceRecord::query()
            ->where('status', 'absent')
            ->whereHas('attendanceSession', fn ($query) => $query
                ->where('school_id', $user->school_id)
                ->whereDate('attendance_date', today()))
            ->count();
    }

    public function unapprovedResultCount(User $user): int
    {
        if (! $user->school?->academic_year_id || ! $user->school?->semester_id || ! $user->can('view result')) {
            return 0;
        }

        $studentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $user->school_id,
            $user->school->academic_year_id
        );

        return Result::query()
            ->where('academic_year_id', $user->school->academic_year_id)
            ->where('semester_id', $user->school->semester_id)
            ->whereIn('student_record_id', $studentRecordIds)
            ->where('approved', false)
            ->count();
    }

    public function resultCompletionRate(User $user, $studentRecordIds, bool $isStaff): ?int
    {
        if (! $isStaff || ! $user->school?->academic_year_id || ! $user->school?->semester_id) {
            return null;
        }

        $results = Result::query()
            ->where('academic_year_id', $user->school->academic_year_id)
            ->where('semester_id', $user->school->semester_id)
            ->whereIn('student_record_id', $studentRecordIds)
            ->get(['approved']);

        if ($results->isEmpty()) {
            return null;
        }

        return (int) round(($results->where('approved', true)->count() / $results->count()) * 100);
    }
}
