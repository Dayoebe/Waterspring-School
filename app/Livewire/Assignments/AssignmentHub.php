<?php

namespace App\Livewire\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentAnswer;
use App\Models\AssignmentQuestion;
use App\Models\AssignmentSubmission;
use App\Models\MyClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssignmentHub extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showCreate = false;

    public string $title = '';

    public string $instructions = '';

    public string $classId = '';

    public string $subjectId = '';

    public string $dueAt = '';

    public string $maxScore = '';

    public $assignmentFile;

    public array $questions = [];

    public string $questionType = 'multiple_choice';

    public string $questionPrompt = '';

    public array $questionOptions = ['', '', '', ''];

    public array $questionCorrectAnswers = [];

    public string $questionAcceptedAnswers = '';

    public string $questionPoints = '1';

    public string $questionExplanation = '';

    public bool $questionRequired = true;

    public ?int $selectedAssignmentId = null;

    public string $submissionText = '';

    public $submissionFile;

    public array $studentAnswers = [];

    public ?int $gradingSubmissionId = null;

    public string $gradeScore = '';

    public string $gradeFeedback = '';

    public array $manualAnswerScores = [];

    public array $manualAnswerFeedback = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view assignment'), 403);
        abort_unless(auth()->user()?->school_id, 403, 'A school must be selected before using assignments.');
        abort_unless(auth()->user()?->school?->academic_year_id && auth()->user()?->school?->semester_id, 409, 'Set the current academic year and term before using assignments.');
        $this->dueAt = now()->addDays(7)->format('Y-m-d\TH:i');
    }

    public function updatedClassId(): void
    {
        $this->subjectId = '';
    }

    public function updatedQuestionType(): void
    {
        $this->questionCorrectAnswers = [];
        $this->questionAcceptedAnswers = '';
        if ($this->questionType === 'true_false') {
            $this->questionOptions = ['True', 'False'];
        } elseif (in_array($this->questionType, ['multiple_choice', 'multiple_select'], true)) {
            $this->questionOptions = ['', '', '', ''];
        }
    }

    public function addQuestion(): void
    {
        abort_unless(auth()->user()?->can('manage assignment'), 403);
        $this->validate([
            'questionType' => ['required', Rule::in(array_keys(AssignmentQuestion::TYPES))],
            'questionPrompt' => ['required', 'string', 'max:10000'],
            'questionPoints' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'questionExplanation' => ['nullable', 'string', 'max:10000'],
        ]);

        $options = null;
        $correctAnswers = null;
        if (in_array($this->questionType, ['multiple_choice', 'multiple_select'], true)) {
            $options = collect($this->questionOptions)->map(fn ($option) => trim((string) $option))->filter()->values()->all();
            if (count($options) < 2) {
                $this->addError('questionOptions', 'Enter at least two answer options.');

                return;
            }
            $correctAnswers = collect($this->questionCorrectAnswers)->map(fn ($answer) => (string) $answer)
                ->filter(fn ($answer) => in_array($answer, $options, true))->unique()->values()->all();
            if ($correctAnswers === [] || ($this->questionType === 'multiple_choice' && count($correctAnswers) !== 1)) {
                $this->addError('questionCorrectAnswers', $this->questionType === 'multiple_choice' ? 'Select one correct answer.' : 'Select at least one correct answer.');

                return;
            }
        } elseif ($this->questionType === 'true_false') {
            $options = ['True', 'False'];
            $correctAnswers = array_values(array_intersect($this->questionCorrectAnswers, $options));
            if (count($correctAnswers) !== 1) {
                $this->addError('questionCorrectAnswers', 'Select True or False as the correct answer.');

                return;
            }
        } elseif (in_array($this->questionType, ['fill_blank', 'short_answer'], true)) {
            $correctAnswers = collect(explode('|', $this->questionAcceptedAnswers))->map(fn ($answer) => trim($answer))->filter()->unique()->values()->all();
            if ($this->questionType === 'fill_blank' && $correctAnswers === []) {
                $this->addError('questionAcceptedAnswers', 'Enter at least one accepted answer.');

                return;
            }
        }

        $this->questions[] = [
            'type' => $this->questionType,
            'prompt' => trim($this->questionPrompt),
            'options' => $options,
            'correct_answers' => $correctAnswers,
            'points' => (float) $this->questionPoints,
            'explanation' => trim($this->questionExplanation) ?: null,
            'is_required' => $this->questionRequired,
        ];
        $this->resetQuestionBuilder();
    }

    public function removeQuestion(int $index): void
    {
        abort_unless(auth()->user()?->can('manage assignment'), 403);
        if (array_key_exists($index, $this->questions)) {
            unset($this->questions[$index]);
            $this->questions = array_values($this->questions);
        }
    }

    protected function resetQuestionBuilder(): void
    {
        $this->reset(['questionPrompt', 'questionCorrectAnswers', 'questionAcceptedAnswers', 'questionExplanation']);
        $this->questionType = 'multiple_choice';
        $this->questionOptions = ['', '', '', ''];
        $this->questionPoints = '1';
        $this->questionRequired = true;
        $this->resetValidation(['questionType', 'questionPrompt', 'questionOptions', 'questionCorrectAnswers', 'questionAcceptedAnswers', 'questionPoints', 'questionExplanation']);
    }

    public function createAssignment(): void
    {
        abort_unless(auth()->user()?->can('manage assignment'), 403);

        $classIds = $this->allowedClasses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $subjectIds = $this->allowedSubjectsForClass((int) $this->classId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:20000'],
            'classId' => ['required', 'integer', Rule::in($classIds)],
            'subjectId' => ['nullable', 'integer', Rule::in($subjectIds)],
            'dueAt' => ['required', 'date', 'after:now'],
            'maxScore' => [$this->questions === [] ? 'nullable' : 'nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'assignmentFile' => ['nullable', 'file', 'max:10240'],
        ]);

        $classId = (int) $validated['classId'];
        $subjectId = filled($validated['subjectId'] ?? null) ? (int) $validated['subjectId'] : null;
        abort_unless($this->canPublishTo($classId, $subjectId), 403);

        $recipientIds = $this->recipientIdsForClass($classId);
        if ($subjectId) {
            $recipientIds = DB::table('student_subject')->whereIn('student_record_id', $recipientIds)
                ->where('subject_id', $subjectId)->pluck('student_record_id')->map(fn ($id) => (int) $id)->unique()->values();
        }
        if ($recipientIds->isEmpty()) {
            $this->addError('classId', 'No eligible students were found for this assignment.');

            return;
        }

        DB::transaction(function () use ($validated, $classId, $subjectId, $recipientIds): void {
            $path = $this->assignmentFile?->store('assignments/'.auth()->user()->school_id, 'local');
            $structuredScore = collect($this->questions)->sum('points');
            $assignment = Assignment::query()->create([
                'school_id' => auth()->user()->school_id,
                'academic_year_id' => auth()->user()->school->academic_year_id,
                'semester_id' => auth()->user()->school->semester_id,
                'my_class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => auth()->id(),
                'title' => trim($validated['title']),
                'instructions' => trim($validated['instructions']),
                'due_at' => $validated['dueAt'],
                'max_score' => $this->questions !== [] ? $structuredScore : (filled($validated['maxScore'] ?? null) ? $validated['maxScore'] : null),
                'attachment_path' => $path,
                'attachment_name' => $this->assignmentFile?->getClientOriginalName(),
                'published_at' => now(),
            ]);
            foreach ($this->questions as $index => $question) {
                $assignment->questions()->create([
                    'question_type' => $question['type'], 'prompt' => $question['prompt'],
                    'options' => $question['options'], 'correct_answers' => $question['correct_answers'],
                    'points' => $question['points'], 'explanation' => $question['explanation'],
                    'position' => $index + 1, 'is_required' => $question['is_required'],
                ]);
            }
            $assignment->recipients()->attach($recipientIds->all(), ['assigned_at' => now()]);
        });

        $this->reset(['title', 'instructions', 'classId', 'subjectId', 'maxScore', 'assignmentFile', 'showCreate', 'questions']);
        $this->dueAt = now()->addDays(7)->format('Y-m-d\TH:i');
        session()->flash('success', 'Assignment published to '.$recipientIds->count().' student'.($recipientIds->count() === 1 ? '' : 's').'.');
    }

    public function selectAssignment(int $assignmentId): void
    {
        $assignment = $this->findVisibleAssignment($assignmentId);
        $this->selectedAssignmentId = $assignment->id;
        $record = auth()->user()->studentRecord;
        $submission = $record ? $assignment->submissions()->with('answers.question')->where('student_record_id', $record->id)->first() : null;
        $this->submissionText = (string) ($submission?->response_text ?? '');
        $this->studentAnswers = $submission?->answers->mapWithKeys(function ($answer) {
            $values = $answer->answer ?? [];

            return [$answer->assignment_question_id => $answer->question?->question_type === 'multiple_select'
                ? $values
                : ($values[0] ?? '')];
        })->all() ?? [];
        $this->reset(['submissionFile', 'gradingSubmissionId', 'gradeScore', 'gradeFeedback']);
    }

    public function submitAssignment(): void
    {
        abort_unless(auth()->user()?->can('submit assignment'), 403);
        $assignment = $this->findVisibleAssignment((int) $this->selectedAssignmentId);
        $studentRecord = auth()->user()->studentRecord;
        abort_unless($studentRecord && $assignment->recipients()->whereKey($studentRecord->id)->exists(), 403);
        $this->validate([
            'submissionText' => ['nullable', 'string', 'max:30000'],
            'submissionFile' => ['nullable', 'file', 'max:15360'],
        ]);
        $existing = $assignment->submissions()->where('student_record_id', $studentRecord->id)->first();
        $hasStructuredAnswers = collect($this->studentAnswers)->contains(fn ($answer) => is_array($answer) ? $answer !== [] : filled($answer));
        if ($assignment->questions->isEmpty() && blank($this->submissionText) && ! $this->submissionFile && ! $existing?->attachment_path) {
            $this->addError('submissionText', 'Write an answer or attach a file before submitting.');

            return;
        }
        if ($assignment->questions->isNotEmpty()) {
            foreach ($assignment->questions->where('is_required', true) as $question) {
                $answer = $this->studentAnswers[$question->id] ?? null;
                if ((is_array($answer) && $answer === []) || (! is_array($answer) && blank($answer))) {
                    $this->addError('studentAnswers.'.$question->id, 'This question is required.');
                }
            }
            if ($this->getErrorBag()->isNotEmpty() || ! $hasStructuredAnswers) {
                return;
            }
        }
        $path = $existing?->attachment_path;
        $name = $existing?->attachment_name;
        if ($this->submissionFile) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            $path = $this->submissionFile->store('assignment-submissions/'.auth()->user()->school_id, 'local');
            $name = $this->submissionFile->getClientOriginalName();
        }
        DB::transaction(function () use ($assignment, $studentRecord, $path, $name): void {
            $requiresReview = $assignment->questions->contains(fn ($question) => ! $question->isAutomaticallyMarked());
            $submission = AssignmentSubmission::query()->updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_record_id' => $studentRecord->id],
                ['response_text' => trim($this->submissionText) ?: null, 'attachment_path' => $path, 'attachment_name' => $name,
                    'status' => $requiresReview ? 'needs_review' : 'graded', 'submitted_at' => now(),
                    'score' => null, 'feedback' => null, 'graded_by' => null, 'graded_at' => $requiresReview ? null : now()]
            );
            $automaticScore = 0.0;
            foreach ($assignment->questions as $question) {
                $rawAnswer = $this->studentAnswers[$question->id] ?? null;
                $answer = is_array($rawAnswer) ? array_values($rawAnswer) : (filled($rawAnswer) ? [(string) $rawAnswer] : []);
                $mark = $question->mark($answer);
                AssignmentAnswer::query()->updateOrCreate(
                    ['assignment_submission_id' => $submission->id, 'assignment_question_id' => $question->id],
                    ['answer' => $answer, 'score' => $mark['score'] ?? null, 'is_correct' => $mark['is_correct'] ?? null, 'feedback' => null]
                );
                $automaticScore += (float) ($mark['score'] ?? 0);
            }
            if ($assignment->questions->isNotEmpty()) {
                $submission->update(['score' => $automaticScore]);
            }
        });
        $this->reset('submissionFile');
        session()->flash('success', 'Your assignment was submitted successfully.');
    }

    public function beginGrading(int $submissionId): void
    {
        $submission = $this->findGradableSubmission($submissionId);
        $this->selectedAssignmentId = $submission->assignment_id;
        $this->gradingSubmissionId = $submission->id;
        $this->gradeScore = (string) ($submission->score ?? '');
        $this->gradeFeedback = (string) ($submission->feedback ?? '');
        $this->manualAnswerScores = $submission->answers->filter(fn ($answer) => ! $answer->question?->isAutomaticallyMarked())
            ->mapWithKeys(fn ($answer) => [$answer->id => (string) ($answer->score ?? '')])->all();
        $this->manualAnswerFeedback = $submission->answers->filter(fn ($answer) => ! $answer->question?->isAutomaticallyMarked())
            ->mapWithKeys(fn ($answer) => [$answer->id => (string) ($answer->feedback ?? '')])->all();
    }

    public function saveGrade(): void
    {
        abort_unless(auth()->user()?->can('grade assignment'), 403);
        $submission = $this->findGradableSubmission((int) $this->gradingSubmissionId);
        $submission->load('answers.question');
        $max = $submission->assignment->max_score;
        if ($submission->answers->isNotEmpty()) {
            foreach ($submission->answers->filter(fn ($answer) => ! $answer->question?->isAutomaticallyMarked()) as $answer) {
                $this->validate([
                    'manualAnswerScores.'.$answer->id => ['required', 'numeric', 'min:0', 'max:'.$answer->question->points],
                    'manualAnswerFeedback.'.$answer->id => ['nullable', 'string', 'max:5000'],
                ]);
                $answer->update([
                    'score' => $this->manualAnswerScores[$answer->id],
                    'feedback' => trim($this->manualAnswerFeedback[$answer->id] ?? '') ?: null,
                ]);
            }
            $score = (float) $submission->answers()->sum('score');
        } else {
            $rules = ['required', 'numeric', 'min:0'];
            if ($max !== null) {
                $rules[] = 'max:'.$max;
            }
            $this->validate(['gradeScore' => $rules]);
            $score = (float) $this->gradeScore;
        }
        $this->validate(['gradeFeedback' => ['nullable', 'string', 'max:10000']]);
        $submission->update(['score' => $score, 'feedback' => trim($this->gradeFeedback) ?: null,
            'status' => 'graded', 'graded_by' => auth()->id(), 'graded_at' => now()]);
        $this->reset(['gradingSubmissionId', 'gradeScore', 'gradeFeedback', 'manualAnswerScores', 'manualAnswerFeedback']);
        session()->flash('success', 'Submission graded successfully.');
    }

    public function deleteAssignment(int $assignmentId): void
    {
        abort_unless(auth()->user()?->can('manage assignment'), 403);
        $assignment = Assignment::query()->where('school_id', auth()->user()->school_id)->findOrFail($assignmentId);
        abort_unless($this->isAdministrator() || $assignment->teacher_id === auth()->id(), 403);
        $assignment->delete();
        if ($this->selectedAssignmentId === $assignmentId) {
            $this->selectedAssignmentId = null;
        }
        session()->flash('success', 'Assignment removed.');
    }

    public function downloadAssignment(int $assignmentId)
    {
        $assignment = $this->findVisibleAssignment($assignmentId);
        abort_unless($assignment->attachment_path && Storage::disk('local')->exists($assignment->attachment_path), 404);

        return Storage::disk('local')->download($assignment->attachment_path, $assignment->attachment_name);
    }

    public function downloadSubmission(int $submissionId)
    {
        $submission = AssignmentSubmission::query()->with('assignment')->findOrFail($submissionId);
        $assignment = $this->findVisibleAssignment($submission->assignment_id);
        $student = auth()->user()->studentRecord;
        abort_unless($this->canManageAssignment($assignment) || ($student && $submission->student_record_id === $student->id), 403);
        abort_unless($submission->attachment_path && Storage::disk('local')->exists($submission->attachment_path), 404);

        return Storage::disk('local')->download($submission->attachment_path, $submission->attachment_name);
    }

    protected function visibleAssignments(): Builder
    {
        $user = auth()->user();
        $query = Assignment::query()->where('school_id', $user->school_id);
        if ($user->hasRole('student')) {
            $recordId = $user->studentRecord?->id;
            $query->whereHas('recipients', fn ($q) => $q->where('student_records.id', $recordId ?? 0));
        } elseif ($user->hasRole('parent')) {
            $childRecordIds = $user->children()->with('studentRecord')->get()->pluck('studentRecord.id')->filter();
            $query->whereHas('recipients', fn ($q) => $q->whereIn('student_records.id', $childRecordIds));
        } elseif (! $this->isAdministrator()) {
            $query->where('teacher_id', $user->id);
        }

        return $query;
    }

    protected function findVisibleAssignment(int $id): Assignment
    {
        return $this->visibleAssignments()->with(['myClass', 'subject', 'teacher', 'questions', 'recipients.user', 'submissions.studentRecord.user', 'submissions.answers.question'])->findOrFail($id);
    }

    protected function findGradableSubmission(int $id): AssignmentSubmission
    {
        $submission = AssignmentSubmission::query()->with(['assignment', 'answers.question'])->findOrFail($id);
        abort_unless($this->canManageAssignment($submission->assignment), 403);

        return $submission;
    }

    protected function canManageAssignment(Assignment $assignment): bool
    {
        return $assignment->school_id === auth()->user()->school_id
            && ($this->isAdministrator() || $assignment->teacher_id === auth()->id());
    }

    protected function isAdministrator(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'super_admin', 'principal', 'admin']);
    }

    protected function recipientIdsForClass(int $classId): Collection
    {
        $academicYearId = (int) auth()->user()->school->academic_year_id;

        return DB::table('student_records as sr')
            ->join('users as u', 'u.id', '=', 'sr.user_id')
            ->leftJoin('academic_year_student_record as placement', function ($join) use ($academicYearId): void {
                $join->on('placement.student_record_id', '=', 'sr.id')
                    ->where('placement.academic_year_id', $academicYearId);
            })
            ->where('u.school_id', auth()->user()->school_id)
            ->whereNull('u.deleted_at')
            ->where('sr.is_graduated', false)
            ->where(function ($query) use ($classId): void {
                $query->where('placement.my_class_id', $classId)
                    ->orWhere(function ($fallback) use ($classId): void {
                        $fallback->whereNull('placement.my_class_id')->where('sr.my_class_id', $classId);
                    });
            })
            ->distinct()
            ->pluck('sr.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function allowedClasses(): Collection
    {
        $query = MyClass::query()
            ->whereHas('classGroup', fn ($q) => $q->where('school_id', auth()->user()->school_id))
            ->whereRaw("UPPER(REPLACE(name, ' ', '')) <> 'ALUMNI'")
            ->orderBy('name');
        if ($this->isAdministrator()) {
            return $query->get(['id', 'name']);
        }
        $classTeacherIds = DB::table('class_teacher')->where('teacher_id', auth()->id())->pluck('class_id');
        $subjectClassIds = DB::table('subject_teacher')->where('user_id', auth()->id())->where('school_id', auth()->user()->school_id)
            ->whereNotNull('my_class_id')->pluck('my_class_id');
        $generalSubjectIds = DB::table('subject_teacher')->where('user_id', auth()->id())->where('school_id', auth()->user()->school_id)
            ->where('is_general', true)->pluck('subject_id');
        $generalClassIds = DB::table('class_subject')->whereIn('subject_id', $generalSubjectIds)->pluck('my_class_id');

        return $query->whereIn('id', $classTeacherIds->merge($subjectClassIds)->merge($generalClassIds)->unique())->get(['id', 'name']);
    }

    protected function allowedSubjectsForClass(int $classId): Collection
    {
        if ($classId <= 0) {
            return collect();
        }
        $query = Subject::query()->active()->where('school_id', auth()->user()->school_id)
            ->whereHas('classes', fn ($q) => $q->where('my_classes.id', $classId))->orderBy('name');
        if ($this->isAdministrator()) {
            return $query->get(['id', 'name']);
        }
        $subjectIds = DB::table('subject_teacher')->where('user_id', auth()->id())->where('school_id', auth()->user()->school_id)
            ->where(function ($q) use ($classId) {
                $q->where('my_class_id', $classId)->orWhere('is_general', true);
            })->pluck('subject_id');

        return $query->whereIn('id', $subjectIds)->get(['id', 'name']);
    }

    protected function canPublishTo(int $classId, ?int $subjectId): bool
    {
        if ($this->isAdministrator()) {
            return $this->allowedClasses()->contains('id', $classId) && (! $subjectId || $this->allowedSubjectsForClass($classId)->contains('id', $subjectId));
        }
        if ($subjectId) {
            return $this->allowedSubjectsForClass($classId)->contains('id', $subjectId);
        }

        return DB::table('class_teacher')->where('teacher_id', auth()->id())->where('class_id', $classId)->exists();
    }

    public function render()
    {
        $query = $this->visibleAssignments()->with(['myClass', 'subject', 'teacher', 'recipients.user'])->withCount(['recipients', 'submissions', 'questions']);
        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('instructions', 'like', '%'.$this->search.'%'));
        }
        if ($this->statusFilter === 'open') {
            $query->where('due_at', '>=', now());
        }
        if ($this->statusFilter === 'closed') {
            $query->where('due_at', '<', now());
        }
        $assignments = $query->latest('published_at')->get();
        $selectedAssignment = $this->selectedAssignmentId ? $this->findVisibleAssignment($this->selectedAssignmentId) : null;
        $childRecords = auth()->user()->hasRole('parent') ? auth()->user()->children()->with('studentRecord')->get()->pluck('studentRecord')->filter()->keyBy('id') : collect();

        return view('livewire.assignments.assignment-hub', [
            'assignments' => $assignments, 'selectedAssignment' => $selectedAssignment,
            'classes' => $this->allowedClasses(), 'subjects' => $this->allowedSubjectsForClass((int) $this->classId),
            'canManage' => auth()->user()->can('manage assignment'), 'isStudent' => auth()->user()->hasRole('student'),
            'isParent' => auth()->user()->hasRole('parent'), 'childRecords' => $childRecords,
            'questionTypes' => AssignmentQuestion::TYPES,
        ]);
    }
}
