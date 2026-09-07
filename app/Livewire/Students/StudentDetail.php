<?php

namespace App\Livewire\Students;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\StudentPeriodStatus;
use App\Models\StudentRecord;
use App\Models\User;
use Livewire\Component;

class StudentDetail extends Component
{
    public User $student;
    public $activeTab = 'profile';
    public ?int $statusAcademicYearId = null;
    public ?int $statusSemesterId = null;
    public string $enrollmentStatus = 'withdrawn';
    public string $statusReason = '';
    public string $statusNotes = '';
    public $statusAcademicYears;
    public $statusSemesters;
    public $statusHistory;
    public ?int $studentRecordId = null;

    public function mount($studentId)
    {
        $this->student = User::with([
            'studentRecord.myClass',
            'studentRecord.section',
            'feeInvoices'
        ])
            ->role('student')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($studentId);

        $studentRecord = StudentRecord::withoutGlobalScope('notGraduated')
            ->where('user_id', $this->student->id)
            ->firstOrFail();
        $this->student->setRelation('studentRecord', $studentRecord);
        $this->studentRecordId = $studentRecord->id;

        // Check if parent accessing their child
        if (auth()->user()->hasRole('parent')) {
            if ($this->student->parents()->where('parent_records.user_id', auth()->user()->id)->count() <= 0) {
                abort(404);
            }
        }

        $this->statusAcademicYears = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderByDesc('start_year')
            ->get();
        $this->statusAcademicYearId = auth()->user()->school?->academic_year_id;
        $this->loadStatusSemesters();
        $this->loadStatusHistory();
    }

    public function updatedStatusAcademicYearId(): void
    {
        $this->statusSemesterId = null;
        $this->loadStatusSemesters();
    }

    public function savePeriodStatus(): void
    {
        $this->authorizeStatusManagement();

        $validated = $this->validate([
            'statusAcademicYearId' => ['required', 'integer'],
            'statusSemesterId' => ['nullable', 'integer'],
            'enrollmentStatus' => ['required', 'in:withdrawn,transferred,inactive,suspended'],
            'statusReason' => ['required', 'string', 'max:100'],
            'statusNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $yearExists = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->whereKey($validated['statusAcademicYearId'])
            ->exists();
        abort_unless($yearExists, 422, 'Select a valid academic year.');

        if ($validated['statusSemesterId']) {
            $semesterExists = Semester::query()
                ->where('school_id', auth()->user()->school_id)
                ->where('academic_year_id', $validated['statusAcademicYearId'])
                ->whereKey($validated['statusSemesterId'])
                ->exists();
            abort_unless($semesterExists, 422, 'Select a valid effective term.');
        }

        StudentPeriodStatus::query()->updateOrCreate(
            [
                'student_record_id' => $this->studentRecordId,
                'academic_year_id' => $validated['statusAcademicYearId'],
            ],
            [
                'school_id' => auth()->user()->school_id,
                'semester_id' => $validated['statusSemesterId'],
                'status' => $validated['enrollmentStatus'],
                'reason' => trim($validated['statusReason']),
                'notes' => trim($validated['statusNotes'] ?? '') ?: null,
                'recorded_by' => auth()->id(),
            ]
        );

        $this->reset(['statusReason', 'statusNotes']);
        $this->loadStatusHistory();
        session()->flash('success', 'Student activity status saved. Class history and previous results were preserved.');
    }

    public function restorePeriodActivity(int $statusId): void
    {
        $this->authorizeStatusManagement();

        StudentPeriodStatus::query()
            ->where('school_id', auth()->user()->school_id)
            ->where('student_record_id', $this->studentRecordId)
            ->whereKey($statusId)
            ->delete();

        $this->loadStatusHistory();
        session()->flash('success', 'The student is active again for that academic year.');
    }

    protected function loadStatusSemesters(): void
    {
        $this->statusSemesters = $this->statusAcademicYearId
            ? Semester::query()
                ->where('school_id', auth()->user()->school_id)
                ->where('academic_year_id', $this->statusAcademicYearId)
                ->orderBy('id')
                ->get()
            : collect();
    }

    protected function loadStatusHistory(): void
    {
        $this->statusHistory = StudentPeriodStatus::query()
            ->where('school_id', auth()->user()->school_id)
            ->where('student_record_id', $this->studentRecordId)
            ->with(['academicYear', 'semester', 'recorder'])
            ->latest()
            ->get();
    }

    protected function authorizeStatusManagement(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super-admin', 'super_admin']) === true,
            403
        );
    }

    public function changeTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function printProfile()
    {
        // This will trigger a browser print
        $this->dispatch('print-profile');
    }

    public function render()
    {
        return view('livewire.students.student-detail')
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('students.index'), 'text' => 'Students'],
                    ['href' => route('students.show', $this->student->id), 'text' => $this->student->name, 'active' => true],
                ]
            ])
            ->title($this->student->name . "'s Profile");
    }
}
