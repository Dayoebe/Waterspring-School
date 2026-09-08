<?php

namespace App\Livewire\Dashboard;

use App\Models\AcademicYear;
use App\Models\ClassExamParticipation;
use App\Models\MyClass;
use App\Models\Result;
use App\Models\Semester;
use App\Support\ClassExamActivity;
use App\Support\ResultPublicationStatus;
use App\Support\StudentPeriodActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResultPublicationManager extends Component
{
    #[Locked]
    public bool $showExamParticipation = false;

    public ?int $academicYearId = null;
    public ?int $semesterId = null;
    public bool $termPublished = false;
    public bool $annualPublished = false;
    public string $statusMessage = '';
    public array $termReadiness = [];
    public array $annualReadiness = [];
    public ?int $examClassId = null;
    public string $examParticipationStatus = 'external';
    public string $externalExaminationName = '';
    public string $examParticipationReason = '';
    public string $examParticipationNotes = '';
    public $examParticipations;

    public function mount(bool $showExamParticipation = false): void
    {
        $this->authorizeSuperAdmin();
        $this->showExamParticipation = $showExamParticipation;

        $school = auth()->user()?->school;
        $this->academicYearId = $school?->academic_year_id;
        $this->semesterId = $school?->semester_id;
        $this->refreshStatus();
    }

    public function updatedAcademicYearId(): void
    {
        $firstSemesterId = Semester::query()
            ->where('school_id', auth()->user()->school_id)
            ->where('academic_year_id', $this->academicYearId)
            ->orderBy('id')
            ->value('id');

        $this->semesterId = $firstSemesterId ? (int) $firstSemesterId : null;
        $this->refreshStatus();
    }

    public function updatedSemesterId(): void
    {
        $this->refreshStatus();
    }

    public function toggleTermPublication(): void
    {
        $this->authorizeSuperAdmin();
        $this->validateSelection(true);

        $publish = !$this->termPublished;
        if ($publish && !($this->termReadiness['ready'] ?? false)) {
            throw ValidationException::withMessages([
                'publication' => $this->readinessFailureMessage($this->termReadiness, 'term'),
            ]);
        }

        ResultPublicationStatus::setTermPublished(
            auth()->user()->school_id,
            $this->academicYearId,
            $this->semesterId,
            $publish,
            auth()->id()
        );

        $this->statusMessage = $publish
            ? 'Term result published. Parents and students can now view it.'
            : 'Term result hidden. Parents and students can no longer view it.';
        $this->refreshStatus(false);
    }

    public function toggleAnnualPublication(): void
    {
        $this->authorizeSuperAdmin();
        $this->validateSelection(false);

        $publish = !$this->annualPublished;
        if ($publish && !($this->annualReadiness['ready'] ?? false)) {
            throw ValidationException::withMessages([
                'publication' => $this->readinessFailureMessage($this->annualReadiness, 'annual'),
            ]);
        }

        ResultPublicationStatus::setAnnualPublished(
            auth()->user()->school_id,
            $this->academicYearId,
            $publish,
            auth()->id()
        );

        $this->statusMessage = $publish
            ? 'Annual result published. Parents and students can now view it.'
            : 'Annual result hidden. Parents and students can no longer view it.';
        $this->refreshStatus(false);
    }

    protected function refreshStatus(bool $clearMessage = true): void
    {
        if ($clearMessage) {
            $this->statusMessage = '';
        }

        $schoolId = auth()->user()?->school_id;
        $this->termPublished = (bool) ($schoolId && $this->academicYearId && $this->semesterId)
            && ResultPublicationStatus::termIsPublished($schoolId, $this->academicYearId, $this->semesterId);
        $this->annualPublished = (bool) ($schoolId && $this->academicYearId)
            && ResultPublicationStatus::annualIsPublished($schoolId, $this->academicYearId);
        $this->termReadiness = ($schoolId && $this->academicYearId && $this->semesterId)
            ? $this->calculateReadiness(collect([(int) $this->semesterId]))
            : [];

        $annualSemesters = ($schoolId && $this->academicYearId)
            ? Semester::query()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $this->academicYearId)
                ->get()
            : collect();
        $hasThreeTerms = $this->hasCompleteAnnualTerms($annualSemesters);
        $this->annualReadiness = $hasThreeTerms
            ? $this->calculateReadiness($annualSemesters->pluck('id')->map(fn ($id) => (int) $id))
            : [
                'ready' => false,
                'expected' => 0,
                'completed' => 0,
                'missing' => 0,
                'students' => 0,
                'classes' => 0,
                'reason' => 'Annual publication requires exactly First, Second, and Third Term.',
            ];
        $this->loadExamParticipations();
    }

    protected function validateSelection(bool $requiresSemester): void
    {
        $schoolId = auth()->user()->school_id;

        $yearExists = AcademicYear::query()
            ->where('school_id', $schoolId)
            ->whereKey($this->academicYearId)
            ->exists();

        abort_unless($yearExists, 422, 'Select a valid academic year.');

        if ($requiresSemester) {
            $semesterExists = Semester::query()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $this->academicYearId)
                ->whereKey($this->semesterId)
                ->exists();

            abort_unless($semesterExists, 422, 'Select a valid term.');
        }
    }

    protected function authorizeSuperAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super-admin', 'super_admin']) === true,
            403
        );
    }

    public function saveExamParticipation(): void
    {
        $this->authorizeSuperAdmin();
        $this->validateSelection(true);

        $validated = $this->validate([
            'examClassId' => ['required', 'integer'],
            'examParticipationStatus' => ['required', 'in:external,exempt,postponed,cancelled'],
            'externalExaminationName' => ['nullable', 'string', 'max:100'],
            'examParticipationReason' => ['required', 'string', 'max:255'],
            'examParticipationNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $classExists = MyClass::query()
            ->whereKey($validated['examClassId'])
            ->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))
            ->exists();
        abort_unless($classExists, 422, 'Select a valid class.');

        if (
            $validated['examParticipationStatus'] === 'external'
            && trim($validated['externalExaminationName'] ?? '') === ''
        ) {
            $this->addError('externalExaminationName', 'Enter the external examination name, such as WAEC.');
            return;
        }

        ClassExamParticipation::query()->updateOrCreate(
            [
                'school_id' => auth()->user()->school_id,
                'academic_year_id' => $this->academicYearId,
                'semester_id' => $this->semesterId,
                'my_class_id' => $validated['examClassId'],
            ],
            [
                'status' => $validated['examParticipationStatus'],
                'examination_name' => trim($validated['externalExaminationName'] ?? '') ?: null,
                'reason' => trim($validated['examParticipationReason']),
                'notes' => trim($validated['examParticipationNotes'] ?? '') ?: null,
                'recorded_by' => auth()->id(),
            ]
        );

        $this->reset([
            'examClassId',
            'externalExaminationName',
            'examParticipationReason',
            'examParticipationNotes',
        ]);
        $this->statusMessage = 'Class examination participation updated. Publication readiness has been recalculated.';
        $this->refreshStatus(false);
    }

    public function restoreInternalExamParticipation(int $participationId): void
    {
        $this->authorizeSuperAdmin();

        ClassExamParticipation::query()
            ->where('school_id', auth()->user()->school_id)
            ->whereKey($participationId)
            ->delete();

        $this->statusMessage = 'The class is participating in internal results again.';
        $this->refreshStatus(false);
    }

    protected function loadExamParticipations(): void
    {
        $this->examParticipations = ($this->academicYearId && $this->semesterId)
            ? ClassExamParticipation::query()
                ->where('school_id', auth()->user()->school_id)
                ->where('academic_year_id', $this->academicYearId)
                ->where('semester_id', $this->semesterId)
                ->with(['myClass', 'recorder'])
                ->orderBy('my_class_id')
                ->get()
            : collect();
    }

    protected function calculateReadiness(Collection $semesterIds): array
    {
        $schoolId = (int) auth()->user()->school_id;
        $academicYearId = (int) $this->academicYearId;

        $placements = DB::table('academic_year_student_record as placement')
            ->join('student_records', 'student_records.id', '=', 'placement.student_record_id')
            ->join('users', 'users.id', '=', 'student_records.user_id')
            ->join('my_classes', 'my_classes.id', '=', 'placement.my_class_id')
            ->join('class_groups', 'class_groups.id', '=', 'my_classes.class_group_id')
            ->where('placement.academic_year_id', $academicYearId)
            ->where('users.school_id', $schoolId)
            ->where('class_groups.school_id', $schoolId)
            ->whereNull('users.deleted_at')
            ->get([
                'placement.student_record_id',
                'placement.my_class_id',
                'users.id as user_id',
                'users.name as student_name',
                'my_classes.name as class_name',
            ]);

        $expectedKeys = collect();
        $excludedIds = collect();
        $annualExcludedIds = $semesterIds->count() > 1
            ? StudentPeriodActivity::excludedStudentRecordIds($schoolId, $academicYearId)
            : null;
        foreach ($semesterIds as $semesterId) {
            $termExcludedIds = $annualExcludedIds
                ?? StudentPeriodActivity::excludedStudentRecordIds(
                    $schoolId,
                    $academicYearId,
                    (int) $semesterId
                );
            $excludedIds = $excludedIds->merge($termExcludedIds);
            $termExcludedClassIds = ClassExamActivity::excludedClassIds(
                $schoolId,
                $academicYearId,
                $semesterIds->count() > 1 ? null : (int) $semesterId
            );
            $termPlacements = $placements
                ->whereNotIn('student_record_id', $termExcludedIds)
                ->whereNotIn('my_class_id', $termExcludedClassIds);
            $termPairs = $this->expectedPairsForPlacements($termPlacements, $schoolId);
            $expectedKeys = $expectedKeys->concat(
                $termPairs->map(
                    fn ($pair) => $semesterId . ':' . $pair->student_record_id . ':' . $pair->subject_id
                )
            );
        }

        $expectedKeys = $expectedKeys->unique()->values();
        $expected = $expectedKeys->count();

        if ($expected === 0) {
            return [
                'ready' => false,
                'expected' => 0,
                'completed' => 0,
                'missing' => 0,
                'students' => $placements->pluck('student_record_id')->unique()->count(),
                'classes' => $placements->pluck('my_class_id')->unique()->count(),
                'excluded_students' => $excludedIds->unique()->count(),
                'missing_students' => [],
                'reason' => 'No enrolled student-subject result entries were found for this period.',
            ];
        }

        $resultKeys = Result::query()
            ->where('academic_year_id', $academicYearId)
            ->whereIn('semester_id', $semesterIds)
            ->whereIn('student_record_id', $placements->pluck('student_record_id'))
            ->get(['semester_id', 'student_record_id', 'subject_id'])
            ->map(fn (Result $result) => $result->semester_id . ':' . $result->student_record_id . ':' . $result->subject_id)
            ->unique();
        $completed = $expectedKeys->intersect($resultKeys)->count();
        $missingKeys = $expectedKeys->diff($resultKeys)->values();
        $subjectNames = DB::table('subjects')
            ->whereIn(
                'id',
                $missingKeys->map(fn ($key) => (int) explode(':', $key)[2])->unique()
            )
            ->pluck('name', 'id');
        $placementByStudent = $placements->keyBy('student_record_id');
        $missingStudents = $missingKeys
            ->groupBy(fn ($key) => (int) explode(':', $key)[1])
            ->map(function (Collection $keys, int $studentRecordId) use (
                $expectedKeys,
                $resultKeys,
                $placementByStudent,
                $subjectNames
            ) {
                $placement = $placementByStudent->get($studentRecordId);
                $studentExpected = $expectedKeys->filter(
                    fn ($key) => (int) explode(':', $key)[1] === $studentRecordId
                );
                $studentCompleted = $studentExpected->intersect($resultKeys)->count();
                $missingSubjects = $keys
                    ->map(fn ($key) => $subjectNames[(int) explode(':', $key)[2]] ?? 'Unknown subject')
                    ->unique()
                    ->sort()
                    ->values();

                return [
                    'student_record_id' => $studentRecordId,
                    'user_id' => (int) ($placement?->user_id ?? 0),
                    'name' => $placement?->student_name ?? "Student {$studentRecordId}",
                    'class_name' => $placement?->class_name ?? 'Unknown class',
                    'expected' => $studentExpected->count(),
                    'completed' => $studentCompleted,
                    'missing' => $keys->count(),
                    'has_no_results' => $studentCompleted === 0,
                    'missing_subjects' => $missingSubjects->all(),
                ];
            })
            ->sortBy([
                ['has_no_results', 'desc'],
                ['missing', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'ready' => $completed === $expected,
            'expected' => $expected,
            'completed' => $completed,
            'missing' => $expected - $completed,
            'students' => $placements->pluck('student_record_id')->unique()->count(),
            'classes' => $placements->pluck('my_class_id')->unique()->count(),
            'excluded_students' => $excludedIds->unique()->count(),
            'excluded_classes' => ClassExamActivity::excludedClassIds(
                $schoolId,
                $academicYearId,
                $semesterIds->count() > 1 ? null : (int) $semesterIds->first()
            )->count(),
            'missing_students' => $missingStudents,
            'reason' => null,
        ];
    }

    protected function expectedPairsForPlacements(Collection $placements, int $schoolId): Collection
    {
        $expectedPairs = collect();

        foreach ($placements->groupBy('my_class_id') as $classId => $classPlacements) {
            $studentIds = $classPlacements->pluck('student_record_id');
            $classSubjects = DB::table('class_subject')
                ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
                ->where('class_subject.my_class_id', $classId)
                ->where('class_subject.school_id', $schoolId)
                ->where('subjects.school_id', $schoolId)
                ->where('subjects.is_legacy', false)
                ->whereNull('subjects.deleted_at')
                ->get(['class_subject.subject_id', 'subjects.is_general']);
            $classSubjectIds = $classSubjects->pluck('subject_id');
            $generalSubjectIds = $classSubjects->where('is_general', true)->pluck('subject_id');
            $generalPairs = $studentIds->flatMap(
                fn ($studentId) => $generalSubjectIds->map(
                    fn ($subjectId) => (object) [
                        'student_record_id' => $studentId,
                        'subject_id' => $subjectId,
                    ]
                )
            );
            $electivePairs = DB::table('student_subject')
                ->whereIn('student_record_id', $studentIds)
                ->whereIn('subject_id', $classSubjectIds)
                ->get(['student_record_id', 'subject_id']);
            $expectedPairs = $expectedPairs->concat($generalPairs)->concat($electivePairs);
        }

        return $expectedPairs
            ->unique(fn ($pair) => $pair->student_record_id . ':' . $pair->subject_id)
            ->values();
    }

    protected function hasCompleteAnnualTerms(Collection $semesters): bool
    {
        $names = $semesters
            ->map(fn (Semester $semester) => Str::lower(trim($semester->name)))
            ->sort()
            ->values();

        return $names->all() === ['first term', 'second term', 'third term'];
    }

    protected function readinessFailureMessage(array $readiness, string $period): string
    {
        if (!empty($readiness['reason'])) {
            return $readiness['reason'];
        }

        return sprintf(
            'Cannot publish this %s result: %d of %d required result entries are still missing.',
            $period,
            (int) ($readiness['missing'] ?? 0),
            (int) ($readiness['expected'] ?? 0)
        );
    }

    public function render()
    {
        $schoolId = auth()->user()->school_id;

        return view('livewire.dashboard.result-publication-manager', [
            'academicYears' => AcademicYear::query()
                ->where('school_id', $schoolId)
                ->orderByDesc('start_year')
                ->get(),
            'semesters' => $this->academicYearId
                ? Semester::query()
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $this->academicYearId)
                    ->orderBy('id')
                    ->get()
                : collect(),
            'classes' => MyClass::query()
                ->whereHas('classGroup', fn ($query) => $query->where('school_id', $schoolId))
                ->instructional()
                ->orderBy('name')
                ->get(),
        ]);
    }
}
