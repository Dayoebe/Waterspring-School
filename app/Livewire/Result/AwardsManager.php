<?php

namespace App\Livewire\Result;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\{Result, MyClass, StudentRecord, Semester};
use App\Traits\RestrictsTeacherResultViewing;
use App\Support\StudentPeriodActivity;
use App\Support\ClassExamActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AwardsManager extends Component
{
    use RestrictsTeacherResultViewing;

    public $academicYearId;
    public $semesterId;
    public $selectedClassId;
    public $viewType = 'termly'; // termly or annual
    
    public $classes;
    public $semesters = [];
    public $topPerformers = [];
    public $statusMessage;

    public function mount()
    {
        $this->classes = $this->accessibleClassTeacherClassesQuery()
            ->orderBy('name')
            ->get();

        abort_unless($this->currentUserCanAccessClassOnlyResultTools(), 403);

        $school = auth()->user()?->school;

        $this->academicYearId = $school?->academic_year_id;
        $this->semesterId = $school?->semester_id;

        if ($this->isRestrictedTeacherResultViewer() && $this->selectedClassId === null) {
            $this->selectedClassId = $this->classes->first()?->id;
        }
        
        $this->loadSemesters();
        $this->loadTopPerformers();
    }

    #[On('academic-period-changed')]
    public function handlePeriodChange($data)
    {
        $this->academicYearId = $data['academicYearId'];
        $this->semesterId = $data['semesterId'];
        $this->loadSemesters();
        $this->loadTopPerformers();
    }

    public function updatedSelectedClassId()
    {
        $this->loadTopPerformers();
    }

    public function updatedViewType()
    {
        if (!in_array($this->viewType, ['termly', 'annual'], true)) {
            $this->viewType = 'termly';
        }

        $this->loadTopPerformers();
    }

    protected function loadSemesters()
    {
        if ($this->academicYearId) {
            $this->semesters = Semester::where('academic_year_id', $this->academicYearId)
                ->where('school_id', auth()->user()->school_id)
                ->orderBy('id')
                ->get();
        } else {
            $this->semesters = collect();
        }
    }

    public function loadTopPerformers()
    {
        $this->topPerformers = collect();
        $this->statusMessage = null;

        if (!$this->academicYearId) {
            $this->statusMessage = 'Select an academic year to calculate awards.';
            return;
        }

        if ($this->isRestrictedTeacherResultViewer() && !$this->selectedClassId) {
            $this->statusMessage = 'A class must be selected.';
            return;
        }

        if (!in_array($this->viewType, ['termly', 'annual'], true)) {
            $this->viewType = 'termly';
        }

        $periodSemesters = $this->awardSemesters();
        if ($periodSemesters->isEmpty()) {
            return;
        }

        if ($this->selectedClassId) {
            $classExists = MyClass::where('id', $this->selectedClassId)
                ->whereHas('classGroup', function ($q) {
                    $q->where('school_id', auth()->user()->school_id);
                })
                ->exists();

            if (!$classExists || !$this->currentUserCanViewClassTeacherClass($this->selectedClassId)) {
                $this->statusMessage = 'The selected class is unavailable or outside your access.';
                return;
            }

            $excludedForAwards = ClassExamActivity::excludedClassIds(
                (int) auth()->user()->school_id,
                (int) $this->academicYearId,
                $this->viewType === 'annual' ? null : (int) $this->semesterId
            );
            if ($excludedForAwards->contains((int) $this->selectedClassId)) {
                $this->statusMessage = $this->viewType === 'annual'
                    ? 'This class did not complete all internal examinations and is excluded from annual awards.'
                    : 'This class did not participate in the internal examination for this term, so no internal awards are calculated.';
                return;
            }
        }

        $studentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            auth()->user()?->school_id,
            $this->academicYearId,
            $this->selectedClassId ? (int) $this->selectedClassId : null,
            null,
            true
        );
        $studentRecordIds = StudentPeriodActivity::filterIncluded(
            $studentRecordIds,
            (int) auth()->user()->school_id,
            (int) $this->academicYearId,
            $this->viewType === 'annual' ? null : (int) $this->semesterId
        );
        $excludedClassIds = ClassExamActivity::excludedClassIds(
            (int) auth()->user()->school_id,
            (int) $this->academicYearId,
            $this->viewType === 'annual' ? null : (int) $this->semesterId
        );
        if ($excludedClassIds->isNotEmpty()) {
            $excludedStudentIds = DB::table('academic_year_student_record')
                ->where('academic_year_id', $this->academicYearId)
                ->whereIn('my_class_id', $excludedClassIds)
                ->pluck('student_record_id');
            $studentRecordIds = $studentRecordIds->diff($excludedStudentIds)->values();
        }

        if ($studentRecordIds->isEmpty()) {
            $this->statusMessage = 'No active students were found for the selected class and academic year.';
            return;
        }

        $classAssignments = DB::table('academic_year_student_record')
            ->where('academic_year_id', $this->academicYearId)
            ->whereIn('student_record_id', $studentRecordIds)
            ->get()
            ->keyBy('student_record_id');
        $classIds = $classAssignments->pluck('my_class_id')->unique()->values();
        $classes = MyClass::query()
            ->whereIn('id', $classIds)
            ->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))
            ->with(['subjects' => fn ($query) => $query->orderBy('name')])
            ->get()
            ->keyBy('id');
        $allowedSubjectIds = $classes
            ->flatMap(fn (MyClass $class) => $class->subjects->pluck('id'))
            ->unique()
            ->values();

        $students = StudentRecord::withoutGlobalScope('notGraduated')
            ->whereIn('id', $studentRecordIds)
            ->whereHas('user', fn ($query) => $query
                ->where('school_id', auth()->user()->school_id)
                ->whereNull('deleted_at'))
            ->with('user')
            ->get()
            ->keyBy('id');
        $enrolledSubjectIds = DB::table('student_subject')
            ->whereIn('student_record_id', $students->keys())
            ->whereIn('subject_id', $allowedSubjectIds)
            ->get(['student_record_id', 'subject_id'])
            ->groupBy('student_record_id')
            ->map(fn (Collection $rows) => $rows->pluck('subject_id')->map(fn ($id) => (int) $id));
        $resultsByStudent = Result::query()
            ->whereIn('student_record_id', $students->keys())
            ->where('academic_year_id', $this->academicYearId)
            ->whereIn('semester_id', $periodSemesters->pluck('id'))
            ->whereIn('subject_id', $allowedSubjectIds)
            ->get()
            ->groupBy('student_record_id');

        $studentData = collect();
        $incompleteStudents = collect();

        foreach ($students as $studentRecordId => $student) {
            $classId = (int) ($classAssignments->get($studentRecordId)?->my_class_id ?? 0);
            $class = $classes->get($classId);
            $generalSubjectIds = $class
                ? $class->subjects->where('is_general', true)->pluck('id')
                : collect();
            $studentSubjectIds = $generalSubjectIds
                ->merge($enrolledSubjectIds->get($studentRecordId, collect()))
                ->unique()
                ->values();
            $subjects = $class
                ? $class->subjects->whereIn('id', $studentSubjectIds)->values()
                : collect();

            if (!$class || $subjects->count() < 3) {
                $incompleteStudents->push($student->user?->name ?? "Student {$studentRecordId}");
                continue;
            }

            $studentResults = collect($resultsByStudent->get($studentRecordId, collect()))
                ->filter(fn (Result $result) => $subjects->contains('id', $result->subject_id))
                ->keyBy(fn (Result $result) => "{$result->semester_id}:{$result->subject_id}");
            $expectedResultCount = $subjects->count() * $periodSemesters->count();

            if ($studentResults->count() !== $expectedResultCount) {
                $incompleteStudents->push($student->user?->name ?? "Student {$studentRecordId}");
                continue;
            }

            $subjectScores = [];
            foreach ($subjects as $subject) {
                $termScores = $periodSemesters
                    ->map(fn (Semester $semester) => (float) $studentResults[
                        "{$semester->id}:{$subject->id}"
                    ]->total_score);
                $subjectScores[$subject->id] = round($termScores->average(), 2);
            }

            $termAverages = $periodSemesters->map(function (Semester $semester) use ($subjects, $studentResults) {
                return $subjects->avg(
                    fn ($subject) => (float) $studentResults["{$semester->id}:{$subject->id}"]->total_score
                );
            });
            $rawTotal = $studentResults->sum(fn (Result $result) => (float) $result->total_score);
            $average = round(collect($subjectScores)->average(), 2);
            $consistencyScores = $this->viewType === 'annual'
                ? $termAverages->all()
                : array_values($subjectScores);

            $studentData->put($studentRecordId, [
                'student' => $student,
                'class_name' => $class->name,
                'total' => round($rawTotal, 2),
                'maximum_total' => $expectedResultCount * 100,
                'average' => $average,
                'a_grades' => collect($subjectScores)->filter(fn ($score) => $score >= 75)->count(),
                'consistency' => round($this->calculateStdDev($consistencyScores), 2),
                'subject_count' => $subjects->count(),
                'expected_subjects' => $subjects->count(),
                'completion_ratio' => 1.0,
                'subject_scores' => $subjectScores,
                'subjects' => $subjects->keyBy('id'),
            ]);
        }

        if ($incompleteStudents->isNotEmpty()) {
            $period = $this->viewType === 'annual' ? 'academic year' : 'term';
            $this->statusMessage = sprintf(
                'Awards are withheld because %d of %d student result sets are incomplete for this %s. Complete every required subject before declaring winners.',
                $incompleteStudents->count(),
                $students->count(),
                $period
            );
            return;
        }

        $eligibleStudents = $studentData
            ->filter(fn (array $student) => $student['average'] >= 40)
            ->sort(function (array $left, array $right) {
                return [$right['average'], $left['student']->user?->name]
                    <=> [$left['average'], $right['student']->user?->name];
            });

        if ($eligibleStudents->isEmpty()) {
            $this->statusMessage = 'All results are complete, but no student met the minimum 40% award average.';
            return;
        }

        $top3 = $this->rankTopStudents($eligibleStudents);
        $mostAs = $this->winnerWithTies(
            $eligibleStudents->filter(fn (array $student) => $student['a_grades'] > 0),
            'a_grades',
            true
        );
        $mostConsistent = $this->winnerWithTies(
            $eligibleStudents->filter(fn (array $student) => $student['average'] >= 50),
            'consistency',
            false
        );
        $bestInSubjects = $this->bestSubjectAwards($eligibleStudents);

        $this->topPerformers = collect([
            'top_3' => $top3,
            'most_as' => $mostAs ? $this->formatStudentAward($mostAs) : null,
            'most_consistent' => $mostConsistent ? $this->formatStudentAward($mostConsistent) : null,
            'best_in_subjects' => $bestInSubjects,
        ]);
    }

    protected function formatStudentAward(array $studentData): array
    {
        return [
            'student' => $this->formatStudentSummary(
                $studentData['student'] ?? null,
                $studentData['class_name'] ?? null
            ),
            'total' => (float) ($studentData['total'] ?? 0),
            'maximum_total' => (float) ($studentData['maximum_total'] ?? 0),
            'average' => (float) ($studentData['average'] ?? 0),
            'a_grades' => (int) ($studentData['a_grades'] ?? 0),
            'consistency' => (float) ($studentData['consistency'] ?? 0),
            'subject_count' => (int) ($studentData['subject_count'] ?? 0),
            'expected_subjects' => (int) ($studentData['expected_subjects'] ?? 0),
            'completion_ratio' => (float) ($studentData['completion_ratio'] ?? 0),
            'rank' => (int) ($studentData['rank'] ?? 0),
            'joint_winners' => $studentData['joint_winners'] ?? [],
        ];
    }

    protected function formatStudentSummary(?StudentRecord $studentRecord, ?string $className = null): array
    {
        return [
            'id' => $studentRecord?->id,
            'name' => $studentRecord?->user?->name ?? 'Unknown Student',
            'class_name' => $className ?? $studentRecord?->myClass?->name ?? 'No Class',
            'profile_photo_url' => $studentRecord?->user?->profile_photo_url ?? asset('images/default-avatar.png'),
        ];
    }

    protected function awardSemesters(): Collection
    {
        $semesters = collect($this->semesters);

        if ($this->viewType === 'termly') {
            if (!$this->semesterId) {
                $this->statusMessage = 'Select a term to calculate term awards.';
                return collect();
            }

            $semester = $semesters->firstWhere('id', (int) $this->semesterId);
            if (!$semester) {
                $this->statusMessage = 'The selected term does not belong to this academic year.';
                return collect();
            }

            return collect([$semester]);
        }

        $names = $semesters->map(fn (Semester $semester) => Str::lower(trim($semester->name)));
        $complete = $semesters->count() === 3
            && $names->contains(fn (string $name) => Str::contains($name, 'first'))
            && $names->contains(fn (string $name) => Str::contains($name, 'second'))
            && $names->contains(fn (string $name) => Str::contains($name, 'third'));

        if (!$complete) {
            $this->statusMessage = 'Annual awards require exactly First, Second, and Third Term.';
            return collect();
        }

        return $semesters->values();
    }

    protected function rankTopStudents(Collection $students): array
    {
        $ranked = [];
        $rank = 1;
        $previousAverage = null;

        foreach ($students->values() as $index => $student) {
            if ($previousAverage !== null && $student['average'] < $previousAverage) {
                $rank = $index + 1;
            }
            if ($rank > 3) {
                break;
            }

            $student['rank'] = $rank;
            $ranked[] = $this->formatStudentAward($student);
            $previousAverage = $student['average'];
        }

        return $ranked;
    }

    protected function winnerWithTies(Collection $students, string $metric, bool $descending): ?array
    {
        if ($students->isEmpty()) {
            return null;
        }

        $winningValue = $descending ? $students->max($metric) : $students->min($metric);
        $winners = $students
            ->filter(fn (array $student) => (float) $student[$metric] === (float) $winningValue)
            ->sortBy(fn (array $student) => $student['student']->user?->name)
            ->values();
        $winner = $winners->first();
        $winner['joint_winners'] = $winners
            ->skip(1)
            ->map(fn (array $student) => $this->formatStudentSummary(
                $student['student'],
                $student['class_name']
            ))
            ->all();

        return $this->formatStudentAward($winner);
    }

    protected function bestSubjectAwards(Collection $students): array
    {
        $subjectIds = $students
            ->flatMap(fn (array $student) => array_keys($student['subject_scores']))
            ->unique();
        $awards = [];

        foreach ($subjectIds as $subjectId) {
            $candidates = $students
                ->filter(fn (array $student) => array_key_exists($subjectId, $student['subject_scores']));
            $highestScore = $candidates->max(fn (array $student) => $student['subject_scores'][$subjectId]);

            if ($highestScore < 40) {
                continue;
            }

            $winners = $candidates
                ->filter(fn (array $student) => (float) $student['subject_scores'][$subjectId] === (float) $highestScore)
                ->sortBy(fn (array $student) => $student['student']->user?->name)
                ->values();
            $first = $winners->first();
            $subject = $first['subjects']->get($subjectId);

            if (!$subject) {
                continue;
            }

            $awards[] = [
                'subject' => ['name' => $subject->name],
                'student' => $this->formatStudentSummary($first['student'], $first['class_name']),
                'joint_winners' => $winners
                    ->skip(1)
                    ->map(fn (array $student) => $this->formatStudentSummary(
                        $student['student'],
                        $student['class_name']
                    ))
                    ->all(),
                'score' => round((float) $highestScore, 2),
            ];
        }

        return collect($awards)->sortBy('subject.name')->values()->all();
    }

    protected function calculateStdDev(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }
        
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $count;
        
        return sqrt($variance);
    }

    public function render()
    {
        return view('livewire.result.awards-manager', [
            'isRestrictedTeacherResultViewer' => $this->isRestrictedTeacherResultViewer(),
            'topPerformersData' => $this->topPerformersData(),
        ]);
    }

    protected function topPerformersData(): Collection
    {
        if ($this->topPerformers instanceof Collection) {
            return $this->topPerformers;
        }

        return collect(is_array($this->topPerformers) ? $this->topPerformers : []);
    }
}
