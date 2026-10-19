<?php

namespace App\Livewire\ClassPilot;

use App\Models\ClassPilot\LearningSession;
use App\Models\ClassPilot\Material;
use App\Models\ClassPilot\Message;
use App\Models\ClassPilot\PracticeAttempt;
use App\Models\MyClass;
use App\Models\Notice;
use App\Models\Result;
use App\Models\SchoolEvent;
use App\Services\ClassPilot\Access;
use App\Services\ClassPilot\CurriculumContext;
use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\Manager;
use App\Support\ResultPublicationStatus;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Workspace extends Component
{
    public string $tab = 'overview';

    public string $classId = '';

    public string $subjectId = '';

    public string $studentRecordId = '';

    public string $topic = '';

    public string $subtopic = '';

    public string $objectives = '';

    public string $duration = '40';

    public string $outputFormat = 'detailed';

    public string $materialType = 'lesson';

    public string $difficulty = 'balanced';

    public string $questionCount = '10';

    public string $totalMarks = '20';

    public string $questionFormat = 'mixed';

    public string $question = '';

    public string $resultId = '';

    public ?int $selectedMaterialId = null;

    public array $editableMaterial = [];

    public array $generated = [];

    public array $sourceSummary = [];

    public string $revisionPrompt = '';

    public string $materialsSearch = '';

    public string $materialsStatus = 'all';

    public string $materialsType = 'all';

    public string $materialsSort = 'newest';

    public ?int $activeSessionId = null;

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(Access $access): void
    {
        $user = auth()->user();
        abort_unless($user?->school_id && $user->can('access classpilot'), 403);
        if ($this->isStudent()) {
            $record = $access->studentRecord($user);
            $this->studentRecordId = (string) $record->id;
            $this->classId = (string) ($record->getClassForYear($user->school?->academic_year_id)?->id ?? $record->my_class_id ?? '');
            $this->tab = 'tutor';
        } elseif ($this->isParent()) {
            $first = $user->children()->where('users.school_id', $user->school_id)->with('studentRecord')->first()?->studentRecord;
            $this->studentRecordId = (string) ($first?->id ?? '');
            $this->tab = 'family';
        } else {
            $this->classId = (string) ($this->teacherClasses($access)->first()?->id ?? '');
            $this->tab = 'lesson';
        }
    }

    public function updatedClassId(): void
    {
        $this->subjectId = '';
        $this->resetOutput();
    }

    public function updatedStudentRecordId(): void
    {
        $this->subjectId = '';
        $this->resultId = '';
        $this->resetOutput();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetOutput();
    }

    public function generateTeacher(Access $access, CurriculumContext $curriculum, Manager $manager): void
    {
        abort_unless($this->isTeacher(), 403);
        $data = $this->validate([
            'classId' => ['required', 'integer'], 'subjectId' => ['required', 'integer'], 'topic' => ['required', 'string', 'max:180'],
            'subtopic' => ['nullable', 'string', 'max:180'], 'objectives' => ['nullable', 'string', 'max:2000'], 'duration' => ['required', 'integer', 'min:10', 'max:240'],
            'outputFormat' => ['required', Rule::in(['concise', 'detailed'])], 'materialType' => ['required', Rule::in(['lesson', 'assessment'])],
            'difficulty' => ['required', Rule::in(['foundation', 'balanced', 'challenging'])], 'questionCount' => ['required', 'integer', 'min:1', 'max:30'],
            'totalMarks' => ['required', 'integer', 'min:1', 'max:200'], 'questionFormat' => ['required', Rule::in(['mixed', 'multiple_choice', 'short_answer', 'structured', 'higher_order', 'practical'])],
        ]);
        $user = auth()->user();
        $class = $this->teacherClasses($access)->whereKey($data['classId'])->firstOrFail();
        $subject = $access->teacherSubjects($user, (int) $class->id)->whereKey($data['subjectId'])->firstOrFail();
        $context = $curriculum->forSelection($user->school_id, $class->id, $subject->id, $user->school?->academic_year_id, $user->school?->semester_id, $data['topic']);
        $input = array_merge($data, ['class' => $class->name, 'subject' => $subject->name]);
        $this->run(fn () => $manager->generate($user, $data['materialType'], $input, $context));
        if ($this->generated === []) {
            return;
        }
        $material = Material::query()->create([
            'school_id' => $user->school_id, 'author_id' => $user->id, 'academic_year_id' => $user->school?->academic_year_id,
            'semester_id' => $user->school?->semester_id, 'my_class_id' => $class->id, 'subject_id' => $subject->id,
            'type' => $data['materialType'], 'title' => $this->generated['title'], 'topic' => $data['topic'], 'status' => 'draft',
            'content' => $this->generated, 'curriculum_sources' => $context['sources'],
        ]);
        $this->selectedMaterialId = $material->id;
        $this->editableMaterial = $this->generated;
        $this->sourceSummary = $context['sources'];
        $this->notice = 'Draft saved. Review and edit it before approval.';
    }

    public function saveMaterial(): void
    {
        $material = $this->ownedMaterial();
        abort_if($material->status === 'approved', 422, 'Approved materials must be returned to draft before editing.');
        $data = $this->validate([
            'editableMaterial.title' => ['required', 'string', 'max:200'],
            'editableMaterial' => ['required', 'array'],
        ]);
        $content = $data['editableMaterial'];
        $material->update(['content' => $content, 'title' => mb_substr($content['title'], 0, 100)]);
        $this->generated = $content;
        $this->notice = 'Draft changes saved.';
    }

    public function approveMaterial(): void
    {
        abort_unless(auth()->user()->can('approve classpilot material'), 403);
        $material = $this->ownedMaterial();
        $material->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->notice = 'Material approved. It remains separate from official exams and assignments until you publish it through those workflows.';
    }

    public function archiveMaterial(int $id): void
    {
        $material = Material::query()->where('school_id', auth()->user()->school_id)->where('author_id', auth()->id())->findOrFail($id);
        $material->update(['status' => 'archived']);
        $this->notice = 'Material archived.';
    }

    public function restoreMaterial(int $id): void
    {
        $material = Material::query()->where('school_id', auth()->user()->school_id)->where('author_id', auth()->id())->findOrFail($id);
        $material->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
        $this->notice = 'Material restored as a draft.';
    }

    public function deleteMaterial(int $id): void
    {
        $material = Material::query()->where('school_id', auth()->user()->school_id)->where('author_id', auth()->id())->findOrFail($id);
        $material->delete();
        if ($this->selectedMaterialId === $id) {
            $this->selectedMaterialId = null;
            $this->editableMaterial = [];
            $this->generated = [];
            $this->sourceSummary = [];
        }
        $this->notice = 'Material permanently deleted.';
    }

    public function startNewMaterial(): void
    {
        $this->selectedMaterialId = null;
        $this->editableMaterial = [];
        $this->generated = [];
        $this->revisionPrompt = '';
        $this->tab = 'lesson';
        $this->notice = null;
    }

    public function openMaterial(int $id): void
    {
        $material = Material::query()->where('school_id', auth()->user()->school_id)->where('author_id', auth()->id())->findOrFail($id);
        $this->selectedMaterialId = $material->id;
        $this->editableMaterial = $material->content;
        $this->generated = $material->content;
        $this->sourceSummary = $material->curriculum_sources ?? [];
        $this->materialType = $material->type;
        $this->classId = (string) ($material->my_class_id ?? '');
        $this->subjectId = (string) ($material->subject_id ?? '');
        $this->topic = $material->topic ?? '';
        $this->revisionPrompt = '';
        $this->resetValidation();
        $this->notice = 'Material opened. You can edit this draft or ask ClassPilot to create a revised copy.';
    }

    public function reviseMaterial(CurriculumContext $curriculum, Manager $manager): void
    {
        $data = $this->validate(['revisionPrompt' => ['required', 'string', 'min:3', 'max:1500']]);
        $original = $this->ownedMaterial();
        $user = auth()->user();
        $context = $curriculum->forSelection($user->school_id, $original->my_class_id, $original->subject_id, $original->academic_year_id, $original->semester_id, $original->topic);
        $input = [
            'class' => $original->schoolClass?->name,
            'subject' => $original->subject?->name,
            'topic' => $original->topic,
            'revision_request' => $data['revisionPrompt'],
            'original_material' => $original->content,
            'instruction' => 'Revise the supplied original material according to the request. Preserve useful original content unless the request asks to change it.',
        ];
        $this->run(fn () => $manager->generate($user, $original->type, $input, $context));
        if ($this->generated === []) {
            return;
        }
        $revision = Material::query()->create([
            'school_id' => $original->school_id, 'author_id' => $user->id, 'academic_year_id' => $original->academic_year_id,
            'semester_id' => $original->semester_id, 'my_class_id' => $original->my_class_id, 'subject_id' => $original->subject_id,
            'source_material_id' => $original->id, 'type' => $original->type, 'title' => $this->generated['title'], 'topic' => $original->topic,
            'status' => 'draft', 'content' => $this->generated, 'curriculum_sources' => $context['sources'],
        ]);
        $this->selectedMaterialId = $revision->id;
        $this->editableMaterial = $this->generated;
        $this->sourceSummary = $context['sources'];
        $this->revisionPrompt = '';
        $this->notice = "Revised draft #{$revision->id} created. The original material #{$original->id} is unchanged.";
    }

    public function askTutor(Access $access, CurriculumContext $curriculum, Manager $manager): void
    {
        abort_unless($this->isStudent(), 403);
        $data = $this->validate(['question' => ['required', 'string', 'min:3', 'max:2000'], 'subjectId' => ['required', 'integer']]);
        $user = auth()->user();
        $student = $access->studentRecord($user);
        $subject = $student->studentSubjects()->where('subjects.school_id', $user->school_id)->whereKey($data['subjectId'])->firstOrFail();
        $class = $student->getClassForYear($user->school?->academic_year_id) ?? $student->baseClass;
        $context = $curriculum->forSelection($user->school_id, $class?->id, $subject->id, $user->school?->academic_year_id, $user->school?->semester_id, $this->topic ?: null);
        $session = $this->activeSession($user->id, $student->id, 'student', 'tutor', $subject->id, $class?->id);
        Message::query()->create(['class_pilot_session_id' => $session->id, 'user_id' => $user->id, 'role' => 'user', 'content' => $data['question']]);
        $this->run(fn () => $manager->generate($user, 'tutor', ['subject' => $subject->name, 'question' => $data['question'], 'instruction' => 'Teach with hints and reasoning before a complete solution.'], $context));
        if ($this->generated !== []) {
            Message::query()->create(['class_pilot_session_id' => $session->id, 'role' => 'assistant', 'content' => $this->generated['answer'], 'metadata' => ['response' => $this->generated, 'curriculum_sources' => $context['sources']]]);
        }
        $session->update(['last_activity_at' => now()]);
        $this->question = '';
    }

    public function generatePractice(Access $access, CurriculumContext $curriculum, Manager $manager): void
    {
        abort_unless($this->isStudent(), 403);
        $data = $this->validate(['subjectId' => ['required', 'integer'], 'topic' => ['required', 'string', 'max:180'], 'questionCount' => ['required', 'integer', 'min:1', 'max:15']]);
        $user = auth()->user();
        $student = $access->studentRecord($user);
        $subject = $student->studentSubjects()->where('subjects.school_id', $user->school_id)->whereKey($data['subjectId'])->firstOrFail();
        $class = $student->getClassForYear($user->school?->academic_year_id) ?? $student->baseClass;
        $context = $curriculum->forSelection($user->school_id, $class?->id, $subject->id, $user->school?->academic_year_id, $user->school?->semester_id, $data['topic']);
        $this->run(fn () => $manager->generate($user, 'practice', ['subject' => $subject->name, 'topic' => $data['topic'], 'question_count' => (int) $data['questionCount']], $context));
        if ($this->generated !== []) {
            PracticeAttempt::query()->create(['school_id' => $user->school_id, 'student_record_id' => $student->id, 'subject_id' => $subject->id, 'activity_type' => 'generated_practice', 'topic' => $data['topic'], 'answers' => ['generated_questions' => $this->generated['questions']]]);
        }
    }

    public function askFamily(Access $access, CurriculumContext $curriculum, Manager $manager): void
    {
        abort_unless($this->isParent(), 403);
        $data = $this->validate(['studentRecordId' => ['required', 'integer'], 'question' => ['required', 'string', 'min:3', 'max:2000'], 'subjectId' => ['nullable', 'integer'], 'resultId' => ['nullable', 'integer']]);
        $user = auth()->user();
        $student = $access->childRecord($user, (int) $data['studentRecordId']);
        $subject = $data['subjectId'] ? $student->studentSubjects()->where('subjects.school_id', $user->school_id)->whereKey($data['subjectId'])->firstOrFail() : null;
        $resultData = null;
        if ($data['resultId']) {
            $result = Result::query()->whereKey($data['resultId'])->where('student_record_id', $student->id)->where('approved', true)->with(['subject', 'academicYear', 'semester'])->firstOrFail();
            abort_unless(ResultPublicationStatus::termIsPublished($user->school_id, $result->academic_year_id, $result->semester_id), 403, 'This result has not been published.');
            $resultData = ['subject' => $result->subject?->name, 'ca_scores' => array_values(array_filter([$result->ca1_score, $result->ca2_score, $result->ca3_score, $result->ca4_score], fn ($v) => $v !== null)), 'exam_score' => $result->exam_score, 'total_score' => $result->total_score];
            $subject ??= $result->subject;
        }
        $class = $student->getClassForYear($user->school?->academic_year_id) ?? $student->baseClass;
        $context = $curriculum->forSelection($user->school_id, $class?->id, $subject?->id, $user->school?->academic_year_id, $user->school?->semester_id, $this->topic ?: null);
        $this->run(fn () => $manager->generate($user, 'family', ['question' => $data['question'], 'subject' => $subject?->name, 'published_result' => $resultData, 'instruction' => 'Separate recorded facts from suggestions. If evidence is insufficient, say so.'], $context));
        if ($this->generated !== []) {
            $session = $this->activeSession($user->id, $student->id, 'family', 'family_support', $subject?->id, $class?->id);
            Message::query()->create(['class_pilot_session_id' => $session->id, 'user_id' => $user->id, 'role' => 'user', 'content' => $data['question']]);
            Message::query()->create(['class_pilot_session_id' => $session->id, 'role' => 'assistant', 'content' => $this->generated['answer'], 'metadata' => ['response' => $this->generated, 'result_id' => $data['resultId'] ?: null, 'curriculum_sources' => $context['sources']]]);
            $session->update(['last_activity_at' => now()]);
        }
        $this->question = '';
    }

    private function activeSession(int $userId, int $studentRecordId, string $audience, string $feature, ?int $subjectId, ?int $classId): LearningSession
    {
        if ($this->activeSessionId) {
            return LearningSession::query()->where('user_id', $userId)->where('school_id', auth()->user()->school_id)->findOrFail($this->activeSessionId);
        }
        $session = LearningSession::query()->create(['school_id' => auth()->user()->school_id, 'user_id' => $userId, 'student_record_id' => $studentRecordId, 'my_class_id' => $classId, 'subject_id' => $subjectId, 'audience' => $audience, 'feature' => $feature, 'title' => $this->topic ?: ucfirst(str_replace('_', ' ', $feature)), 'last_activity_at' => now()]);
        $this->activeSessionId = $session->id;

        return $session;
    }

    private function run(callable $callback): void
    {
        $this->resetOutput();
        try {
            $this->generated = $callback();
        } catch (ClassPilotException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    private function resetOutput(): void
    {
        $this->generated = [];
        $this->sourceSummary = [];
        $this->notice = null;
        $this->error = null;
    }

    private function ownedMaterial(): Material
    {
        return Material::query()->where('school_id', auth()->user()->school_id)->where('author_id', auth()->id())->findOrFail($this->selectedMaterialId);
    }

    private function isTeacher(): bool
    {
        return auth()->user()->hasAnyRole(['teacher', 'principal', 'admin', 'super-admin', 'super_admin']);
    }

    private function isStudent(): bool
    {
        return auth()->user()->hasRole('student') && ! $this->isTeacher();
    }

    private function isParent(): bool
    {
        return auth()->user()->hasRole('parent') && ! $this->isTeacher();
    }

    private function teacherClasses(Access $access)
    {
        $user = auth()->user();

        return $user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']) ? MyClass::query()->whereHas('classGroup', fn ($q) => $q->where('school_id', $user->school_id))->instructional() : $access->teacherClasses($user)->instructional();
    }

    public function render(Access $access)
    {
        $user = auth()->user();
        $student = null;
        $classes = $subjects = $children = $results = collect();
        $familyOverview = [];

        if ($this->isTeacher()) {
            $classes = $this->teacherClasses($access)->orderBy('name')->get();
            if ($this->classId) {
                $subjects = $access->teacherSubjects($user, (int) $this->classId)->orderBy('name')->get();
            }
        } elseif ($this->isStudent()) {
            $student = $access->studentRecord($user);
            $subjects = $student->getFilteredSubjects();
        } else {
            $children = $user->children()->where('users.school_id', $user->school_id)->with('studentRecord')->orderBy('name')->get();
            if ($this->studentRecordId) {
                $student = $access->childRecord($user, (int) $this->studentRecordId);
                $subjects = $student->getFilteredSubjects();
                $results = $student->results()->where('approved', true)->with(['subject', 'academicYear', 'semester'])->latest()->get()
                    ->filter(fn ($result) => ResultPublicationStatus::termIsPublished($user->school_id, $result->academic_year_id, $result->semester_id));
                $attendance = $student->attendanceRecords()->whereHas('attendanceSession', fn ($query) => $query
                    ->where('school_id', $user->school_id)->whereDate('attendance_date', '>=', now()->subDays(30)))
                    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
                $familyOverview = [
                    'attendance' => $attendance,
                    'assignments' => $student->assignments()->where('school_id', $user->school_id)->whereNotNull('published_at')->where('due_at', '>=', now())->with('subject')->orderBy('due_at')->limit(5)->get(),
                    'events' => SchoolEvent::query()->where('school_id', $user->school_id)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
                    'notices' => Notice::query()->where('school_id', $user->school_id)->where('active', true)->whereDate('start_date', '<=', today())->whereDate('stop_date', '>=', today())->latest()->limit(5)->get(),
                ];
            }
        }
        $materials = collect();
        if ($this->isTeacher()) {
            $materialsQuery = Material::query()->where('school_id', $user->school_id)->where('author_id', $user->id)->with(['schoolClass', 'subject']);
            if ($this->materialsSearch !== '') {
                $search = trim($this->materialsSearch);
                $materialsQuery->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('topic', 'like', "%{$search}%"));
            }
            if (in_array($this->materialsStatus, ['draft', 'approved', 'archived'], true)) {
                $materialsQuery->where('status', $this->materialsStatus);
            }
            if (in_array($this->materialsType, ['lesson', 'assessment'], true)) {
                $materialsQuery->where('type', $this->materialsType);
            }
            match ($this->materialsSort) {
                'oldest' => $materialsQuery->oldest(),
                'title_asc' => $materialsQuery->orderBy('title'),
                'title_desc' => $materialsQuery->orderByDesc('title'),
                'status' => $materialsQuery->orderBy('status')->latest(),
                default => $materialsQuery->latest(),
            };
            $materials = $materialsQuery->limit(100)->get();
        }
        $sessions = LearningSession::query()->where('school_id', $user->school_id)->where('user_id', $user->id)->with('messages')->latest('last_activity_at')->limit(8)->get();
        $attempts = $student ? PracticeAttempt::query()->where('student_record_id', $student->id)->with('subject')->latest()->limit(8)->get() : collect();

        return view('livewire.class-pilot.workspace', compact('classes', 'subjects', 'children', 'results', 'materials', 'sessions', 'attempts', 'familyOverview'))
            ->layout('layouts.dashboard', ['description' => 'Curriculum-aware teaching, tutoring, and family learning support.', 'icon' => 'fas fa-wand-magic-sparkles'])
            ->title('ClassPilot AI');
    }
}
