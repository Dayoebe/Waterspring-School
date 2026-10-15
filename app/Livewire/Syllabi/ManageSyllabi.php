<?php

namespace App\Livewire\Syllabi;

use App\Models\MyClass;
use App\Models\Subject;
use App\Models\Syllabus;
use App\Traits\ResolvesRestrictedTeacherAssignments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ManageSyllabi extends Component
{
    use ResolvesRestrictedTeacherAssignments;
    use WithPagination;

    public string $mode = 'list';

    public ?int $syllabusId = null;

    public ?Syllabus $selectedSyllabus = null;

    public string $search = '';

    public int $perPage = 10;

    public $class = '';

    public $subject = '';

    public string $name = '';

    public string $description = '';

    public string $overview = '';

    public string $aims = '';

    public string $learningOutcomes = '';

    public string $scopeAndSequence = '';

    public string $prerequisites = '';

    public string $teachingMethodology = '';

    public string $assessmentPlan = '';

    public string $learningResources = '';

    public string $differentiation = '';

    public string $crossCurricularLinks = '';

    public string $homeworkPolicy = '';

    public string $references = '';

    public bool $isPublished = true;

    public $classes;

    public $subjects;

    protected $queryString = [
        'mode' => ['except' => 'list'],
        'search' => ['except' => ''],
        'class' => ['except' => ''],
    ];

    public function mount($syllabus = null): void
    {
        $this->classes = collect();
        $this->subjects = collect();

        $this->hydrateModeFromRoute($syllabus);
        $this->loadClasses();

        if ($this->mode === 'create' && ! $this->modeIsAllowed('create')) {
            $this->mode = 'list';
        }

        if ($this->mode === 'show') {
            if (! $this->modeIsAllowed('show') || ! $this->syllabusId) {
                $this->mode = 'list';
                $this->syllabusId = null;
            } else {
                $this->loadSyllabusForShow();
            }
        }
    }

    protected function hydrateModeFromRoute($syllabus = null): void
    {
        $routeName = request()->route()?->getName();

        if ($routeName === 'syllabi.create') {
            $this->mode = 'create';
            $this->syllabusId = null;

            return;
        }

        if ($routeName === 'syllabi.show') {
            $this->mode = 'show';
            $this->syllabusId = $this->resolveRouteModelId($syllabus ?? request()->route('syllabus'));

            return;
        }

        $this->mode = 'list';
        $this->syllabusId = null;
    }

    protected function resolveRouteModelId(mixed $value): ?int
    {
        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    protected function loadClasses(): void
    {
        $user = auth()->user();
        $studentClassId = $user->studentRecord?->myClass?->id;

        if ($user->hasRole('student')) {
            $this->class = $studentClassId ?: '';
            $this->loadSubjectsForClass();

            return;
        }

        $classesQuery = $user->school
            ->myClasses()
            ->orderBy('my_classes.name');

        if ($this->isRestrictedTeacher()) {
            $classIds = $this->restrictedTeacherAllClassIds();

            if ($classIds->isEmpty()) {
                $this->classes = collect();
                $this->class = '';
                $this->loadSubjectsForClass();

                return;
            }

            $classesQuery->whereIn('my_classes.id', $classIds);
        }

        $this->classes = $classesQuery->get(['my_classes.id', 'my_classes.name']);

        if ($this->classes->isNotEmpty() && ! $this->classes->contains('id', (int) $this->class)) {
            $this->class = (string) $this->classes->first()->id;
        }

        $this->loadSubjectsForClass();
    }

    public function updatedClass(): void
    {
        if (auth()->user()->hasRole('student')) {
            $this->class = (string) (auth()->user()->studentRecord?->myClass?->id ?: '');
        } elseif ($this->class !== '' && ! $this->classBelongsToCurrentSchool((int) $this->class)) {
            $this->class = (string) ($this->classes->first()?->id ?: '');
        }

        $this->loadSubjectsForClass();
        $this->resetPage();
    }

    protected function loadSubjectsForClass(): void
    {
        $classId = $this->class !== '' ? (int) $this->class : null;

        if (! $classId) {
            $this->subjects = collect();
            $this->subject = '';

            return;
        }

        if ($this->isRestrictedTeacher()) {
            $this->subjects = $this->restrictedTeacherSubjectsQuery($classId)
                ->where('subjects.is_legacy', false)
                ->get()
                ->unique('id')
                ->values();
        } else {
            $this->subjects = Subject::query()
                ->where('school_id', auth()->user()->school_id)
                ->where('is_legacy', false)
                ->where(function (Builder $query) use ($classId) {
                    $query->where('my_class_id', $classId)
                        ->orWhereHas('classes', function (Builder $classQuery) use ($classId) {
                            $classQuery->where('my_classes.id', $classId);
                        });
                })
                ->orderBy('name')
                ->get()
                ->unique('id')
                ->values();
        }

        if (! $this->subjects->contains('id', (int) $this->subject)) {
            $this->subject = (string) ($this->subjects->first()?->id ?: '');
        }
    }

    public function switchMode(string $mode, ?int $syllabusId = null)
    {
        if (! $this->modeIsAllowed($mode)) {
            return;
        }

        $this->mode = $mode;
        $this->syllabusId = $syllabusId;
        $this->resetValidation();

        if ($mode === 'create') {
            $this->resetCreateForm();
            $this->loadSubjectsForClass();

            return;
        }

        if ($mode === 'show' && $syllabusId) {
            $this->loadSyllabusForShow();

            return;
        }

        $this->selectedSyllabus = null;
    }

    protected function resetCreateForm(): void
    {
        $this->reset([
            'subject',
            'name',
            'description',
            'overview',
            'aims',
            'learningOutcomes',
            'scopeAndSequence',
            'prerequisites',
            'teachingMethodology',
            'assessmentPlan',
            'learningResources',
            'differentiation',
            'crossCurricularLinks',
            'homeworkPolicy',
            'references',
        ]);
        $this->isPublished = true;
    }

    public function createSyllabus()
    {
        $this->ensurePermission('create syllabus');

        $semesterId = auth()->user()->school?->semester_id;
        if (! $semesterId) {
            $this->addError('semester', 'Set an active term before creating a syllabus.');

            return;
        }

        $this->validate([
            'class' => 'required|integer',
            'subject' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'overview' => 'required|string|max:10000',
            'aims' => 'required|string|max:10000',
            'learningOutcomes' => 'required|string|max:15000',
            'scopeAndSequence' => 'required|string|max:30000',
            'prerequisites' => 'nullable|string|max:5000',
            'teachingMethodology' => 'required|string|max:10000',
            'assessmentPlan' => 'required|string|max:10000',
            'learningResources' => 'required|string|max:10000',
            'differentiation' => 'nullable|string|max:10000',
            'crossCurricularLinks' => 'nullable|string|max:10000',
            'homeworkPolicy' => 'nullable|string|max:5000',
            'references' => 'nullable|string|max:10000',
            'isPublished' => 'boolean',
        ]);

        $classId = (int) $this->class;
        if (! $this->classBelongsToCurrentSchool($classId)) {
            $this->addError('class', 'Selected class is not in your current school.');

            return;
        }

        $subject = $this->getSubjectForCurrentSchool((int) $this->subject);
        if (! $subject || ! $this->subjectMatchesClass($subject, $classId)) {
            $this->addError('subject', 'Selected subject is not valid for the selected class.');

            return;
        }

        $duplicateExists = Syllabus::query()
            ->where('subject_id', $subject->id)
            ->where('my_class_id', $classId)
            ->where('semester_id', $semesterId)
            ->exists();
        if ($duplicateExists) {
            $this->addError('subject', 'A syllabus already exists for this class, subject, and term.');

            return;
        }

        Syllabus::create([
            'name' => trim($this->name),
            'description' => trim($this->description),
            'overview' => trim($this->overview),
            'aims' => trim($this->aims),
            'learning_outcomes' => trim($this->learningOutcomes),
            'scope_and_sequence' => trim($this->scopeAndSequence),
            'prerequisites' => trim($this->prerequisites) ?: null,
            'teaching_methodology' => trim($this->teachingMethodology),
            'assessment_plan' => trim($this->assessmentPlan),
            'learning_resources' => trim($this->learningResources),
            'differentiation' => trim($this->differentiation) ?: null,
            'cross_curricular_links' => trim($this->crossCurricularLinks) ?: null,
            'homework_policy' => trim($this->homeworkPolicy) ?: null,
            'references' => trim($this->references) ?: null,
            'subject_id' => $subject->id,
            'semester_id' => $semesterId,
            'academic_year_id' => auth()->user()->school?->academic_year_id,
            'my_class_id' => $classId,
            'teacher_id' => auth()->id(),
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? now() : null,
        ]);

        session()->flash('success', 'Syllabus created successfully.');

        return $this->redirectRoute('syllabi.index', navigate: true);
    }

    public function loadSyllabusForShow(): void
    {
        $this->selectedSyllabus = $this->getSyllabusForCurrentSchool($this->syllabusId);
    }

    public function deleteSyllabus(int $syllabusId)
    {
        $this->ensurePermission('delete syllabus');

        $syllabus = $this->getSyllabusForCurrentSchool($syllabusId);
        if ($syllabus->file) {
            Storage::disk('public')->delete($syllabus->file);
        }
        $syllabus->delete();

        session()->flash('success', 'Syllabus deleted successfully.');

        if ($this->mode === 'show' && $this->syllabusId === $syllabusId) {
            return $this->redirectRoute('syllabi.index', navigate: true);
        }
    }

    protected function getSyllabiQuery(): Builder
    {
        $query = Syllabus::query()
            ->with(['subject', 'schoolClass', 'teacher'])
            ->forSchool(auth()->user()->school_id)
            ->forSemester(auth()->user()->school?->semester_id)
            ->forClass($this->class !== '' ? (int) $this->class : null)
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $searchQuery) {
                    $searchValue = '%'.$this->search.'%';
                    $searchQuery->where('name', 'like', $searchValue)
                        ->orWhere('description', 'like', $searchValue)
                        ->orWhereHas('subject', function (Builder $subjectQuery) use ($searchValue) {
                            $subjectQuery->where('name', 'like', $searchValue);
                        });
                });
            })
            ->latest();

        if ($this->isRestrictedTeacher()) {
            $query->whereHas('subject', function (Builder $subjectQuery) {
                $subjectQuery->whereIn(
                    'subjects.id',
                    $this->restrictedTeacherSubjectsQuery(
                        $this->class !== '' ? (int) $this->class : null
                    )->select('subjects.id')
                );
            });
        }

        return $query;
    }

    protected function classBelongsToCurrentSchool(int $classId): bool
    {
        if ($this->isRestrictedTeacher()) {
            return $this->restrictedTeacherCanAccessSubjectClass($classId);
        }

        return MyClass::query()
            ->whereKey($classId)
            ->whereHas('classGroup', function (Builder $query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->exists();
    }

    protected function getSubjectForCurrentSchool(int $subjectId): ?Subject
    {
        $query = Subject::query()
            ->where('school_id', auth()->user()->school_id)
            ->whereKey($subjectId);

        if ($this->isRestrictedTeacher()) {
            $query->whereIn(
                'subjects.id',
                $this->restrictedTeacherSubjectsQuery($this->class !== '' ? (int) $this->class : null)
                    ->select('subjects.id')
            );
        }

        return $query->first();
    }

    protected function subjectMatchesClass(Subject $subject, int $classId): bool
    {
        if ((int) $subject->my_class_id === $classId) {
            return true;
        }

        return $subject->classes()->where('my_classes.id', $classId)->exists();
    }

    protected function getSyllabusForCurrentSchool(?int $syllabusId): Syllabus
    {
        $query = Syllabus::query()
            ->with(['subject', 'schoolClass', 'teacher', 'academicYear', 'semester'])
            ->forSchool(auth()->user()->school_id)
            ->whereKey($syllabusId);

        if ($this->isRestrictedTeacher()) {
            $query->whereHas('subject', function (Builder $subjectQuery) {
                $subjectQuery->whereIn('subjects.id', $this->restrictedTeacherSubjectsQuery()->select('subjects.id'));
            });
        }

        return $query->findOrFail($syllabusId);
    }

    protected function modeIsAllowed(string $mode): bool
    {
        return match ($mode) {
            'create' => auth()->user()?->can('create syllabus') ?? false,
            'show' => auth()->user()?->can('read syllabus') ?? false,
            default => auth()->user()?->can('read syllabus') ?? false,
        };
    }

    protected function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        $syllabi = collect();
        $isRestrictedTeacherSyllabusManager = $this->isRestrictedTeacher();

        if ($this->mode === 'list') {
            $syllabi = $this->getSyllabiQuery()->paginate($this->perPage);
        }

        $breadcrumbs = [
            ['href' => route('dashboard'), 'text' => 'Dashboard'],
            ['href' => route('syllabi.index'), 'text' => 'Syllabi'],
        ];

        if ($this->mode === 'create') {
            $breadcrumbs[] = ['href' => route('syllabi.create'), 'text' => 'Create', 'active' => true];
        } elseif ($this->mode === 'show' && $this->selectedSyllabus) {
            $breadcrumbs[] = ['href' => route('syllabi.show', $this->selectedSyllabus->id), 'text' => $this->selectedSyllabus->name, 'active' => true];
        } else {
            $breadcrumbs[1]['active'] = true;
        }

        return view('livewire.syllabi.manage-syllabi', [
            'syllabi' => $syllabi,
            'isRestrictedTeacherSyllabusManager' => $isRestrictedTeacherSyllabusManager,
        ])
            ->layout('layouts.dashboard', ['breadcrumbs' => $breadcrumbs])
            ->title('Syllabi');
    }
}
