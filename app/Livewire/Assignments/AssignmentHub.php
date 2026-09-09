<?php

namespace App\Livewire\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\MyClass;
use App\Models\StudentRecord;
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

    public ?int $selectedAssignmentId = null;

    public string $submissionText = '';

    public $submissionFile;

    public ?int $gradingSubmissionId = null;

    public string $gradeScore = '';

    public string $gradeFeedback = '';

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
            'maxScore' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'assignmentFile' => ['nullable', 'file', 'max:10240'],
        ]);

        $classId = (int) $validated['classId'];
        $subjectId = filled($validated['subjectId'] ?? null) ? (int) $validated['subjectId'] : null;
        abort_unless($this->canPublishTo($classId, $subjectId), 403);

        $recipientIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            (int) auth()->user()->school_id,
            (int) auth()->user()->school?->academic_year_id,
            $classId
        );
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
                'max_score' => filled($validated['maxScore'] ?? null) ? $validated['maxScore'] : null,
                'attachment_path' => $path,
                'attachment_name' => $this->assignmentFile?->getClientOriginalName(),
                'published_at' => now(),
            ]);
            $assignment->recipients()->attach($recipientIds->all(), ['assigned_at' => now()]);
        });

        $this->reset(['title', 'instructions', 'classId', 'subjectId', 'maxScore', 'assignmentFile', 'showCreate']);
        $this->dueAt = now()->addDays(7)->format('Y-m-d\TH:i');
        session()->flash('success', 'Assignment published to '.$recipientIds->count().' student'.($recipientIds->count() === 1 ? '' : 's').'.');
    }

    public function selectAssignment(int $assignmentId): void
    {
        $assignment = $this->findVisibleAssignment($assignmentId);
        $this->selectedAssignmentId = $assignment->id;
        $record = auth()->user()->studentRecord;
        $submission = $record ? $assignment->submissions()->where('student_record_id', $record->id)->first() : null;
        $this->submissionText = (string) ($submission?->response_text ?? '');
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
        if (blank($this->submissionText) && ! $this->submissionFile && ! $existing?->attachment_path) {
            $this->addError('submissionText', 'Write an answer or attach a file before submitting.');

            return;
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
        AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_record_id' => $studentRecord->id],
            ['response_text' => trim($this->submissionText) ?: null, 'attachment_path' => $path, 'attachment_name' => $name,
                'status' => now()->greaterThan($assignment->due_at) ? 'late' : 'submitted', 'submitted_at' => now(),
                'score' => null, 'feedback' => null, 'graded_by' => null, 'graded_at' => null]
        );
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
    }

    public function saveGrade(): void
    {
        abort_unless(auth()->user()?->can('grade assignment'), 403);
        $submission = $this->findGradableSubmission((int) $this->gradingSubmissionId);
        $max = $submission->assignment->max_score;
        $rules = ['required', 'numeric', 'min:0'];
        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }
        $this->validate(['gradeScore' => $rules, 'gradeFeedback' => ['nullable', 'string', 'max:10000']]);
        $submission->update(['score' => $this->gradeScore, 'feedback' => trim($this->gradeFeedback) ?: null,
            'status' => 'graded', 'graded_by' => auth()->id(), 'graded_at' => now()]);
        $this->reset(['gradingSubmissionId', 'gradeScore', 'gradeFeedback']);
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
        return $this->visibleAssignments()->with(['myClass', 'subject', 'teacher', 'recipients.user', 'submissions.studentRecord.user'])->findOrFail($id);
    }

    protected function findGradableSubmission(int $id): AssignmentSubmission
    {
        $submission = AssignmentSubmission::query()->with('assignment')->findOrFail($id);
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

    protected function allowedClasses(): Collection
    {
        $query = MyClass::query()->whereHas('classGroup', fn ($q) => $q->where('school_id', auth()->user()->school_id))->instructional()->orderBy('name');
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
        $query = $this->visibleAssignments()->with(['myClass', 'subject', 'teacher', 'recipients.user'])->withCount(['recipients', 'submissions']);
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
        ]);
    }
}
