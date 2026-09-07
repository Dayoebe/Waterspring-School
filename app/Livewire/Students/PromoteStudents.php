<?php

namespace App\Livewire\Students;

use App\Models\MyClass;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Models\Promotion;
use App\Models\User;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PromoteStudents extends Component
{
    use WithPagination;

    public $currentView = 'promote';

    public $classes;
    public $academicYears;
    public $oldClass;
    public $oldSections = [];
    public $oldSection = 'all';
    public $newClass;
    public $newSections = [];
    public $newSection = 'none';
    public $fromAcademicYear;
    public $toAcademicYear;
    public $students = [];
    public $selectedStudents = [];
    public $selectAll = false;

    public $promotions = [];
    public $selectedPromotion = null;
    public $promotionStudents = [];

    // Search & Filter
    public $searchStudent = '';
    public $filterStatus = 'all';

    protected $rules = [
        'oldClass' => 'required|exists:my_classes,id',
        'newClass' => 'required|exists:my_classes,id',
        'fromAcademicYear' => 'required|exists:academic_years,id',
        'toAcademicYear' => 'required|exists:academic_years,id',
        'selectedStudents' => 'required|array|min:1',
    ];

    public function mount()
    {
        $this->classes = MyClass::whereHas('classGroup', function ($query) {
            $query->where('school_id', auth()->user()->school_id);
        })->with('sections')->orderBy('name')->get();

        $this->academicYears = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('start_year', 'desc')
            ->get();

        if ($this->classes->isNotEmpty()) {
            $this->oldClass = $this->classes->first()->id;
            $this->newClass = $this->classes->first()->id;
            $this->loadOldSections();
            $this->loadNewSections();
        }

        $currentAcademicYear = auth()->user()->school->academicYear;
        if ($currentAcademicYear) {
            $this->fromAcademicYear = $currentAcademicYear->id;
            $nextYear = $this->academicYears->where('start_year', '>', $currentAcademicYear->start_year)->first();
            $this->toAcademicYear = $nextYear ? $nextYear->id : $currentAcademicYear->id;
        }

        $this->loadPromotions();
    }

    public function switchView($view)
    {
        $this->currentView = $view;
        if ($view === 'history') {
            $this->loadPromotions();
        }
    }

    public function updatedOldClass()
    {
        $this->loadOldSections();
        $this->oldSection = 'all';
        $this->students = [];
        $this->selectedStudents = [];
        $this->selectAll = false;
    }

    public function updatedNewClass()
    {
        $this->loadNewSections();
        $this->newSection = 'none';
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedStudents = collect($this->getFilteredStudents())
                ->where('already_promoted', false)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedStudents = [];
        }
    }

    private function loadOldSections()
    {
        $class = $this->classes->firstWhere('id', $this->oldClass);
        $this->oldSections = $class ? $class->sections : collect();
    }

    private function loadNewSections()
    {
        $class = $this->classes->firstWhere('id', $this->newClass);
        $this->newSections = $class ? $class->sections : collect();
    }

    private function studentUsersQuery()
    {
        return User::query()
            ->where('school_id', auth()->user()->school_id)
            ->whereNull('deleted_at')
            ->with([
                'studentRecord' => fn ($query) => $query->withoutGlobalScope('notGraduated'),
            ]);
    }

    public function loadStudents()
    {
        $this->students = [];
        $this->selectedStudents = [];
        $this->selectAll = false;

        if (!$this->oldClass || !$this->fromAcademicYear) {
            session()->flash('info', 'Please select a class and academic year.');
            return;
        }

        if ($this->isAlumniClass($this->newClass)) {
            session()->flash('error', 'Use Graduate Students to move students into Alumni. Promotions cannot target Alumni.');
            return;
        }

        if (!$this->classBelongsToCurrentSchool($this->oldClass) || !$this->classBelongsToCurrentSchool($this->newClass)) {
            session()->flash('error', 'Selected class is not in your current school.');
            return;
        }

        if ($this->oldSection && $this->oldSection !== 'all' && !$this->sectionBelongsToClassInCurrentSchool($this->oldSection, $this->oldClass)) {
            session()->flash('error', 'Selected source section is not valid for the selected class.');
            return;
        }

        if ($this->newSection && $this->newSection !== 'none' && !$this->sectionBelongsToClassInCurrentSchool($this->newSection, $this->newClass)) {
            session()->flash('error', 'Selected target section is not valid for the selected class.');
            return;
        }

        $fromYear = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->find($this->fromAcademicYear);
        $toYear = $this->toAcademicYear
            ? AcademicYear::query()
                ->where('school_id', auth()->user()->school_id)
                ->find($this->toAcademicYear)
            : null;

        if (!$fromYear) {
            session()->flash('error', 'Invalid academic year selected.');
            return;
        }

        $query = DB::table('academic_year_student_record')
            ->where('academic_year_id', $fromYear->id)
            ->where('my_class_id', $this->oldClass);

        if ($this->oldSection && $this->oldSection !== 'all') {
            $query->where('section_id', (int) $this->oldSection);
        }

        $pivotRecords = $query->get();
        $studentRecordIds = $pivotRecords->pluck('student_record_id');

        if ($studentRecordIds->isEmpty()) {
            session()->flash('info', 'No students found in this class for the selected academic year.');
            return;
        }

        $alreadyInToYearAndClass = collect();
        if ($toYear) {
            $alreadyInToYearAndClass = DB::table('academic_year_student_record')
                ->where('academic_year_id', $toYear->id)
                ->where('my_class_id', $this->newClass)
                ->whereIn('student_record_id', $studentRecordIds)
                ->pluck('student_record_id');
        }

        $classIds = $pivotRecords->pluck('my_class_id')->unique();
        $sectionIds = $pivotRecords->pluck('section_id')->filter()->unique();

        $classes = MyClass::whereIn('id', $classIds)
            ->whereHas('classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->get()
            ->keyBy('id');
        $sections = Section::whereIn('id', $sectionIds)
            ->whereHas('myClass.classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->get()
            ->keyBy('id');

        $toYearClassInfo = collect();
        if ($toYear && $alreadyInToYearAndClass->isNotEmpty()) {
            $toYearRecords = DB::table('academic_year_student_record')
                ->where('academic_year_id', $toYear->id)
                ->whereIn('student_record_id', $alreadyInToYearAndClass)
                ->get()
                ->keyBy('student_record_id');

            $toYearClassIds = $toYearRecords->pluck('my_class_id')->unique();
            $toYearSectionIds = $toYearRecords->pluck('section_id')->filter()->unique();

            $toYearClasses = MyClass::whereIn('id', $toYearClassIds)
                ->whereHas('classGroup', function ($query) {
                    $query->where('school_id', auth()->user()->school_id);
                })
                ->get()
                ->keyBy('id');
            $toYearSections = Section::whereIn('id', $toYearSectionIds)
                ->whereHas('myClass.classGroup', function ($query) {
                    $query->where('school_id', auth()->user()->school_id);
                })
                ->get()
                ->keyBy('id');

            $toYearClassInfo = [
                'records' => $toYearRecords,
                'classes' => $toYearClasses,
                'sections' => $toYearSections,
            ];
        }

        $users = $this->studentUsersQuery()
            ->whereHas('studentRecord', function ($query) use ($studentRecordIds) {
                $query->whereIn('id', $studentRecordIds)
                    ->where('is_graduated', false);
            })
            ->get();

        $pivotMap = $pivotRecords->keyBy('student_record_id');

        $this->students = $users->map(function ($user) use ($pivotMap, $classes, $sections, $toYear, $alreadyInToYearAndClass, $toYearClassInfo) {
            if (!$user->studentRecord) return null;

            $pivot = $pivotMap->get($user->studentRecord->id);
            if (!$pivot) return null;

            $originalClass = $classes->get($pivot->my_class_id);
            $originalSection = $pivot->section_id ? $sections->get($pivot->section_id) : null;

            $alreadyPromoted = false;
            $promotedClass = null;
            $promotedSection = null;

            if ($toYear && $alreadyInToYearAndClass->contains($user->studentRecord->id)) {
                $alreadyPromoted = true;
                if (isset($toYearClassInfo['records'])) {
                    $toYearRecord = $toYearClassInfo['records']->get($user->studentRecord->id);
                    if ($toYearRecord) {
                        $promotedClass = $toYearClassInfo['classes']->get($toYearRecord->my_class_id);
                        $promotedSection = $toYearRecord->section_id ?
                            $toYearClassInfo['sections']->get($toYearRecord->section_id) : null;
                    }
                }
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'admission_number' => $user->studentRecord->admission_number ?? '—',
                'original_class' => $originalClass?->name ?? 'Unknown',
                'original_section' => $originalSection?->name,
                'promoted_class' => $promotedClass?->name,
                'promoted_section' => $promotedSection?->name,
                'already_promoted' => $alreadyPromoted,
            ];
        })
            ->filter()
            ->sortBy('name')
            ->values()
            ->toArray();

        $notPromotedCount = collect($this->students)->where('already_promoted', false)->count();
        $promotedCount = collect($this->students)->where('already_promoted', true)->count();

        if (empty($this->students)) {
            session()->flash('info', 'No students found.');
        } else {
            $message = count($this->students) . " student(s) found";
            if ($promotedCount > 0) {
                $message .= " ({$notPromotedCount} ready, {$promotedCount} already in {$toYear->name})";
            }
            session()->flash('success', $message);
        }
    }

    private function getFilteredStudents()
    {
        $filtered = collect($this->students);

        if ($this->searchStudent) {
            $filtered = $filtered->filter(function($student) {
                return stripos($student['name'], $this->searchStudent) !== false ||
                       stripos($student['email'] ?? '', $this->searchStudent) !== false ||
                       stripos($student['admission_number'], $this->searchStudent) !== false;
            });
        }

        if ($this->filterStatus === 'ready') {
            $filtered = $filtered->where('already_promoted', false);
        } elseif ($this->filterStatus === 'promoted') {
            $filtered = $filtered->where('already_promoted', true);
        }

        return $filtered->values()->toArray();
    }

    public function promoteStudents()
    {
        abort_unless(
            auth()->user()?->can('promote student')
                || auth()->user()?->hasAnyRole(['super-admin', 'super_admin']),
            403
        );

        $this->validate();

        if (empty($this->selectedStudents)) {
            session()->flash('error', 'Please select at least one student to move.');
            return;
        }

        if ($this->isAlumniClass($this->newClass)) {
            session()->flash('error', 'Use Graduate Students to move students into Alumni. Promotions cannot target Alumni.');
            return;
        }

        if (!$this->classBelongsToCurrentSchool($this->oldClass) || !$this->classBelongsToCurrentSchool($this->newClass)) {
            session()->flash('error', 'Selected class is not in your current school.');
            return;
        }

        if ($this->oldSection && $this->oldSection !== 'all' && !$this->sectionBelongsToClassInCurrentSchool($this->oldSection, $this->oldClass)) {
            session()->flash('error', 'Selected source section is not valid for the selected class.');
            return;
        }

        if ($this->newSection && $this->newSection !== 'none' && !$this->sectionBelongsToClassInCurrentSchool($this->newSection, $this->newClass)) {
            session()->flash('error', 'Selected target section is not valid for the selected class.');
            return;
        }

        $fromYear = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->find($this->fromAcademicYear);
        $toYear = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->find($this->toAcademicYear);

        if (!$fromYear || !$toYear) {
            session()->flash('error', 'Invalid academic year selected.');
            return;
        }

        if ($toYear->start_year <= $fromYear->start_year) {
            session()->flash('error', 'Student promotion or demotion must target a later academic year.');
            return;
        }

        $successCount = 0;
        $promotedStudents = [];
        $studentSnapshots = [];
        $allowedStudentIds = collect($this->students)
            ->where('already_promoted', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
        $selectedStudents = array_values(array_intersect(array_map('intval', $this->selectedStudents), $allowedStudentIds));

        if (empty($selectedStudents)) {
            session()->flash('error', 'Selected students are not valid for this promotion context.');
            return;
        }

        DB::transaction(function () use (
            $fromYear,
            $toYear,
            $selectedStudents,
            &$successCount,
            &$promotedStudents,
            &$studentSnapshots
        ) {
            $students = $this->studentUsersQuery()
                ->whereIn('id', $selectedStudents)
                ->get()
                ->keyBy('id');

            foreach ($selectedStudents as $userId) {
                $student = $students->get($userId);
                if (!$student || !$student->studentRecord) continue;

                $studentRecord = $student->studentRecord;
                $sourceRecord = DB::table('academic_year_student_record')
                    ->where('student_record_id', $studentRecord->id)
                    ->where('academic_year_id', $fromYear->id)
                    ->lockForUpdate()
                    ->first();

                if (!$sourceRecord || (int) $sourceRecord->my_class_id !== (int) $this->oldClass) {
                    continue;
                }

                $existingInToYear = DB::table('academic_year_student_record')
                    ->where('student_record_id', $studentRecord->id)
                    ->where('academic_year_id', $toYear->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingInToYear && $existingInToYear->my_class_id == $this->newClass) {
                    continue;
                }

                $studentSnapshots[(string) $student->id] = [
                    'student_record_id' => (int) $studentRecord->id,
                    'source_class_id' => (int) $sourceRecord->my_class_id,
                    'source_section_id' => $sourceRecord->section_id ? (int) $sourceRecord->section_id : null,
                    'target_existed' => $existingInToYear !== null,
                    'previous_target_class_id' => $existingInToYear?->my_class_id
                        ? (int) $existingInToYear->my_class_id
                        : null,
                    'previous_target_section_id' => $existingInToYear?->section_id
                        ? (int) $existingInToYear->section_id
                        : null,
                ];

                $pivotData = [
                    'my_class_id' => $this->newClass,
                    'section_id' => ($this->newSection && $this->newSection !== 'none') ? $this->newSection : null,
                ];

                if ($existingInToYear) {
                    DB::table('academic_year_student_record')
                        ->where('id', $existingInToYear->id)
                        ->update(array_merge($pivotData, ['updated_at' => now()]));
                } else {
                    $studentRecord->academicYears()->syncWithoutDetaching([
                        $toYear->id => $pivotData
                    ]);
                }

                if ($toYear->id == auth()->user()->school->academic_year_id) {
                    $studentRecord->update([
                        'my_class_id' => $this->newClass,
                        'section_id' => $pivotData['section_id'],
                    ]);
                }

                $promotedStudents[] = $student->id;
                $successCount++;
            }

            if (empty($promotedStudents)) {
                throw new \Exception('No students were successfully promoted.');
            }

            Promotion::create([
                'old_class_id' => $this->oldClass,
                'new_class_id' => $this->newClass,
                'old_section_id' => ($this->oldSection && $this->oldSection !== 'all') ? $this->oldSection : null,
                'new_section_id' => ($this->newSection && $this->newSection !== 'none') ? $this->newSection : null,
                'students' => $promotedStudents,
                'student_snapshots' => $studentSnapshots,
                'from_academic_year_id' => $fromYear->id,
                'movement_type' => $this->movementType($this->oldClass, $this->newClass),
                'academic_year_id' => $toYear->id,
                'school_id' => auth()->user()->school_id,
            ]);
        });

        $this->students = [];
        $this->selectedStudents = [];
        $this->selectAll = false;
        $this->loadPromotions();

        $movementLabel = str_replace('_', ' ', $this->movementType($this->oldClass, $this->newClass));
        session()->flash('success', "{$successCount} student(s) moved successfully ({$movementLabel}).");
    }

    public function loadPromotions()
    {
        $this->promotions = Promotion::query()
            ->where('school_id', auth()->user()->school_id)
            ->with(['oldClass', 'newClass', 'oldSection', 'newSection', 'fromAcademicYear', 'academicYear'])
            ->latest()
            ->get();
    }

    public function resetPromotion($promotionId)
    {
        abort_unless(
            auth()->user()?->can('reset promotion')
                || auth()->user()?->hasAnyRole(['super-admin', 'super_admin']),
            403
        );

        $promotion = Promotion::query()
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($promotionId);

        $currentAcademicYearId = auth()->user()->school->academic_year_id;
        $resetCount = 0;
        $skippedCount = 0;
        $resetUserIds = [];

        DB::transaction(function () use (
            $promotion,
            $currentAcademicYearId,
            &$resetCount,
            &$skippedCount,
            &$resetUserIds
        ) {
            $studentIds = is_array($promotion->students) ? $promotion->students : json_decode($promotion->students, true);
            $snapshots = is_array($promotion->student_snapshots) ? $promotion->student_snapshots : [];

            if (!is_array($studentIds)) {
                throw new \Exception('Invalid promotion data.');
            }

            $students = $this->studentUsersQuery()
                ->whereIn('id', $studentIds)
                ->get()
                ->keyBy('id');

            foreach ($studentIds as $userId) {
                $student = $students->get($userId);
                if (!$student || !$student->studentRecord) {
                    $skippedCount++;
                    continue;
                }

                $studentRecord = $student->studentRecord;
                $snapshot = $snapshots[(string) $userId] ?? null;
                if (!is_array($snapshot)) {
                    $skippedCount++;
                    continue;
                }

                $currentTarget = DB::table('academic_year_student_record')
                    ->where('student_record_id', $studentRecord->id)
                    ->where('academic_year_id', $promotion->academic_year_id)
                    ->first();

                $expectedSectionId = $promotion->new_section_id ? (int) $promotion->new_section_id : null;
                if (
                    !$currentTarget
                    || (int) $currentTarget->my_class_id !== (int) $promotion->new_class_id
                    || ($currentTarget->section_id ? (int) $currentTarget->section_id : null) !== $expectedSectionId
                ) {
                    $skippedCount++;
                    continue;
                }

                $targetExisted = (bool) ($snapshot['target_existed'] ?? false);
                $restoreClassId = $targetExisted
                    ? ($snapshot['previous_target_class_id'] ?? null)
                    : ($snapshot['source_class_id'] ?? null);
                $restoreSectionId = $targetExisted
                    ? ($snapshot['previous_target_section_id'] ?? null)
                    : ($snapshot['source_section_id'] ?? null);

                if (!$restoreClassId || !$this->classBelongsToCurrentSchool($restoreClassId)) {
                    $skippedCount++;
                    continue;
                }

                if (
                    $restoreSectionId
                    && !$this->sectionBelongsToClassInCurrentSchool($restoreSectionId, $restoreClassId)
                ) {
                    $restoreSectionId = null;
                }

                if ($targetExisted) {
                    DB::table('academic_year_student_record')
                        ->where('id', $currentTarget->id)
                        ->update([
                            'my_class_id' => $restoreClassId,
                            'section_id' => $restoreSectionId,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('academic_year_student_record')
                        ->where('id', $currentTarget->id)
                        ->delete();
                }

                if ($promotion->academic_year_id == $currentAcademicYearId) {
                    $studentRecord->update([
                        'my_class_id' => $restoreClassId,
                        'section_id' => $restoreSectionId,
                    ]);
                }

                $resetCount++;
                $resetUserIds[] = (int) $userId;
            }

            if ($resetCount > 0 && $skippedCount === 0) {
                $promotion->delete();
            } elseif ($resetCount > 0) {
                $remainingStudentIds = collect($studentIds)
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => in_array($id, $resetUserIds, true))
                    ->values()
                    ->all();
                $remainingSnapshots = collect($snapshots)
                    ->reject(fn ($snapshot, $userId) => in_array((int) $userId, $resetUserIds, true))
                    ->all();

                $promotion->update([
                    'students' => $remainingStudentIds,
                    'student_snapshots' => $remainingSnapshots,
                ]);
            }
        });

        $this->loadPromotions();
        $this->loadStudents();

        if ($resetCount === 0) {
            session()->flash(
                'error',
                'Nothing was changed. These students have moved again, or the original class is no longer available.'
            );
            return;
        }

        $message = "{$resetCount} student movement(s) reversed successfully.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} later movement(s) were protected and left unchanged.";
        }
        session()->flash('success', $message);
    }

    public function viewPromotion($promotionId)
    {
        abort_unless(
            auth()->user()?->can('read promotion')
                || auth()->user()?->can('promote student')
                || auth()->user()?->hasAnyRole(['super-admin', 'super_admin']),
            403
        );

        $this->selectedPromotion = Promotion::query()
            ->where('school_id', auth()->user()->school_id)
            ->with(['oldClass', 'newClass', 'oldSection', 'newSection', 'fromAcademicYear', 'academicYear'])
            ->findOrFail($promotionId);

        $studentIds = is_array($this->selectedPromotion->students)
            ? $this->selectedPromotion->students
            : (json_decode($this->selectedPromotion->students, true) ?: []);

        $this->promotionStudents = $this->studentUsersQuery()
            ->whereIn('id', $studentIds)
            ->get();
        $this->currentView = 'view';
    }

    protected function classBelongsToCurrentSchool($classId): bool
    {
        if (!$classId) {
            return false;
        }

        return MyClass::where('id', $classId)
            ->whereHas('classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->exists();
    }

    protected function sectionBelongsToClassInCurrentSchool($sectionId, $classId): bool
    {
        if (!$sectionId || !$classId) {
            return false;
        }

        return Section::where('id', $sectionId)
            ->where('my_class_id', $classId)
            ->whereHas('myClass.classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->exists();
    }

    protected function isAlumniClass($classId): bool
    {
        if (!$classId) {
            return false;
        }

        return MyClass::query()
            ->where('id', $classId)
            ->whereHas('classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->where(function ($query) {
                $query->where('name', 'Alumni')
                    ->orWhere('name', 'like', 'Alumni%');
            })
            ->exists();
    }

    protected function movementType($oldClassId, $newClassId): string
    {
        $classes = MyClass::withTrashed()
            ->whereIn('id', [$oldClassId, $newClassId])
            ->pluck('name', 'id');
        $oldRank = $this->classRank($classes[$oldClassId] ?? null);
        $newRank = $this->classRank($classes[$newClassId] ?? null);

        if ($oldRank !== null && $newRank !== null) {
            return $newRank > $oldRank ? 'promotion' : ($newRank < $oldRank ? 'demotion' : 'repeat');
        }

        return 'movement';
    }

    protected function classRank(?string $name): ?int
    {
        $normalized = strtoupper(str_replace(' ', '', (string) $name));

        return [
            'JSS1' => 1,
            'JSS2' => 2,
            'JSS3' => 3,
            'SS1' => 4,
            'SSS1' => 4,
            'SS2' => 5,
            'SSS2' => 5,
            'SS3' => 6,
            'SSS3' => 6,
        ][$normalized] ?? null;
    }

    public function backToHistory()
    {
        $this->selectedPromotion = null;
        $this->promotionStudents = [];
        $this->currentView = 'history';
    }

    public function render()
    {
        $filteredStudents = $this->getFilteredStudents();
        $canResetPromotions = auth()->user()?->can('reset promotion')
            || auth()->user()?->hasAnyRole(['super-admin', 'super_admin']);

        return view('livewire.students.promote-students', [
            'students' => $filteredStudents,
            'canResetPromotions' => $canResetPromotions,
        ])
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('students.index'), 'text' => 'Students'],
                    ['href' => route('students.promote'), 'text' => 'Promotion & Demotion', 'active' => true],
                ],
                'page_heading' => 'Promotion & Demotion'
            ])
            ->title('Promotion & Demotion');
    }
}
