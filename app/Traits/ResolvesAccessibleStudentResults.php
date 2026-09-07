<?php

namespace App\Traits;

use App\Models\StudentRecord;
use App\Support\ResultPublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait ResolvesAccessibleStudentResults
{
    use RestrictsTeacherResultViewing;

    protected function resultStaffRoles(): array
    {
        return ['super-admin', 'super_admin', 'admin', 'principal'];
    }

    protected function canBrowseAllStudentResults(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole($this->resultStaffRoles());
    }

    protected function isStudentResultViewer(): bool
    {
        $user = auth()->user();

        return $user !== null
            && !$this->canBrowseAllStudentResults()
            && $user->hasRole('student');
    }

    protected function isParentResultViewer(): bool
    {
        $user = auth()->user();

        return $user !== null
            && !$this->canBrowseAllStudentResults()
            && $user->hasRole('parent');
    }

    protected function isRestrictedToOwnFamilyResults(): bool
    {
        return $this->isStudentResultViewer() || $this->isParentResultViewer();
    }

    protected function familyCanViewTermResult(
        int|string|null $academicYearId,
        int|string|null $semesterId
    ): bool {
        if (!$this->isRestrictedToOwnFamilyResults()) {
            return true;
        }

        $schoolId = auth()->user()?->school_id;

        return $schoolId && $academicYearId && $semesterId
            ? ResultPublicationStatus::termIsPublished(
                (int) $schoolId,
                (int) $academicYearId,
                (int) $semesterId
            )
            : false;
    }

    protected function familyCanViewAnnualResult(int|string|null $academicYearId): bool
    {
        if (!$this->isRestrictedToOwnFamilyResults()) {
            return true;
        }

        $schoolId = auth()->user()?->school_id;

        return $schoolId && $academicYearId
            ? ResultPublicationStatus::annualIsPublished((int) $schoolId, (int) $academicYearId)
            : false;
    }

    protected function ensureFamilyCanViewTermResult(
        int|string|null $academicYearId,
        int|string|null $semesterId
    ): void {
        abort_unless(
            $this->familyCanViewTermResult($academicYearId, $semesterId),
            403,
            'This term result has not been published yet.'
        );
    }

    protected function ensureFamilyCanViewAnnualResult(int|string|null $academicYearId): void
    {
        abort_unless(
            $this->familyCanViewAnnualResult($academicYearId),
            403,
            'This annual result has not been published yet.'
        );
    }

    protected function accessibleStudentUserIds(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        if ($this->canBrowseAllStudentResults()) {
            return collect();
        }

        if ($this->isStudentResultViewer()) {
            return collect([$user->id]);
        }

        if ($this->isParentResultViewer()) {
            return $user->children()
                ->where('users.school_id', $user->school_id)
                ->whereNull('users.deleted_at')
                ->pluck('users.id');
        }

        return collect();
    }

    protected function accessibleStudentRecordsQuery(): Builder
    {
        $schoolId = auth()->user()?->school_id;

        $query = StudentRecord::query()
            ->whereHas('user', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId)
                    ->whereNull('deleted_at');
            });

        if ($this->canBrowseAllStudentResults()) {
            return $query;
        }

        if ($this->isRestrictedTeacherResultViewer()) {
            $classIds = $this->accessibleClassTeacherClassIds();

            if ($classIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('student_records.my_class_id', $classIds);
        }

        $studentUserIds = $this->accessibleStudentUserIds();

        if ($studentUserIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('user_id', $studentUserIds);
    }

    protected function findAccessibleStudentRecordById(int $studentRecordId): StudentRecord
    {
        return $this->accessibleStudentRecordsQuery()->findOrFail($studentRecordId);
    }

    protected function findAccessibleStudentRecordByUserId(int $userId): StudentRecord
    {
        return $this->accessibleStudentRecordsQuery()
            ->where('user_id', $userId)
            ->firstOrFail();
    }
}
