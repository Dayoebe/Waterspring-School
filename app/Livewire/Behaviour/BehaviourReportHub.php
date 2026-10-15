<?php

namespace App\Livewire\Behaviour;

use App\Models\BehaviourReport;
use App\Models\BehaviourReportMessage;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class BehaviourReportHub extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public ?int $selectedReportId = null;
    public bool $showComposer = false;
    public string $contactKey = '';
    public string $category = 'conduct';
    public string $severity = 'observation';
    public string $title = '';
    public string $occurredOn = '';
    public string $initialMessage = '';
    public string $reply = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access behavioural reports'), 403);
        $this->occurredOn = now()->toDateString();
        $this->selectedReportId = $this->visibleReports()->where('status', 'open')->latest('last_message_at')->value('id')
            ?? $this->visibleReports()->latest('last_message_at')->value('id');
        $this->markSelectedRead();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedReportId = $this->visibleReports()
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter === 'active' ? 'open' : $this->statusFilter))
            ->latest('last_message_at')->value('id');
        $this->markSelectedRead();
    }

    public function openComposer(): void
    {
        abort_unless($this->canCreate(), 403);
        $this->resetValidation();
        $this->showComposer = true;
    }

    public function closeComposer(): void
    {
        $this->showComposer = false;
        $this->reset(['contactKey', 'title', 'initialMessage']);
        $this->category = 'conduct';
        $this->severity = 'observation';
        $this->occurredOn = now()->toDateString();
        $this->resetValidation();
    }

    public function createReport(): void
    {
        abort_unless($this->canCreate(), 403);
        $this->validate([
            'contactKey' => ['required', 'string'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'severity' => ['required', Rule::in(['positive', 'observation', 'concern', 'urgent'])],
            'title' => ['required', 'string', 'max:180'],
            'occurredOn' => ['required', 'date', 'before_or_equal:today'],
            'initialMessage' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $contact = $this->contactOptions()->firstWhere('key', $this->contactKey);
        abort_unless($contact, 403, 'That student and teacher assignment is no longer available.');

        $report = DB::transaction(function () use ($contact) {
            $report = BehaviourReport::create([
                'school_id' => auth()->user()->school_id,
                'student_id' => $contact['student_id'],
                'parent_id' => $contact['parent_id'],
                'teacher_id' => $contact['teacher_id'],
                'subject_id' => $contact['subject_id'],
                'teacher_context' => $contact['context'],
                'category' => $this->category,
                'severity' => $this->severity,
                'title' => trim($this->title),
                'occurred_on' => $this->occurredOn,
                'status' => 'open',
                'initiated_by' => auth()->id(),
                'last_message_at' => now(),
            ]);
            $report->messages()->create([
                'author_id' => auth()->id(),
                'body' => trim($this->initialMessage),
            ]);

            return $report;
        });

        $this->selectedReportId = $report->id;
        $this->closeComposer();
        session()->flash('success', 'Behaviour report sent privately.');
    }

    public function selectReport(int $reportId): void
    {
        $this->visibleReports()->findOrFail($reportId);
        $this->selectedReportId = $reportId;
        $this->markSelectedRead();
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => ['required', 'string', 'min:2', 'max:5000']]);
        $report = $this->selectedReport();
        abort_if($report->status === 'resolved', 422, 'Reopen this report before replying.');

        DB::transaction(function () use ($report) {
            $report->messages()->create(['author_id' => auth()->id(), 'body' => trim($this->reply)]);
            $report->update(['last_message_at' => now()]);
        });
        $this->reply = '';
        session()->flash('success', 'Reply sent.');
    }

    public function resolveReport(): void
    {
        $report = $this->selectedReport();
        abort_unless($this->canResolve($report), 403);
        $report->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()]);
        session()->flash('success', 'Report marked as resolved. The conversation is now read-only.');
    }

    public function reopenReport(): void
    {
        $report = $this->selectedReport();
        abort_unless($this->canResolve($report), 403);
        $report->update(['status' => 'open', 'resolved_at' => null, 'resolved_by' => null]);
        session()->flash('success', 'Report reopened.');
    }

    protected function visibleReports(): Builder
    {
        $user = auth()->user();
        $query = BehaviourReport::query()->where('school_id', $user->school_id);
        if (! $user->can('manage behavioural reports')) {
            $query->where(fn (Builder $q) => $q->where('parent_id', $user->id)->orWhere('teacher_id', $user->id));
        }

        return $query;
    }

    protected function selectedReport(): BehaviourReport
    {
        abort_unless($this->selectedReportId, 404);

        return $this->visibleReports()->findOrFail($this->selectedReportId);
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['teacher', 'parent']) === true;
    }

    protected function canResolve(BehaviourReport $report): bool
    {
        return auth()->user()->can('manage behavioural reports') || $report->teacher_id === auth()->id();
    }

    protected function markSelectedRead(): void
    {
        if (! $this->selectedReportId) return;
        $report = $this->visibleReports()->find($this->selectedReportId);
        $report?->messages()->where('author_id', '!=', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
    }

    protected function contactOptions(): Collection
    {
        $user = auth()->user();
        if ($user->hasRole('parent')) return $this->parentContactOptions($user);
        if ($user->hasRole('teacher')) return $this->teacherContactOptions($user);

        return collect();
    }

    protected function parentContactOptions(User $parent): Collection
    {
        $options = collect();
        $children = $parent->children()->where('users.school_id', $parent->school_id)
            ->with('studentRecord.baseClass')->orderBy('users.name')->get();
        foreach ($children as $student) {
            $classId = $student->studentRecord?->my_class_id;
            if (! $classId) continue;
            foreach (DB::table('class_teacher')->join('users', 'users.id', '=', 'class_teacher.teacher_id')
                ->where('class_teacher.class_id', $classId)->where('users.school_id', $parent->school_id)->get() as $assignment) {
                $options->push($this->contactRow($student, $parent->id, $assignment->teacher_id, 'class', null, $assignment->name.' · Class teacher'));
            }
            $subjectTeachers = DB::table('subject_teacher as st')->join('subjects as s', 's.id', '=', 'st.subject_id')
                ->join('users as u', 'u.id', '=', 'st.user_id')->join('class_subject as cs', function ($join) use ($classId) {
                    $join->on('cs.subject_id', '=', 'st.subject_id')->where('cs.my_class_id', '=', $classId);
                })->where('st.school_id', $parent->school_id)
                ->where(fn ($q) => $q->where('st.my_class_id', $classId)->orWhere('st.is_general', true))
                ->select('st.user_id as teacher_id', 'st.subject_id', 'u.name as teacher_name', 's.name as subject_name')->distinct()->get();
            foreach ($subjectTeachers as $assignment) {
                $options->push($this->contactRow($student, $parent->id, $assignment->teacher_id, 'subject', $assignment->subject_id, $assignment->teacher_name.' · '.$assignment->subject_name));
            }
        }

        return $options->unique('key')->values();
    }

    protected function teacherContactOptions(User $teacher): Collection
    {
        $classAssignments = DB::table('class_teacher')->where('teacher_id', $teacher->id)->pluck('class_id')->map(fn ($id) => (int) $id);
        $subjectAssignments = DB::table('subject_teacher')->where('user_id', $teacher->id)->where('school_id', $teacher->school_id)->get();
        $classIds = $classAssignments->merge($subjectAssignments->whereNotNull('my_class_id')->pluck('my_class_id'));
        foreach ($subjectAssignments->where('is_general', true) as $general) {
            $classIds = $classIds->merge(DB::table('class_subject')->where('subject_id', $general->subject_id)->pluck('my_class_id'));
        }
        $records = StudentRecord::query()->whereIn('my_class_id', $classIds->unique())->whereHas('user', fn ($q) => $q->where('school_id', $teacher->school_id))
            ->with(['user.parents', 'baseClass'])->get();
        $options = collect();
        foreach ($records as $record) {
            $student = $record->user;
            foreach ($student?->parents ?? [] as $parent) {
                if ($classAssignments->contains((int) $record->my_class_id)) {
                    $options->push($this->contactRow($student, $parent->id, $teacher->id, 'class', null, $parent->name.' · Class teacher'));
                }
                foreach ($subjectAssignments as $assignment) {
                    $matchesClass = (int) $assignment->my_class_id === (int) $record->my_class_id;
                    $generalMatches = $assignment->is_general && DB::table('class_subject')->where('my_class_id', $record->my_class_id)->where('subject_id', $assignment->subject_id)->exists();
                    if (! $matchesClass && ! $generalMatches) continue;
                    $subjectName = DB::table('subjects')->where('id', $assignment->subject_id)->value('name');
                    $options->push($this->contactRow($student, $parent->id, $teacher->id, 'subject', $assignment->subject_id, $parent->name.' · '.$subjectName));
                }
            }
        }

        return $options->unique('key')->sortBy('label')->values();
    }

    protected function contactRow(User $student, int $parentId, int $teacherId, string $context, ?int $subjectId, string $detail): array
    {
        return [
            'key' => implode(':', [$student->id, $parentId, $teacherId, $context, $subjectId ?? 0]),
            'student_id' => $student->id, 'parent_id' => $parentId, 'teacher_id' => $teacherId,
            'context' => $context, 'subject_id' => $subjectId,
            'label' => $student->name.' — '.$detail,
        ];
    }

    protected function categories(): array
    {
        return ['conduct' => 'Conduct', 'participation' => 'Participation', 'relationships' => 'Peer relationships', 'attendance' => 'Attendance & punctuality', 'wellbeing' => 'Wellbeing', 'commendation' => 'Positive recognition', 'other' => 'Other'];
    }

    public function render()
    {
        $reports = $this->visibleReports()->with(['student:id,name', 'parent:id,name', 'teacher:id,name', 'subject:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('author_id', '!=', auth()->id())->whereNull('read_at')])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter === 'active' ? 'open' : $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', '%'.$this->search.'%')->orWhereHas('student', fn ($student) => $student->where('name', 'like', '%'.$this->search.'%'))))
            ->latest('last_message_at')->paginate(15);
        $selectedReport = $this->selectedReportId ? $this->visibleReports()->with(['student.studentRecord.baseClass', 'parent', 'teacher', 'subject', 'initiator', 'resolver', 'messages.author'])->find($this->selectedReportId) : null;

        return view('livewire.behaviour.behaviour-report-hub', [
            'reports' => $reports, 'selectedReport' => $selectedReport,
            'contactOptions' => $this->canCreate() ? $this->contactOptions() : collect(),
            'categories' => $this->categories(), 'canCreate' => $this->canCreate(),
            'canResolve' => $selectedReport ? $this->canResolve($selectedReport) : false,
        ])->layout('layouts.dashboard', [
            'breadcrumbs' => [['href' => route('dashboard'), 'text' => 'Dashboard'], ['href' => route('behaviour-reports.index'), 'text' => 'Behaviour Reports', 'active' => true]],
        ])->title('Behaviour Reports');
    }
}
