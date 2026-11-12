<?php

namespace App\Livewire\OpenLearning;

use App\Models\OpenLearningRecord;
use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\OpenLearning\AssessmentMarker;
use App\Services\OpenLearning\CurriculumCatalog;
use App\Services\OpenLearning\SessionStore;
use App\Services\OpenLearning\Tutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Workspace extends Component
{
    public string $classId = '';

    public string $subjectId = '';

    public string $semesterId = '';

    public string $topicId = '';

    public string $teachingStyle = 'beginner';

    public string $phase = 'select';

    public string $learnerQuestion = '';

    public string $assessmentDifficulty = 'intermediate';

    public array $answers = [];

    public int $assessmentIndex = 0;

    public ?string $notice = null;

    public ?string $error = null;

    public bool $showSavePrompt = false;

    public function mount(SessionStore $store): void
    {
        $state = $store->current();
        if ($state === []) {
            return;
        }

        $this->syncFromState($state);
    }

    public function updatedClassId(): void
    {
        $this->reset(['subjectId', 'semesterId', 'topicId']);
    }

    public function updatedSubjectId(): void
    {
        $this->reset(['semesterId', 'topicId']);
    }

    public function updatedSemesterId(): void
    {
        $this->reset('topicId');
    }

    public function startLesson(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        $data = $this->validateSelection();
        $school = $catalog->school();
        abort_unless($school, 404);
        $topic = $catalog->selection(
            $school,
            (int) $data['classId'],
            (int) $data['subjectId'],
            (int) $data['semesterId'],
            (int) $data['topicId'],
        );
        abort_unless($topic, 404);

        $state = $store->start([
            'school_id' => $school->id,
            'academic_year_id' => $school->academic_year_id,
            'class_id' => (int) $data['classId'],
            'subject_id' => (int) $data['subjectId'],
            'semester_id' => (int) $data['semesterId'],
            'topic_id' => (int) $data['topicId'],
            'class_name' => $topic->scheme->schoolClass?->name,
            'subject_name' => $topic->scheme->subject?->name,
            'term_name' => $topic->scheme->semester?->name,
            'academic_year_name' => $topic->scheme->academicYear?->name,
            'topic_name' => $topic->topic,
        ], $this->teachingStyle);

        $this->phase = 'lesson';
        $this->generateSection($state, $topic, $store, $tutor);
    }

    public function continueLesson(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        [$state, $topic] = $this->active($catalog, $store);
        $current = (int) $state['current_section'];
        $state['completed_sections'] = collect($state['completed_sections'] ?? [])->push($current)->unique()->sort()->values()->all();

        if ($current >= count($state['section_plan'] ?? [])) {
            $store->put($state);
            $this->notice = 'Lesson complete. Review any section, explore beyond the classroom, or take an assessment.';

            return;
        }

        $state['current_section'] = $current + 1;
        $store->put($state);
        if (isset($state['sections'][(string) $state['current_section']])) {
            $this->notice = null;

            return;
        }

        $this->generateSection($state, $topic, $store, $tutor);
    }

    public function previousSection(SessionStore $store): void
    {
        $state = $store->current();
        if ($state === []) {
            return;
        }
        $state['current_section'] = max(1, (int) $state['current_section'] - 1);
        $store->put($state);
        $this->notice = null;
    }

    public function retryCurrentSection(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        [$state, $topic] = $this->active($catalog, $store);
        if (isset($state['sections'][(string) $state['current_section']])) {
            return;
        }

        $this->generateSection($state, $topic, $store, $tutor);
    }

    public function revisitSection(int $section, SessionStore $store): void
    {
        $state = $store->current();
        if ($state === [] || ! isset($state['sections'][(string) $section])) {
            return;
        }
        $state['current_section'] = $section;
        $store->put($state);
        $this->phase = 'lesson';
        $this->notice = null;
    }

    public function changeStyle(string $style, SessionStore $store): void
    {
        abort_unless(in_array($style, ['beginner', 'detailed', 'practical', 'examination'], true), 422);
        $this->teachingStyle = $style;
        $state = $store->current();
        if ($state !== []) {
            $state['teaching_style'] = $style;
            $store->put($state);
            $this->notice = 'Teaching style changed. Your lesson progress and conversation have been preserved.';
        }
    }

    public function quickAction(string $intent, CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        $prompts = [
            'simpler' => 'Please explain the current idea again in simpler language.',
            'example' => 'Please give me another worked example for the current idea.',
            'question' => 'Ask me one age-appropriate question about the current section, but do not reveal the answer yet.',
            'deeper' => 'Please explain the current idea in more depth.',
            'summary' => 'Please summarise the current section and its most important points.',
            'practice' => 'Give me one practice question and guide me with hints before revealing the answer.',
            'real_life' => 'How does the current idea apply in real life?',
        ];
        abort_unless(isset($prompts[$intent]), 422);
        $this->askCompanion($prompts[$intent], $intent, $catalog, $store, $tutor);
    }

    public function askQuestion(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        $data = $this->validate([
            'learnerQuestion' => ['required', 'string', 'min:2', 'max:'.config('open-learning.max_message_characters')],
        ]);
        $question = trim($data['learnerQuestion']);
        $this->learnerQuestion = '';
        $this->askCompanion($question, 'question', $catalog, $store, $tutor);
    }

    public function exploreBeyond(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        [$state, $topic] = $this->active($catalog, $store);
        $this->phase = 'beyond';
        if (! empty($state['beyond'])) {
            return;
        }

        $this->run(function () use ($state, $topic, $store, $tutor): void {
            $state['beyond'] = $tutor->beyond($state, $topic);
            $store->put($state);
            $this->notice = 'Beyond the Classroom is ready. Ask the companion any follow-up question.';
        });
    }

    public function showLesson(): void
    {
        $this->phase = 'lesson';
        $this->notice = null;
    }

    public function prepareAssessment(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        $this->validate(['assessmentDifficulty' => ['required', Rule::in(['easy', 'intermediate', 'challenging'])]]);
        [$state, $topic] = $this->active($catalog, $store);

        $this->run(function () use ($state, $topic, $store, $tutor): void {
            $state['assessment'] = $tutor->assessment($state, $topic, $this->assessmentDifficulty);
            $store->put($state);
            $this->answers = [];
            $this->assessmentIndex = 0;
            $this->phase = 'assessment';
            $this->notice = null;
        });
    }

    public function showAssessment(SessionStore $store): void
    {
        if (! empty($store->current()['assessment'])) {
            $this->phase = 'assessment';
        }
    }

    public function assessmentPrevious(): void
    {
        $this->assessmentIndex = max(0, $this->assessmentIndex - 1);
    }

    public function assessmentNext(SessionStore $store): void
    {
        $count = count(data_get($store->current(), 'assessment.questions', []));
        $this->assessmentIndex = min(max(0, $count - 1), $this->assessmentIndex + 1);
    }

    public function submitAssessment(string $token, CurriculumCatalog $catalog, SessionStore $store, AssessmentMarker $marker): void
    {
        [$state, $topic] = $this->active($catalog, $store);
        $assessment = $state['assessment'] ?? null;
        abort_unless(is_array($assessment) && hash_equals((string) $assessment['submission_token'], $token), 422);
        abort_if(! empty($assessment['submitted_at']), 409, 'This assessment has already been submitted.');

        $allowedIds = collect($assessment['questions'])->pluck('id')->all();
        $answers = collect($this->answers)->only($allowedIds)->map(fn ($answer) => mb_substr(trim((string) $answer), 0, 3000))->all();

        $this->run(function () use ($state, $topic, $assessment, $answers, $store, $marker): void {
            $assessment['result'] = $marker->mark($state, $topic, $assessment, $answers);
            $assessment['submitted_at'] = now()->toIso8601String();
            $assessment['learner_answers'] = $answers;
            $state['assessment'] = $assessment;
            $store->put($state);
            $this->notice = 'Assessment submitted. Your score and detailed corrections are ready.';
        });
    }

    public function saveProgress(CurriculumCatalog $catalog, SessionStore $store): void
    {
        $state = $store->current();
        if ($state === []) {
            return;
        }
        if (! auth()->check()) {
            $this->showSavePrompt = true;

            return;
        }
        abort_unless($this->eligibleToSave(), 403);
        [$state, $topic] = $this->active($catalog, $store);
        $user = auth()->user();

        $record = DB::transaction(function () use ($state, $topic, $user): OpenLearningRecord {
            $existing = isset($state['saved_record_id'])
                ? OpenLearningRecord::query()->where('user_id', $user->id)->find($state['saved_record_id'])
                : null;
            $record = $existing ?? new OpenLearningRecord(['public_id' => (string) Str::uuid(), 'user_id' => $user->id]);
            $record->fill([
                'school_id' => $topic->scheme->school_id,
                'academic_year_id' => $topic->scheme->academic_year_id,
                'semester_id' => $topic->scheme->semester_id,
                'my_class_id' => $topic->scheme->my_class_id,
                'subject_id' => $topic->scheme->subject_id,
                'scheme_topic_id' => $topic->id,
                'title' => (string) ($state['lesson_title'] ?? $topic->topic),
                'teaching_style' => $state['teaching_style'],
                'progress_percentage' => $this->progress($state),
                'current_section' => $state['current_section'],
                'lesson_state' => collect($state)->except(['messages', 'assessment'])->all(),
                'conversation_state' => collect($state['messages'] ?? [])->take(-40)->values()->all(),
                'assessment_state' => $state['assessment'] ?? null,
                'last_activity_at' => now(),
            ]);
            $record->save();

            return $record;
        });

        $state['saved_record_id'] = $record->id;
        $store->put($state);
        $store->claim($user->id);
        $this->notice = 'Your learning progress has been saved to your Water Spring account.';
        $this->showSavePrompt = false;
    }

    public function loadRecord(int $recordId, CurriculumCatalog $catalog, SessionStore $store): void
    {
        abort_unless(auth()->check() && $this->eligibleToSave(), 403);
        $record = OpenLearningRecord::query()->where('user_id', auth()->id())->findOrFail($recordId);
        Gate::authorize('view', $record);
        $school = $catalog->school();
        abort_unless($school && $catalog->selection($school, $record->my_class_id, $record->subject_id, $record->semester_id, $record->scheme_topic_id), 404);

        $state = array_merge($record->lesson_state, [
            'id' => (string) Str::uuid(),
            'claimed_by_user_id' => auth()->id(),
            'saved_record_id' => $record->id,
            'messages' => $record->conversation_state ?? [],
            'assessment' => $record->assessment_state,
            'last_activity_at' => now()->toIso8601String(),
        ]);
        $store->put($state);
        $this->syncFromState($state);
        $this->phase = 'lesson';
        $this->notice = 'Saved learning progress restored.';
    }

    public function startOver(SessionStore $store): void
    {
        $store->forget();
        $this->reset([
            'classId', 'subjectId', 'semesterId', 'topicId', 'learnerQuestion', 'answers',
            'assessmentIndex', 'notice', 'error', 'showSavePrompt',
        ]);
        $this->phase = 'select';
        $this->teachingStyle = 'beginner';
    }

    public function render(CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor)
    {
        $school = $catalog->school();
        $classes = $school ? $catalog->classes($school) : collect();
        $subjects = $school && $this->classId !== '' ? $catalog->subjects($school, (int) $this->classId) : collect();
        $terms = $school && $this->classId !== '' && $this->subjectId !== ''
            ? $catalog->terms($school, (int) $this->classId, (int) $this->subjectId) : collect();
        $topics = $school && $this->classId !== '' && $this->subjectId !== '' && $this->semesterId !== ''
            ? $catalog->topics($school, (int) $this->classId, (int) $this->subjectId, (int) $this->semesterId) : collect();
        $state = $store->current();
        $assessment = $this->publicAssessment($state['assessment'] ?? null);
        $savedRecords = auth()->check() && $this->eligibleToSave()
            ? OpenLearningRecord::query()->where('user_id', auth()->id())->with(['topic:id,topic', 'subject:id,name'])
                ->latest('last_activity_at')->limit(8)->get()
            : collect();

        return view('livewire.open-learning.workspace', compact(
            'school', 'classes', 'subjects', 'terms', 'topics', 'state', 'assessment', 'savedRecords'
        ))->with([
            'aiConfigured' => $tutor->configured(),
            'eligibleToSave' => $this->eligibleToSave(),
            'progress' => $this->progress($state),
        ]);
    }

    private function validateSelection(): array
    {
        return $this->validate([
            'classId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],
            'semesterId' => ['required', 'integer'],
            'topicId' => ['required', 'integer'],
            'teachingStyle' => ['required', Rule::in(['beginner', 'detailed', 'practical', 'examination'])],
        ]);
    }

    private function active(CurriculumCatalog $catalog, SessionStore $store): array
    {
        $state = $store->current();
        abort_if($state === [], 409, 'Your learning session has expired. Please select a topic again.');
        $school = $catalog->school();
        abort_unless($school && (int) $state['school_id'] === (int) $school->id, 404);
        $topic = $catalog->selection($school, (int) $state['class_id'], (int) $state['subject_id'], (int) $state['semester_id'], (int) $state['topic_id']);
        abort_unless($topic, 404);

        return [$state, $topic];
    }

    private function generateSection(array $state, $topic, SessionStore $store, Tutor $tutor): void
    {
        $this->run(function () use ($state, $topic, $store, $tutor): void {
            $generated = $tutor->lessonSection($state, $topic);
            $state['lesson_title'] = $generated['lesson_title'];
            $state['objectives'] = $generated['objectives'];
            $state['section_plan'] = $state['section_plan'] ?: $generated['section_plan'];
            $state['sections'][(string) $state['current_section']] = $generated['section'];
            $store->put($state);
            $this->notice = null;
        });
    }

    private function askCompanion(string $message, string $intent, CurriculumCatalog $catalog, SessionStore $store, Tutor $tutor): void
    {
        [$state, $topic] = $this->active($catalog, $store);
        $this->run(function () use ($state, $topic, $message, $intent, $store, $tutor): void {
            $response = $tutor->companion($state, $topic, $message, $intent);
            $state['messages'][] = ['role' => 'learner', 'content' => $message, 'created_at' => now()->toIso8601String()];
            $state['messages'][] = ['role' => 'companion', 'content' => $response['answer'], 'details' => $response, 'created_at' => now()->toIso8601String()];
            $state['messages'] = collect($state['messages'])->take(-40)->values()->all();
            $store->put($state);
            $this->notice = null;
        });
    }

    private function run(callable $callback): void
    {
        $this->error = null;
        $this->notice = null;
        try {
            $callback();
        } catch (ClassPilotException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    private function syncFromState(array $state): void
    {
        $this->classId = (string) ($state['class_id'] ?? '');
        $this->subjectId = (string) ($state['subject_id'] ?? '');
        $this->semesterId = (string) ($state['semester_id'] ?? '');
        $this->topicId = (string) ($state['topic_id'] ?? '');
        $this->teachingStyle = (string) ($state['teaching_style'] ?? 'beginner');
        $this->phase = ! empty($state['sections']) ? 'lesson' : 'select';
    }

    private function publicAssessment(?array $assessment): ?array
    {
        if (! $assessment) {
            return null;
        }

        $result = $assessment['result'] ?? null;
        $assessment['questions'] = collect($assessment['questions'] ?? [])->map(function (array $question) use ($result): array {
            if ($result) {
                return $question;
            }

            return collect($question)->except(['expected_answer', 'accepted_answers', 'marking_guide', 'explanation'])->all();
        })->values()->all();

        return $assessment;
    }

    private function progress(array $state): int
    {
        $total = count($state['section_plan'] ?? []);
        if ($total === 0) {
            return 0;
        }

        return min(100, (int) round((count($state['completed_sections'] ?? []) / $total) * 100));
    }

    private function eligibleToSave(): bool
    {
        $user = auth()->user();

        return $user?->hasAnyRole((array) config('open-learning.eligible_roles')) === true;
    }
}
