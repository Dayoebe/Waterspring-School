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

    public string $editableContent = '';

    public array $generated = [];

    public array $sourceSummary = [];

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
        $this->editableContent = json_encode($this->generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->sourceSummary = $context['sources'];
        $this->notice = 'Draft saved. Review and edit it before approval.';
    }

    public function saveMaterial(): void
    {
        $material = $this->ownedMaterial();
        abort_if($material->status === 'approved', 422, 'Approved materials must be returned to draft before editing.');
        $decoded = json_decode($this->editableContent, true);
        if (! is_array($decoded)) {
            $this->addError('editableContent', 'The edited content must remain valid JSON.');

            return;
        }
        $material->update(['content' => $decoded, 'title' => mb_substr((string) ($decoded['title'] ?? $material->title), 0, 100)]);
        $this->generated = $decoded;
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
        $materials = $this->isTeacher() ? Material::query()->where('school_id', $user->school_id)->where('author_id', $user->id)->with(['schoolClass', 'subject'])->latest()->limit(12)->get() : collect();
        $sessions = LearningSession::query()->where('school_id', $user->school_id)->where('user_id', $user->id)->with('messages')->latest('last_activity_at')->limit(8)->get();
        $attempts = $student ? PracticeAttempt::query()->where('student_record_id', $student->id)->with('subject')->latest()->limit(8)->get() : collect();

        return view('livewire.class-pilot.workspace', compact('classes', 'subjects', 'children', 'results', 'materials', 'sessions', 'attempts', 'familyOverview'))
            ->layout('layouts.dashboard', ['description' => 'Curriculum-aware teaching, tutoring, and family learning support.', 'icon' => 'fas fa-wand-magic-sparkles'])
            ->title('ClassPilot AI');
    }
}
