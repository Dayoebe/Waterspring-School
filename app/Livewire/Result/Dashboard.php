<?php

namespace App\Livewire\Result;

use App\Models\Result;
use App\Models\StudentRecord;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public $academicYearId;

    public $semesterId;

    public $stats = [];

    public bool $canOpenViewResults = true;

    public bool $canOpenHistory = true;

    public function mount()
    {
        $school = auth()->user()?->school;

        $this->academicYearId = $school?->academic_year_id;
        $this->semesterId = $school?->semester_id;
        $this->loadStats();
    }

    #[On('academic-period-changed')]
    public function handlePeriodChange($data)
    {
        $this->academicYearId = $data['academicYearId'];
        $this->semesterId = $data['semesterId'];
        $this->loadStats();
    }

    protected function loadStats()
    {
        if (! $this->academicYearId || ! $this->semesterId) {
            $this->stats = [];

            return;
        }

        $schoolId = auth()->user()?->school_id;

        $studentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            $schoolId,
            $this->academicYearId
        );

        $totalStudents = $studentRecordIds->count();
        $inactiveStudents = User::query()
            ->where('school_id', $schoolId)
            ->role('student')
            ->where('locked', true)
            ->whereHas('studentRecord', fn ($query) => $query->where('is_graduated', false))
            ->count();

        $results = Result::where('academic_year_id', $this->academicYearId)
            ->where('semester_id', $this->semesterId)
            ->whereIn('student_record_id', $studentRecordIds)
            ->get();

        $studentsWithResults = $results->pluck('student_record_id')->unique()->count();
        $totalResults = $results->count();
        $avgScore = $results->avg('total_score') ?? 0;

        $recentlyUploaded = Result::where('academic_year_id', $this->academicYearId)
            ->where('semester_id', $this->semesterId)
            ->whereIn('student_record_id', $studentRecordIds)
            ->with([
                'studentRecord.user',
                'subject',
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Result $result): array {
                $studentRecord = $result->studentRecord;
                $studentUser = $studentRecord?->user;

                return [
                    'student_name' => $studentUser?->name ?? 'Unknown Student',
                    'student_photo_url' => $studentUser?->profile_photo_url ?? asset('images/default-avatar.png'),
                    'subject_name' => $result->subject?->name ?? 'Unknown Subject',
                    'total_score' => (int) ($result->total_score ?? 0),
                    'uploaded_at_human' => $result->created_at?->diffForHumans() ?? '',
                ];
            })
            ->all();

        $this->stats = [
            'total_students' => $totalStudents,
            'inactive_students' => $inactiveStudents,
            'students_with_results' => $studentsWithResults,
            'pending_students' => $totalStudents - $studentsWithResults,
            'total_results' => $totalResults,
            'average_score' => round($avgScore, 2),
            'completion_rate' => $totalStudents > 0
                ? round(($studentsWithResults / $totalStudents) * 100, 2)
                : 0,
            'recently_uploaded' => $recentlyUploaded,
        ];
    }

    public function render()
    {
        return view('livewire.result.dashboard')
            ->layout('layouts.result', [
                'title' => 'Results Dashboard',
                'page_heading' => 'Results Dashboard',
            ]);
    }
}
