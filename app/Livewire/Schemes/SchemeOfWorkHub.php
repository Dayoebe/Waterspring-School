<?php

namespace App\Livewire\Schemes;

use App\Models\MyClass;
use App\Models\SchemeOfWork;
use App\Models\SchemeTopic;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchemeOfWorkHub extends Component
{
    public ?int $selectedSchemeId = null;

    public ?int $selectedWardId = null;

    public bool $showSchemeForm = false;

    public bool $showTopicForm = false;

    public ?int $editingTopicId = null;

    public string $type = 'subject';

    public ?int $classId = null;

    public ?int $subjectId = null;

    public string $title = '';

    public string $aim = '';

    public string $objectives = '';

    public string $teachingApproach = '';

    public string $assessmentStrategy = '';

    public string $resources = '';

    public string $learnerSupport = '';

    public bool $publishScheme = true;

    public int $weekNumber = 1;

    public string $scheduledFor = '';

    public string $topic = '';

    public string $topicAim = '';

    public string $topicObjectives = '';

    public string $contentOutline = '';

    public string $teacherActivities = '';

    public string $learnerActivities = '';

    public string $topicResources = '';

    public string $assessment = '';

    public string $homework = '';

    public string $completionNotes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view schemes of work'), 403);
        if (auth()->user()->hasRole('parent')) {
            $this->selectedWardId = auth()->user()->children()->where('users.school_id', auth()->user()->school_id)->value('users.id');
        }
        $this->selectedSchemeId = $this->visibleSchemesQuery()->value('id');
    }

    public function updatedSelectedWardId(): void
    {
        $this->selectedSchemeId = $this->visibleSchemesQuery()->value('id');
    }

    public function selectScheme(int $schemeId): void
    {
        $this->visibleSchemesQuery()->findOrFail($schemeId);
        $this->selectedSchemeId = $schemeId;
        $this->showSchemeForm = false;
        $this->resetTopicForm();
    }

    public function createScheme(): void
    {
        abort_unless($this->canCreate(), 403);
        $this->resetSchemeForm();
        $this->showSchemeForm = true;
    }

    public function editScheme(): void
    {
        $scheme = SchemeOfWork::query()->findOrFail($this->selectedSchemeId);
        $this->ensureCanManage($scheme);
        $this->type = $scheme->type;
        $this->classId = $scheme->my_class_id;
        $this->subjectId = $scheme->subject_id;
        $this->title = $scheme->title;
        $this->aim = $scheme->aim;
        $this->objectives = $scheme->objectives;
        $this->teachingApproach = (string) $scheme->teaching_approach;
        $this->assessmentStrategy = (string) $scheme->assessment_strategy;
        $this->resources = (string) $scheme->resources;
        $this->learnerSupport = (string) $scheme->learner_support;
        $this->publishScheme = (bool) $scheme->published_at;
        $this->showSchemeForm = true;
    }

    public function saveScheme(): void
    {
        abort_unless($this->canCreate(), 403);
        $validated = $this->validate([
            'type' => ['required', Rule::in(['class', 'subject'])], 'classId' => ['required', 'integer'],
            'subjectId' => [Rule::requiredIf($this->type === 'subject'), 'nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'], 'aim' => ['required', 'string', 'max:5000'],
            'objectives' => ['required', 'string', 'max:10000'], 'teachingApproach' => ['nullable', 'string', 'max:5000'],
            'assessmentStrategy' => ['nullable', 'string', 'max:5000'], 'resources' => ['nullable', 'string', 'max:5000'],
            'learnerSupport' => ['nullable', 'string', 'max:5000'], 'publishScheme' => ['boolean'],
        ]);
        $class = $this->availableClasses()->firstWhere('id', (int) $validated['classId']);
        abort_unless($class, 422, 'This class is not assigned to you.');
        $subject = $this->type === 'subject' ? $this->availableSubjects((int) $class->id)->firstWhere('id', (int) $validated['subjectId']) : null;
        abort_if($this->type === 'subject' && ! $subject, 422, 'This subject and class are not assigned to you.');
        $user = auth()->user();
        if (! $user->can('manage schemes of work')) {
            $canAuthorClassPlan = $this->type === 'class' && DB::table('class_teacher')->where('class_id', $class->id)->where('teacher_id', $user->id)->exists();
            $canAuthorSubjectPlan = $this->type === 'subject' && DB::table('subject_teacher')->where('subject_id', $subject?->id)->where('user_id', $user->id)->where('school_id', $user->school_id)->where(fn ($query) => $query->where('my_class_id', $class->id)->orWhere('is_general', true))->exists();
            abort_unless($canAuthorClassPlan || $canAuthorSubjectPlan, 403);
        }
        abort_unless($user->school?->academic_year_id && $user->school?->semester_id, 422, 'Set the current academic year and term first.');
        $scheme = $this->selectedSchemeId && $this->showSchemeForm ? SchemeOfWork::query()->find($this->selectedSchemeId) : null;
        if ($scheme) {
            $this->ensureCanManage($scheme);
        }
        $duplicateExists = SchemeOfWork::query()
            ->where('academic_year_id', $user->school->academic_year_id)
            ->where('semester_id', $user->school->semester_id)
            ->where('my_class_id', $class->id)
            ->where('scope_key', $subject ? 'subject:'.$subject->id : 'class')
            ->where('teacher_id', $scheme?->teacher_id ?? $user->id)
            ->when($scheme, fn (Builder $query) => $query->where('id', '!=', $scheme->id))
            ->exists();
        if ($duplicateExists) {
            $this->addError('title', 'A scheme already exists for this teacher, class, subject, and term.');

            return;
        }
        $scheme ??= new SchemeOfWork(['teacher_id' => $user->id]);
        $scheme->fill([
            'school_id' => $user->school_id, 'academic_year_id' => $user->school->academic_year_id,
            'semester_id' => $user->school->semester_id, 'my_class_id' => $class->id,
            'subject_id' => $subject?->id, 'type' => $validated['type'],
            'scope_key' => $subject ? 'subject:'.$subject->id : 'class', 'title' => trim($validated['title']),
            'aim' => trim($validated['aim']), 'objectives' => trim($validated['objectives']),
            'teaching_approach' => trim($validated['teachingApproach']) ?: null,
            'assessment_strategy' => trim($validated['assessmentStrategy']) ?: null,
            'resources' => trim($validated['resources']) ?: null, 'learner_support' => trim($validated['learnerSupport']) ?: null,
            'published_at' => $validated['publishScheme'] ? ($scheme->published_at ?: now()) : null, 'updated_by' => $user->id,
        ])->save();
        $this->selectedSchemeId = $scheme->id;
        $this->showSchemeForm = false;
        session()->flash('success', 'Scheme of work saved successfully.');
    }

    public function createTopic(): void
    {
        $scheme = SchemeOfWork::query()->findOrFail($this->selectedSchemeId);
        $this->ensureCanManage($scheme);
        $this->resetTopicForm();
        $this->weekNumber = ($scheme->topics()->max('week_number') ?? 0) + 1;
        $this->showTopicForm = true;
    }

    public function editTopic(int $topicId): void
    {
        $record = SchemeTopic::query()->with('scheme')->findOrFail($topicId);
        $this->ensureCanManage($record->scheme);
        $this->editingTopicId = $record->id;
        $this->weekNumber = $record->week_number;
        $this->scheduledFor = $record->scheduled_for?->format('Y-m-d') ?? '';
        $this->topic = $record->topic;
        $this->topicAim = $record->aim;
        $this->topicObjectives = $record->objectives;
        foreach (['contentOutline' => 'content_outline', 'teacherActivities' => 'teacher_activities', 'learnerActivities' => 'learner_activities', 'topicResources' => 'resources', 'assessment' => 'assessment', 'homework' => 'homework', 'completionNotes' => 'completion_notes'] as $property => $column) {
            $this->{$property} = (string) $record->{$column};
        }
        $this->showTopicForm = true;
    }

    public function saveTopic(): void
    {
        $scheme = SchemeOfWork::query()->findOrFail($this->selectedSchemeId);
        $this->ensureCanManage($scheme);
        $validated = $this->validate([
            'weekNumber' => ['required', 'integer', 'min:1', 'max:60', Rule::unique('scheme_topics', 'week_number')->where('scheme_of_work_id', $scheme->id)->ignore($this->editingTopicId)],
            'scheduledFor' => ['nullable', 'date'], 'topic' => ['required', 'string', 'max:255'],
            'topicAim' => ['required', 'string', 'max:5000'], 'topicObjectives' => ['required', 'string', 'max:10000'],
            'contentOutline' => ['nullable', 'string', 'max:10000'], 'teacherActivities' => ['nullable', 'string', 'max:10000'],
            'learnerActivities' => ['nullable', 'string', 'max:10000'], 'topicResources' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['nullable', 'string', 'max:5000'], 'homework' => ['nullable', 'string', 'max:5000'],
            'completionNotes' => ['nullable', 'string', 'max:5000'],
        ]);
        $record = $this->editingTopicId ? SchemeTopic::query()->where('scheme_of_work_id', $scheme->id)->findOrFail($this->editingTopicId) : new SchemeTopic(['scheme_of_work_id' => $scheme->id]);
        $record->fill([
            'week_number' => $validated['weekNumber'], 'scheduled_for' => $validated['scheduledFor'] ?: null,
            'topic' => trim($validated['topic']), 'aim' => trim($validated['topicAim']), 'objectives' => trim($validated['topicObjectives']),
            'content_outline' => trim($validated['contentOutline']) ?: null, 'teacher_activities' => trim($validated['teacherActivities']) ?: null,
            'learner_activities' => trim($validated['learnerActivities']) ?: null, 'resources' => trim($validated['topicResources']) ?: null,
            'assessment' => trim($validated['assessment']) ?: null, 'homework' => trim($validated['homework']) ?: null,
            'completion_notes' => trim($validated['completionNotes']) ?: null,
        ])->save();
        $this->resetTopicForm();
        session()->flash('success', 'Weekly topic saved.');
    }

    public function toggleTopicCompleted(int $topicId): void
    {
        $record = SchemeTopic::query()->with('scheme')->findOrFail($topicId);
        $this->ensureCanManage($record->scheme);
        $completed = $record->status !== 'completed';
        $record->update(['status' => $completed ? 'completed' : 'planned', 'completed_at' => $completed ? now() : null, 'completed_by' => $completed ? auth()->id() : null]);
        session()->flash('success', $completed ? 'Topic marked as completed.' : 'Topic reopened.');
    }

    public function deleteScheme(): void
    {
        $scheme = SchemeOfWork::query()->findOrFail($this->selectedSchemeId);
        $this->ensureCanManage($scheme);
        $scheme->delete();
        $this->selectedSchemeId = $this->visibleSchemesQuery()->value('id');
        $this->showSchemeForm = false;
        $this->resetTopicForm();
        session()->flash('success', 'Scheme of work and its weekly topics were deleted.');
    }

    public function deleteTopic(int $topicId): void
    {
        $record = SchemeTopic::query()->with('scheme')->findOrFail($topicId);
        $this->ensureCanManage($record->scheme);
        $record->delete();
        $this->resetTopicForm();
    }

    protected function visibleSchemesQuery(): Builder
    {
        $user = auth()->user();
        $query = SchemeOfWork::query()->where('academic_year_id', $user->school?->academic_year_id)->where('semester_id', $user->school?->semester_id);
        if ($user->can('manage schemes of work')) {
            return $query;
        }
        if ($user->hasRole('teacher')) {
            return $query->where(fn (Builder $q) => $q->where('teacher_id', $user->id)->orWhereIn('my_class_id', DB::table('class_teacher')->where('teacher_id', $user->id)->select('class_id')));
        }
        $studentId = $user->hasRole('student') ? $user->id : $this->selectedWardId;
        $student = $studentId ? User::query()->whereKey($studentId)->where('school_id', $user->school_id)->first() : null;
        if ($user->hasRole('parent')) {
            abort_unless($student && $user->children()->whereKey($student->id)->exists(), 403);
        }
        $classId = $student?->studentRecord?->my_class_id;

        return $query->where('my_class_id', $classId ?: 0)->whereNotNull('published_at');
    }

    protected function availableClasses()
    {
        $user = auth()->user();
        $query = MyClass::query()->whereHas('classGroup', fn (Builder $q) => $q->where('school_id', $user->school_id))->instructional()->orderBy('name');
        if ($user->can('manage schemes of work')) {
            return $query->get(['id', 'name']);
        }
        $classTeacherIds = DB::table('class_teacher')->where('teacher_id', $user->id)->pluck('class_id');
        $subjectClassIds = DB::table('subject_teacher')->where('user_id', $user->id)->where('school_id', $user->school_id)->whereNotNull('my_class_id')->pluck('my_class_id');
        $generalSubjectIds = DB::table('subject_teacher')->where('user_id', $user->id)->where('school_id', $user->school_id)->where('is_general', true)->pluck('subject_id');
        $generalClassIds = DB::table('class_subject')->whereIn('subject_id', $generalSubjectIds)->pluck('my_class_id');

        return $query->whereIn('id', $classTeacherIds->merge($subjectClassIds)->merge($generalClassIds)->unique())->get(['id', 'name']);
    }

    protected function availableSubjects(int $classId)
    {
        $user = auth()->user();
        $query = Subject::query()->active()->where(fn (Builder $q) => $q->where('my_class_id', $classId)->orWhereHas('classes', fn (Builder $q) => $q->where('my_classes.id', $classId)))->orderBy('name');
        if ($user->can('manage schemes of work')) {
            return $query->get(['id', 'name']);
        }
        $subjectIds = DB::table('subject_teacher')->where('user_id', $user->id)->where('school_id', $user->school_id)
            ->where(fn ($q) => $q->where('my_class_id', $classId)->orWhere('is_general', true))->pluck('subject_id');

        return $query->whereIn('id', $subjectIds)->get(['id', 'name']);
    }

    protected function canCreate(): bool
    {
        return auth()->user()->can('manage schemes of work') || auth()->user()->can('manage assigned schemes of work');
    }

    protected function ensureCanManage(SchemeOfWork $scheme): void
    {
        $user = auth()->user();
        if ($user->can('manage schemes of work')) {
            return;
        }
        $classAssigned = $scheme->type === 'class' && DB::table('class_teacher')->where('class_id', $scheme->my_class_id)->where('teacher_id', $user->id)->exists();
        $subjectAssigned = $scheme->type === 'subject' && DB::table('subject_teacher')->where('subject_id', $scheme->subject_id)->where('user_id', $user->id)->where('school_id', $user->school_id)->where(fn ($q) => $q->where('my_class_id', $scheme->my_class_id)->orWhere('is_general', true))->exists();
        abort_unless($user->can('manage assigned schemes of work') && ($scheme->teacher_id === $user->id || $classAssigned || $subjectAssigned), 403);
    }

    protected function resetSchemeForm(): void
    {
        $this->reset(['selectedSchemeId', 'showSchemeForm', 'classId', 'subjectId', 'title', 'aim', 'objectives', 'teachingApproach', 'assessmentStrategy', 'resources', 'learnerSupport']);
        $this->type = 'subject';
        $this->publishScheme = true;
    }

    protected function resetTopicForm(): void
    {
        $this->reset(['showTopicForm', 'editingTopicId', 'scheduledFor', 'topic', 'topicAim', 'topicObjectives', 'contentOutline', 'teacherActivities', 'learnerActivities', 'topicResources', 'assessment', 'homework', 'completionNotes']);
        $this->weekNumber = 1;
    }

    public function cancelSchemeForm(): void
    {
        $this->showSchemeForm = false;
    }

    public function cancelTopicForm(): void
    {
        $this->resetTopicForm();
    }

    public function render()
    {
        $user = auth()->user();
        $schemes = $this->visibleSchemesQuery()->with(['schoolClass:id,name', 'subject:id,name', 'teacher:id,name'])->withCount(['topics', 'topics as completed_topics_count' => fn ($q) => $q->where('status', 'completed')])->orderBy('title')->get();
        $selected = $this->selectedSchemeId ? $this->visibleSchemesQuery()->with(['schoolClass:id,name', 'subject:id,name', 'teacher:id,name', 'topics' => fn ($q) => $q->orderBy('week_number')])->find($this->selectedSchemeId) : null;
        if (! $selected && $schemes->isNotEmpty()) {
            $this->selectedSchemeId = $schemes->first()->id;
            $selected = $this->visibleSchemesQuery()->with(['schoolClass:id,name', 'subject:id,name', 'teacher:id,name', 'topics' => fn ($q) => $q->orderBy('week_number')])->find($this->selectedSchemeId);
        }
        $canManageSelected = $selected ? $this->canManageWithoutAbort($selected) : false;

        return view('livewire.schemes.scheme-of-work-hub', [
            'schemes' => $schemes, 'selectedScheme' => $selected, 'canCreate' => $this->canCreate(), 'canManageSelected' => $canManageSelected,
            'classes' => $this->canCreate() ? $this->availableClasses() : collect(),
            'subjects' => $this->canCreate() && $this->classId ? $this->availableSubjects($this->classId) : collect(),
            'wards' => $user->hasRole('parent') ? $user->children()->where('users.school_id', $user->school_id)->orderBy('name')->get(['users.id', 'users.name']) : collect(),
        ])->layout('layouts.dashboard', ['breadcrumbs' => [['href' => route('dashboard'), 'text' => 'Dashboard'], ['href' => route('schemes.index'), 'text' => 'Schemes of Work', 'active' => true]]])->title('Schemes of Work');
    }

    protected function canManageWithoutAbort(SchemeOfWork $scheme): bool
    {
        try {
            $this->ensureCanManage($scheme);

            return true;
        } catch (HttpException) {
            return false;
        }
    }
}
