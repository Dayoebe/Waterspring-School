<?php

namespace App\Livewire\Cbt;

use App\Models\Assessment\Assessment;
use App\Models\Assessment\AttemptSession;
use App\Models\Assessment\Question;
use App\Models\Assessment\StudentAnswer;
use App\Services\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

#[Layout('layouts.exam', ['title' => 'CBT Exam', 'description' => 'Secure CBT exam interface'])]
class CbtExamInterface extends Component
{
    #[Locked]
    public $assessment;

    #[Locked]
    public $questions;

    #[Locked]
    public $currentQuestionIndex = 0;

    #[Locked]
    public $answers = [];
    public $timeRemaining;
    #[Locked]
    public $examStarted = false;

    #[Locked]
    public $examCompleted = false;
    #[Locked]
    public $results = null;
    #[Locked]
    public $flaggedQuestions = [];

    #[Locked]
    public $attemptNumber;
    public $startTime;
    #[Locked]
    public $securityViolations = [];
    public $isFullscreenForced = false;
    #[Locked]
    public $questionOrder = [];

    #[Locked]
    public ?int $attemptSessionId = null;
    public bool $resumedAttempt = false;
    #[Locked]
    public bool $resultsVisible = true;

    #[Locked]
    public bool $pendingPublish = false;
    public string $resultNotice = '';
    public string $resumeBanner = '';

    #[Locked]
    public $progressTracking = [
        'question_start_times' => [],
        'question_end_times' => [],
        'question_durations' => [],
        'total_active_time' => 0,
        'pause_count' => 0,
        'navigation_count' => 0,
    ];

    public function mount($assessment)
    {
        $currentUserId = (int) Auth::id();

        $this->assessment = $this->assessmentsForCurrentSchool()
            ->with([
                'questions',
                'studentLocks' => fn ($query) => $query->where('user_id', $currentUserId),
                'studentAnswers' => fn ($query) => $query->where('user_id', $currentUserId),
                'attemptSessions' => fn ($query) => $query->where('user_id', $currentUserId),
            ])
            ->find($assessment);

        if (!$this->assessment || $this->assessment->questions->count() === 0) {
            session()->flash('error', 'Invalid assessment.');
            return redirect()->route('cbt.exams');
        }

        if ($this->isCurrentStudentLocked()) {
            session()->flash('warning', $this->currentStudentLockMessage());
            return redirect()->route('cbt.exams');
        }

        if (!$this->isAssessmentPublished()) {
            session()->flash('warning', $this->unpublishedAssessmentMessage());
            return redirect()->route('cbt.exams');
        }

        $activeSession = $this->assessment->getActiveAttemptSession($currentUserId);
        if ($activeSession) {
            if ($activeSession->isExpired()) {
                $this->attemptNumber = (int) $activeSession->attempt_number;
                $this->loadQuestions();
                $this->initializeExam();
                $this->restoreAttemptSnapshot($activeSession);
                $this->submitExam(true, $activeSession);
                session()->flash('warning', 'Your previous attempt expired and was auto-submitted.');
                return redirect()->route('cbt.viewer');
            }

            $this->attemptNumber = (int) $activeSession->attempt_number;
            $this->loadQuestions();
            $this->initializeExam();
            $this->restoreAttemptSnapshot($activeSession);
            $this->activateExamSession($activeSession);
            $this->resumedAttempt = true;
            $this->resumeBanner = 'Resumed your in-progress attempt.';
            return;
        }

        [$canTake, $message] = $this->assessment->canUserTakeAssessment(Auth::id());
        if (!$canTake) {
            session()->flash('error', $message);
            return redirect()->route('cbt.exams');
        }

        $this->attemptNumber = $this->assessment->getNextAttemptNumber(Auth::id());
        $this->loadQuestions();
        $this->initializeExam();

        if (request()->boolean('autostart')) {
            try {
                $session = $this->createAttemptSession();
                $this->activateExamSession($session);
            } catch (\Throwable $e) {
                report($e);
                session()->flash('error', $this->resolveStartExamErrorMessage($e));
                return redirect()->route('cbt.exam.take', ['assessment' => $this->assessment->id]);
            }
        }
    }

    public function render()
    {
        return view('livewire.cbt.cbt-exam-interface');
    }

    protected function loadQuestions(): void
    {
        $questionCollection = $this->assessment->questions;

        if ($this->assessment->shuffle_questions) {
            $questionCollection = $questionCollection
                ->sortBy(fn ($question) => $this->deterministicHash('question-order', (int) $question->id))
                ->values();
        }

        $trustedQuestions = $questionCollection
            ->map(fn (Question $question): array => $this->buildTrustedQuestionData($question))
            ->values();

        // Livewire serializes public properties into the browser. Only send fields
        // required to display the paper; marking keys and option mappings stay server-side.
        $this->questions = $trustedQuestions
            ->map(fn (array $question): array => $this->displayQuestionData($question))
            ->all();

        $this->questionOrder = collect($this->questions)->pluck('id')->toArray();
    }

    protected function initializeExam(): void
    {
        $this->timeRemaining = max(60, (int) $this->assessment->estimated_duration_minutes * 60);
        foreach ($this->questions as $question) {
            $this->answers[$question['id']] = null;
        }
    }

    public function startExam(): void
    {
        $this->refreshAssessmentState();

        if ($this->isCurrentStudentLocked()) {
            $message = $this->currentStudentLockMessage();
            $this->dispatch('exam-start-feedback', state: 'warning', message: $message);
            session()->flash('warning', $message);
            return;
        }

        if (!$this->isAssessmentPublished()) {
            $message = $this->unpublishedAssessmentMessage();
            $this->dispatch('exam-start-feedback', state: 'warning', message: $message);
            session()->flash('warning', $message);
            return;
        }

        if ($this->examCompleted) {
            $this->dispatch('exam-start-feedback', state: 'warning', message: 'This attempt is already completed.');
            return;
        }

        if ($this->examStarted && $this->attemptSessionId) {
            $this->dispatch('exam-start-feedback', state: 'info', message: 'Exam is already running.');
            return;
        }

        try {
            $session = $this->createAttemptSession();
            $this->activateExamSession($session);
            $this->dispatch('exam-start-feedback', state: 'success', message: 'Exam started successfully. Timer is now running.');
            $this->dispatch('startTimer');
        } catch (\Throwable $e) {
            report($e);
            $message = $this->resolveStartExamErrorMessage($e);
            $this->dispatch('exam-start-feedback', state: 'error', message: $message);
            session()->flash('error', $message);
        }
    }

    #[Renderless]
    public function saveAnswer($questionId, $answer): void
    {
        $session = $this->currentAttemptSession();
        if (!$this->sessionAcceptsAnswers($session)) {
            return;
        }

        $question = $this->trustedQuestionDataForId((int) $questionId, $session);
        if (!$question) {
            return;
        }

        $this->answers[(int) $questionId] = $this->normalizeDisplayAnswer($question, $answer);

        $this->persistCurrentAnswer((int) $questionId, $session, $question);
        $this->persistAttemptSessionState($session);
    }

    #[Renderless]
    public function syncAnswers(array $answers): void
    {
        $session = $this->currentAttemptSession();
        if (!$this->sessionAcceptsAnswers($session)) {
            return;
        }

        foreach ($this->trustedQuestionsForSession($session) as $question) {
            $questionId = (int) $question['id'];
            $incomingAnswer = $answers[$questionId] ?? $answers[(string) $questionId] ?? null;
            $this->answers[$questionId] = $this->normalizeDisplayAnswer($question, $incomingAnswer);
        }

        $this->persistAttemptSessionState($session);
    }

    public function submitExamWithAnswers(array $answers): void
    {
        $this->syncAnswers($answers);
        $this->submitExam();
    }

    public function heartbeat(?int $clientTimeRemaining = null): array
    {
        if (!$this->examStarted || $this->examCompleted) {
            $this->skipRender();

            return [
                'time_remaining' => (int) $this->timeRemaining,
                'exam_completed' => (bool) $this->examCompleted,
            ];
        }

        $this->refreshAssessmentState(['studentLocks']);

        if ($this->isCurrentStudentLocked()) {
            session()->flash('warning', $this->currentStudentLockMessage());
            $this->skipRender();

            return [
                'time_remaining' => (int) $this->timeRemaining,
                'exam_completed' => (bool) $this->examCompleted,
                'redirect' => route('cbt.exams'),
            ];
        }

        if (!$this->isAssessmentPublished()) {
            session()->flash('warning', $this->unpublishedAssessmentMessage());
            $this->skipRender();

            return [
                'time_remaining' => (int) $this->timeRemaining,
                'exam_completed' => (bool) $this->examCompleted,
                'redirect' => route('cbt.exams'),
            ];
        }

        $session = $this->currentAttemptSession();
        if (!$session || $session->status !== 'in_progress') {
            $this->skipRender();

            return [
                'time_remaining' => (int) $this->timeRemaining,
                'exam_completed' => (bool) $this->examCompleted,
                'reload' => true,
            ];
        }

        $remaining = max(0, now()->diffInSeconds($session->expires_at, false));
        $this->timeRemaining = $remaining;

        $this->updateAttemptSession($session, [
            'last_activity_at' => now(),
            'current_question_index' => (int) $this->currentQuestionIndex,
            'flagged_question_ids' => array_values($this->flaggedQuestions),
            'state_revision' => DB::raw('state_revision + 1'),
        ]);

        if ($remaining <= 0) {
            $this->submitExam(true, $session);
            return [
                'time_remaining' => 0,
                'exam_completed' => true,
                'auto_submitted' => true,
            ];
        }

        $this->skipRender();

        return [
            'time_remaining' => $remaining,
            'exam_completed' => (bool) $this->examCompleted,
        ];
    }

    public function submitExam(bool $isAutoSubmit = false, ?AttemptSession $attemptSession = null): void
    {
        if ($this->examCompleted) {
            return;
        }

        try {
            $this->trackQuestionTime($this->currentQuestionIndex);
            $sessionId = (int) ($attemptSession?->id ?: $this->attemptSessionId);

            $submission = DB::transaction(function () use ($sessionId, $isAutoSubmit) {
                $session = $this->attemptSessionQuery()
                    ->whereKey($sessionId)
                    ->lockForUpdate()
                    ->first();

                if (!$session) {
                    throw new \RuntimeException('The authenticated CBT attempt could not be confirmed.');
                }

                $this->attemptSessionId = (int) $session->id;
                $this->attemptNumber = (int) $session->attempt_number;
                $trustedQuestions = $this->trustedQuestionsForSession($session);

                if ($session->status !== 'in_progress') {
                    return [
                        'session' => $session,
                        'questions' => $trustedQuestions,
                        'summary' => $this->summarizeStoredSubmission($session, $trustedQuestions),
                    ];
                }

                $this->mergeAnswersFromSession($session);
                $totalPoints = 0;
                $correctAnswers = 0;
                $answeredQuestions = 0;
                $submittedAt = now();

                foreach ($trustedQuestions as $questionIndex => $qData) {
                    $qId = (int) $qData['id'];
                    $userDisplayAnswer = $this->answers[$qId] ?? null;

                    $userDisplayAnswer = $this->normalizeDisplayAnswer($qData, $userDisplayAnswer);
                    $userOriginalAnswer = $userDisplayAnswer === null
                        ? null
                        : $this->mapDisplayToOriginalAnswer($qData, $userDisplayAnswer);

                    if ($userOriginalAnswer !== null) {
                        $answeredQuestions++;
                    }

                    $examData = [
                        'was_shuffled' => $qData['was_shuffled'],
                        'user_displayed_answer' => $userDisplayAnswer,
                        'user_original_answer' => $userOriginalAnswer,
                        'display_to_original_map' => $qData['display_to_original_map'],
                        'original_correct_answers' => $qData['original_correct_answers'],
                        'question_type' => $qData['question_type'] ?? null,
                        'question_points' => (float) ($qData['points'] ?? 0),
                    ];

                    $questionDurationSeconds = (int) round($this->progressTracking['question_durations'][$questionIndex] ?? 0);
                    $grading = $userOriginalAnswer === null
                        ? ['is_correct' => false, 'points_earned' => 0]
                        : $this->gradeSubmittedAnswer($qData, $userOriginalAnswer);

                    $studentAnswer = StudentAnswer::updateOrCreate(
                        [
                            'attempt_session_id' => $session->id,
                            'question_id' => $qId,
                        ],
                        [
                            'user_id' => $session->user_id,
                            'assessment_id' => $session->assessment_id,
                            'attempt_number' => $session->attempt_number,
                            'answer' => $userOriginalAnswer,
                            'time_spent_seconds' => $questionDurationSeconds,
                            'submitted_at' => $submittedAt,
                            'question_order' => $this->questionOrder,
                            'exam_data' => $examData,
                            'is_correct' => $grading['is_correct'],
                            'points_earned' => $grading['points_earned'],
                        ]
                    );

                    if ($studentAnswer->is_correct) {
                        $correctAnswers++;
                    }

                    $totalPoints += $studentAnswer->points_earned ?? 0;
                }

                $this->updateAttemptSession($session, [
                    'status' => $isAutoSubmit ? 'expired' : 'submitted',
                    'completed_at' => now(),
                    'last_activity_at' => now(),
                    'current_question_index' => (int) $this->currentQuestionIndex,
                    'answers_snapshot' => $this->answers,
                    'flagged_question_ids' => array_values($this->flaggedQuestions),
                    'security_violations' => $this->securityViolations,
                    'state_revision' => DB::raw('state_revision + 1'),
                ]);

                app(AuditTrail::class)->record(
                    $session->school_id,
                    'cbt',
                    $isAutoSubmit ? 'cbt.attempt_auto_submitted' : 'cbt.attempt_submitted',
                    $isAutoSubmit ? 'A CBT attempt was submitted automatically.' : 'A CBT attempt was submitted.',
                    $session,
                    ['status' => 'in_progress'],
                    ['status' => $session->status],
                    [
                        'assessment_id' => $session->assessment_id,
                        'user_id' => $session->user_id,
                        'attempt_number' => $session->attempt_number,
                        'answered_questions' => $answeredQuestions,
                    ],
                    subjectLabel: 'CBT attempt #'.$session->attempt_number,
                );

                return [
                    'session' => $session->fresh(),
                    'questions' => $trustedQuestions,
                    'summary' => [
                        'total_points' => $totalPoints,
                        'correct_answers' => $correctAnswers,
                        'answered_questions' => $answeredQuestions,
                    ],
                ];
            });

            $session = $submission['session'];
            $trustedQuestions = $submission['questions'];
            $submissionSummary = $submission['summary'];
            $maxPoints = collect($trustedQuestions)->sum('points') ?: 100;
            $percentage = $maxPoints > 0
                ? round(($submissionSummary['total_points'] / $maxPoints) * 100, 1)
                : 0;
            $timeSpent = $session->started_at
                ? abs($session->started_at->diffInSeconds($session->completed_at ?: now(), false))
                : 0;

            $resultPayload = [
                'total_questions' => count($trustedQuestions),
                'answered_questions' => $submissionSummary['answered_questions'],
                'correct_answers' => $submissionSummary['correct_answers'],
                'total_points' => $submissionSummary['total_points'],
                'max_points' => $maxPoints,
                'percentage' => $percentage,
                'passed' => $percentage >= $this->assessment->pass_percentage,
                'attempt_number' => $session->attempt_number,
                'time_spent' => $timeSpent,
            ];

            $this->resultsVisible = $this->assessment->canUserViewResults(Auth::user());
            $this->pendingPublish = !$this->resultsVisible;
            $this->results = $this->resultsVisible ? $resultPayload : null;
            $this->resultNotice = $this->pendingPublish
                ? 'Exam submitted. Your school will publish the result when ready.'
                : '';

            $this->examCompleted = true;
            $this->dispatch('examCompleted');
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to submit exam.');
        }
    }

    #[Renderless]
    public function nextQuestion(): void
    {
        if ($this->currentQuestionIndex < count($this->questions) - 1) {
            $this->trackQuestionTime($this->currentQuestionIndex);
            $previousIndex = $this->currentQuestionIndex;
            $this->currentQuestionIndex++;
            $this->progressTracking['navigation_count']++;
            $this->startQuestionTimer();
            $this->persistAttemptSessionState();

            $this->dispatch('questionChanged', previousIndex: $previousIndex, currentIndex: $this->currentQuestionIndex);
        }
    }

    #[Renderless]
    public function previousQuestion(): void
    {
        if ($this->currentQuestionIndex > 0) {
            $this->trackQuestionTime($this->currentQuestionIndex);
            $previousIndex = $this->currentQuestionIndex;
            $this->currentQuestionIndex--;
            $this->progressTracking['navigation_count']++;
            $this->startQuestionTimer();
            $this->persistAttemptSessionState();

            $this->dispatch('questionChanged', previousIndex: $previousIndex, currentIndex: $this->currentQuestionIndex);
        }
    }

    #[Renderless]
    public function goToQuestion($index): void
    {
        if ($index >= 0 && $index < count($this->questions)) {
            $this->trackQuestionTime($this->currentQuestionIndex);
            $previousIndex = $this->currentQuestionIndex;
            $this->currentQuestionIndex = (int) $index;
            $this->progressTracking['navigation_count']++;
            $this->startQuestionTimer();
            $this->persistAttemptSessionState();

            $this->dispatch('questionChanged', previousIndex: $previousIndex, currentIndex: $this->currentQuestionIndex);
        }
    }

    protected function startQuestionTimer(): void
    {
        $this->progressTracking['current_question_start'] = microtime(true);
    }

    protected function trackQuestionTime($questionIndex): void
    {
        if (isset($this->progressTracking['current_question_start'])) {
            $startTime = $this->progressTracking['current_question_start'];
            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $this->progressTracking['question_durations'][$questionIndex] = $duration;
            $this->progressTracking['total_active_time'] += $duration;
        }
    }

    #[Renderless]
    public function toggleFlag($questionIndex): void
    {
        if (!isset($this->questions[$questionIndex])) {
            return;
        }

        $questionId = (int) $this->questions[$questionIndex]['id'];

        if (in_array($questionId, $this->flaggedQuestions, true)) {
            $this->flaggedQuestions = array_values(array_filter($this->flaggedQuestions, fn ($id) => (int) $id !== $questionId));
        } else {
            $this->flaggedQuestions[] = $questionId;
        }

        $this->persistAttemptSessionState();
    }

    public function retakeExam()
    {
        return redirect()->route('cbt.exams');
    }

    public function handleSecurityViolation($type, $details = null): void
    {
        if (!$this->examStarted || $this->examCompleted) {
            $this->skipRender();
            return;
        }

        $violation = [
            'type' => $type,
            'details' => $details,
            'timestamp' => Carbon::now(),
            'question_index' => $this->currentQuestionIndex,
            'time_remaining' => $this->timeRemaining,
        ];

        $this->securityViolations[] = $violation;

        if ($type === 'app_switch' || $type === 'visibility_change') {
            $this->progressTracking['pause_count']++;
        }

        if ($type === 'fullscreen_exit') {
            $this->dispatch('forceFullscreen');
        }

        $this->persistAttemptSessionState();

        if (count($this->securityViolations) >= 10) {
            session()->flash('error', 'Too many security violations detected. Exam auto-submitted.');
            $this->submitExam(true);
            return;
        }

        $this->skipRender();
    }

    public function getCurrentQuestion()
    {
        if ($this->currentQuestionIndex >= 0 && $this->currentQuestionIndex < count($this->questions)) {
            return $this->questions[$this->currentQuestionIndex];
        }

        return null;
    }

    public function getAnsweredQuestionsCount(): int
    {
        return count(array_filter($this->answers, fn ($answer) => $answer !== null && $answer !== ''));
    }

    public function isQuestionFlagged($questionIndex): bool
    {
        if (!isset($this->questions[$questionIndex])) {
            return false;
        }

        $questionId = (int) $this->questions[$questionIndex]['id'];
        return in_array($questionId, $this->flaggedQuestions, true);
    }

    public function getProgressPercentage(): float
    {
        return count($this->questions) > 0
            ? (($this->currentQuestionIndex + 1) / count($this->questions)) * 100
            : 0;
    }

    public function canGoNext(): bool
    {
        return $this->currentQuestionIndex < count($this->questions) - 1;
    }

    public function canGoPrevious(): bool
    {
        return $this->currentQuestionIndex > 0;
    }

    public function isLastQuestion(): bool
    {
        return $this->currentQuestionIndex === count($this->questions) - 1;
    }

    public function formatTimeSpent($seconds): string
    {
        $seconds = abs((int) $seconds);

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    protected function createAttemptSession(): AttemptSession
    {
        $this->refreshAssessmentState();

        if ($this->isCurrentStudentLocked()) {
            throw new \RuntimeException($this->currentStudentLockMessage());
        }

        if (!$this->isAssessmentPublished()) {
            throw new \RuntimeException($this->unpublishedAssessmentMessage());
        }

        return DB::transaction(function (): AttemptSession {
            // Serialize exam-start creation per student so duplicate clicks/tabs
            // cannot create conflicting attempt records for the same paper.
            DB::table('users')
                ->where('id', Auth::id())
                ->lockForUpdate()
                ->first();

            $active = AttemptSession::query()
                ->where('assessment_id', $this->assessment->id)
                ->where('user_id', Auth::id())
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            if ($active && !$active->isExpired()) {
                $this->attemptNumber = (int) $active->attempt_number;
                return $active;
            }

            $durationSeconds = max(60, (int) $this->assessment->estimated_duration_minutes * 60);
            $this->attemptNumber = (int) $this->assessment->getNextAttemptNumber(Auth::id());

            return AttemptSession::create([
                'assessment_id' => $this->assessment->id,
                'user_id' => Auth::id(),
                'school_id' => (int) $this->currentSchoolId(),
                'attempt_number' => (int) $this->attemptNumber,
                'current_question_index' => (int) $this->currentQuestionIndex,
                'status' => 'in_progress',
                'started_at' => now(),
                'expires_at' => now()->addSeconds($durationSeconds),
                'last_activity_at' => now(),
                'question_order' => $this->questionOrder,
                'answers_snapshot' => $this->answers,
                'flagged_question_ids' => array_values($this->flaggedQuestions),
                'security_violations' => $this->securityViolations,
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        }, 3);
    }

    protected function activateExamSession(AttemptSession $session): void
    {
        $this->attemptSessionId = $session->id;
        $this->examStarted = true;
        $this->isFullscreenForced = true;
        $this->startTime = $session->started_at ?: Carbon::now();
        $this->timeRemaining = max(0, now()->diffInSeconds($session->expires_at, false));
        $this->startQuestionTimer();
    }

    protected function getActiveAttemptSession(): ?AttemptSession
    {
        return AttemptSession::query()
            ->where('assessment_id', $this->assessment->id)
            ->where('user_id', Auth::id())
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();
    }

    protected function currentAttemptSession(): ?AttemptSession
    {
        if ($this->attemptSessionId) {
            return $this->attemptSessionQuery()->find($this->attemptSessionId);
        }

        return $this->getActiveAttemptSession();
    }

    protected function attemptSessionQuery(): Builder
    {
        return AttemptSession::query()
            ->where('assessment_id', (int) $this->assessment->id)
            ->where('user_id', (int) Auth::id())
            ->where('school_id', (int) $this->currentSchoolId());
    }

    protected function restoreAttemptSnapshot(AttemptSession $session): void
    {
        $snapshot = $this->persistedDisplayAnswers($session);

        if ($snapshot === []) {
            $snapshot = is_array($session->answers_snapshot) ? $session->answers_snapshot : [];
        }

        foreach ($this->answers as $questionId => $value) {
            if (array_key_exists($questionId, $snapshot)) {
                $this->answers[$questionId] = $snapshot[$questionId];
            } elseif (array_key_exists((string) $questionId, $snapshot)) {
                $this->answers[$questionId] = $snapshot[(string) $questionId];
            }
        }

        $this->flaggedQuestions = array_values(array_map(
            'intval',
            is_array($session->flagged_question_ids) ? $session->flagged_question_ids : []
        ));
        $this->securityViolations = is_array($session->security_violations) ? $session->security_violations : [];

        $maxIndex = max(0, count($this->questions) - 1);
        $this->currentQuestionIndex = min(max(0, (int) $session->current_question_index), $maxIndex);
    }

    protected function mergeAnswersFromSession(?AttemptSession $session): void
    {
        if (!$session) {
            return;
        }

        $snapshot = $this->persistedDisplayAnswers($session);
        if ($snapshot === [] && is_array($session->answers_snapshot)) {
            $snapshot = $session->answers_snapshot;
        }

        foreach ($snapshot as $questionId => $answer) {
            if (array_key_exists((int) $questionId, $this->answers) && $this->answers[(int) $questionId] === null) {
                $this->answers[(int) $questionId] = $answer;
            }
        }
    }

    protected function persistCurrentAnswer(
        int $questionId,
        ?AttemptSession $session = null,
        ?array $questionData = null
    ): void
    {
        if (!$this->examStarted || $this->examCompleted) {
            return;
        }

        $session ??= $this->currentAttemptSession();
        if (!$this->sessionAcceptsAnswers($session)) {
            return;
        }

        $questionData ??= $this->trustedQuestionDataForId($questionId, $session);
        if (!$questionData) {
            return;
        }

        $displayAnswer = $this->answers[$questionId] ?? null;
        $originalAnswer = $displayAnswer === null
            ? null
            : $this->mapDisplayToOriginalAnswer($questionData, (int) $displayAnswer);

        $examData = [
            'was_shuffled' => $questionData['was_shuffled'] ?? false,
            'user_displayed_answer' => $displayAnswer,
            'user_original_answer' => $originalAnswer,
            'display_to_original_map' => $questionData['display_to_original_map'] ?? null,
            'original_correct_answers' => $questionData['original_correct_answers'] ?? [],
            'question_type' => $questionData['question_type'] ?? null,
            'question_points' => (float) ($questionData['points'] ?? 0),
        ];

        StudentAnswer::updateOrCreate(
            [
                'attempt_session_id' => $session->id,
                'question_id' => $questionId,
            ],
            [
                'user_id' => $session->user_id,
                'assessment_id' => $session->assessment_id,
                'attempt_number' => $session->attempt_number,
                'answer' => $originalAnswer,
                'submitted_at' => null,
                'question_order' => $this->questionOrder,
                'exam_data' => $examData,
            ]
        );
    }

    protected function persistAttemptSessionState(?AttemptSession $session = null): void
    {
        $session ??= $this->currentAttemptSession();
        if (!$this->sessionAcceptsAnswers($session)) {
            return;
        }

        $this->updateAttemptSession($session, [
            'last_activity_at' => now(),
            'current_question_index' => (int) $this->currentQuestionIndex,
            'flagged_question_ids' => array_values($this->flaggedQuestions),
            'security_violations' => $this->securityViolations,
            'state_revision' => DB::raw('state_revision + 1'),
        ]);
    }

    protected function buildTrustedQuestionData(Question $question): array
    {
        $options = is_array($question->options) ? array_values($question->options) : [];
        $correctAnswers = is_array($question->correct_answers)
            ? $question->correct_answers
            : (json_decode((string) $question->correct_answers, true) ?: []);
        $displayToOriginal = null;
        $wasShuffled = false;

        if (
            $this->assessment->shuffle_options
            && $options !== []
            && $question->question_type === 'multiple_choice'
        ) {
            $indexed = [];
            foreach ($options as $index => $text) {
                $indexed[] = [
                    'original_index' => (int) $index,
                    'text' => $text,
                    'weight' => $this->deterministicHash('option-order:'.$question->id, (int) $index),
                ];
            }

            usort($indexed, fn (array $left, array $right): int => strcmp($left['weight'], $right['weight']));
            $options = [];
            $displayToOriginal = [];
            foreach ($indexed as $displayIndex => $item) {
                $options[$displayIndex] = $item['text'];
                $displayToOriginal[$displayIndex] = $item['original_index'];
            }
            $wasShuffled = true;
        }

        return [
            'id' => (int) $question->id,
            'question_text' => (string) ($question->question_text ?? ''),
            'question_type' => (string) $question->question_type,
            'options' => $options,
            'points' => (float) ($question->points ?? 1),
            'question_media_url' => $question->question_media_url,
            'question_media_is_image' => (bool) $question->question_media_is_image,
            'question_media_original_name' => $question->question_media_original_name,
            'was_shuffled' => $wasShuffled,
            'display_to_original_map' => $displayToOriginal,
            'original_correct_answers' => array_values(array_map('intval', $correctAnswers)),
        ];
    }

    protected function updateAttemptSession(AttemptSession $session, array $attributes): void
    {
        AttemptSession::query()
            ->whereKey($session->id)
            ->update($attributes);

        $session->refresh();
    }

    protected function displayQuestionData(array $question): array
    {
        return collect($question)
            ->except(['display_to_original_map', 'original_correct_answers'])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trustedQuestionsForSession(AttemptSession $session): array
    {
        $questionsById = Question::query()
            ->withTrashed()
            ->where('assessment_id', $session->assessment_id)
            ->get()
            ->keyBy('id');
        $order = is_array($session->question_order) && $session->question_order !== []
            ? array_map('intval', $session->question_order)
            : $questionsById->keys()->map(fn ($id): int => (int) $id)->all();

        return collect($order)
            ->map(fn (int $questionId) => $questionsById->get($questionId))
            ->filter()
            ->map(fn (Question $question): array => $this->buildTrustedQuestionData($question))
            ->values()
            ->all();
    }

    protected function trustedQuestionDataForId(int $questionId, AttemptSession $session): ?array
    {
        return collect($this->trustedQuestionsForSession($session))->firstWhere('id', $questionId);
    }

    protected function normalizeDisplayAnswer(array $question, mixed $answer): ?int
    {
        if ($answer === null || $answer === '' || $answer === 'null') {
            return null;
        }

        if (!is_numeric($answer)) {
            return null;
        }

        $answer = (int) $answer;
        $validAnswers = match ($question['question_type'] ?? null) {
            'multiple_choice' => array_keys($question['options'] ?? []),
            'true_false' => [0, 1],
            default => [],
        };

        return in_array($answer, array_map('intval', $validAnswers), true) ? $answer : null;
    }

    protected function sessionAcceptsAnswers(?AttemptSession $session): bool
    {
        return $session !== null
            && $session->status === 'in_progress'
            && !$session->isExpired();
    }

    /**
     * @return array<int, int|null>
     */
    protected function persistedDisplayAnswers(AttemptSession $session): array
    {
        return StudentAnswer::query()
            ->where(function (Builder $query) use ($session): void {
                $query->where('attempt_session_id', $session->id)
                    ->orWhere(function (Builder $legacy) use ($session): void {
                        $legacy->whereNull('attempt_session_id')
                            ->where('user_id', $session->user_id)
                            ->where('assessment_id', $session->assessment_id)
                            ->where('attempt_number', $session->attempt_number);
                    });
            })
            ->get()
            ->mapWithKeys(function (StudentAnswer $answer): array {
                $displayAnswer = data_get($answer->exam_data, 'user_displayed_answer');

                return [(int) $answer->question_id => $displayAnswer === null ? null : (int) $displayAnswer];
            })
            ->all();
    }

    protected function summarizeStoredSubmission(AttemptSession $session, array $questions): array
    {
        $answers = StudentAnswer::query()
            ->where(function (Builder $query) use ($session): void {
                $query->where('attempt_session_id', $session->id)
                    ->orWhere(function (Builder $legacy) use ($session): void {
                        $legacy->whereNull('attempt_session_id')
                            ->where('user_id', $session->user_id)
                            ->where('assessment_id', $session->assessment_id)
                            ->where('attempt_number', $session->attempt_number);
                    });
            })
            ->whereNotNull('submitted_at')
            ->get();

        return [
            'total_points' => (float) $answers->sum('points_earned'),
            'correct_answers' => $answers->where('is_correct', true)->count(),
            'answered_questions' => $answers->filter(fn (StudentAnswer $answer): bool => $answer->answer !== null)->count(),
        ];
    }

    protected function mapDisplayToOriginalAnswer(array $questionData, int $displayAnswer): int
    {
        if (
            !empty($questionData['was_shuffled'])
            && isset($questionData['display_to_original_map'][$displayAnswer])
        ) {
            return (int) $questionData['display_to_original_map'][$displayAnswer];
        }

        return $displayAnswer;
    }

    protected function gradeSubmittedAnswer(array $questionData, int $userOriginalAnswer): array
    {
        $correctAnswers = array_map('intval', $questionData['original_correct_answers'] ?? []);
        $questionType = $questionData['question_type'] ?? null;
        $points = (float) ($questionData['points'] ?? 0);

        if ($questionType === 'multiple_choice') {
            $isCorrect = in_array((int) $userOriginalAnswer, $correctAnswers, true);

            return [
                'is_correct' => $isCorrect,
                'points_earned' => $isCorrect ? $points : 0,
            ];
        }

        if ($questionType === 'true_false') {
            $isCorrect = (int) $userOriginalAnswer === (int) ($correctAnswers[0] ?? 0);

            return [
                'is_correct' => $isCorrect,
                'points_earned' => $isCorrect ? $points : 0,
            ];
        }

        return [
            'is_correct' => false,
            'points_earned' => 0,
        ];
    }

    protected function deterministicHash(string $scope, int $entityId): string
    {
        return hash('sha256', implode('|', [
            $scope,
            (string) $entityId,
            (string) $this->assessment->id,
            (string) $this->attemptNumber,
            (string) Auth::id(),
        ]));
    }

    protected function currentSchoolId(): ?int
    {
        return auth()->user()?->school_id;
    }

    protected function assessmentsForCurrentSchool(): Builder
    {
        return Assessment::query()
            ->standaloneCBT()
            ->visibleToUser(auth()->user());
    }

    protected function refreshAssessmentState(array $relations = ['studentLocks', 'studentAnswers', 'attemptSessions']): void
    {
        if (!$this->assessment) {
            return;
        }

        $currentUserId = (int) Auth::id();
        $availableRelations = [
            'studentLocks' => fn ($query) => $query->where('user_id', $currentUserId),
            'studentAnswers' => fn ($query) => $query->where('user_id', $currentUserId),
            'attemptSessions' => fn ($query) => $query->where('user_id', $currentUserId),
        ];

        $relationsToLoad = array_intersect_key($availableRelations, array_flip($relations));

        $this->assessment->refresh();

        if ($relationsToLoad !== []) {
            $this->assessment->load($relationsToLoad);
        }
    }

    protected function isAssessmentPublished(): bool
    {
        return (bool) $this->assessment?->isExamPublished();
    }

    protected function isCurrentStudentLocked(): bool
    {
        return (bool) $this->assessment?->isLockedForStudent(Auth::id());
    }

    protected function currentStudentLockMessage(): string
    {
        return $this->assessment?->lockedStudentMessage()
            ?? 'You are currently restricted from writing this CBT exam. Please contact school administration.';
    }

    protected function unpublishedAssessmentMessage(): string
    {
        return 'This CBT exam has not been published to students yet.';
    }

    protected function resolveStartExamErrorMessage(\Throwable $exception): string
    {
        if ($exception instanceof \RuntimeException) {
            return $exception->getMessage();
        }

        if ($exception instanceof QueryException) {
            $message = $exception->getMessage();

            if (
                str_contains($message, 'assessment_attempt_sessions_attempt_unique')
                || str_contains($message, 'Duplicate entry')
            ) {
                return 'This exam is already opening in another tab or request. Reload once and resume the active attempt.';
            }

            if (
                str_contains($message, 'assessment_attempt_sessions')
                || str_contains($message, 'student_answers')
            ) {
                return 'CBT setup is incomplete. Ask the administrator to run the latest CBT database update, then try again.';
            }
        }

        return 'Failed to start exam. Please try again.';
    }
}
