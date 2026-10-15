<?php

namespace App\Livewire\Support;

use App\Models\StudentRecord;
use App\Models\StudentSupportAction;
use App\Models\StudentSupportCase;
use App\Models\User;
use App\Services\StudentSupportNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StudentSupportHub extends Component
{
    public ?int $selectedCaseId = null;
    public bool $showComposer = false;
    public string $contactKey = '';
    public string $category = 'academic';
    public string $priority = 'medium';
    public string $title = '';
    public string $concern = '';
    public string $goal = '';
    public string $reviewOn = '';
    public string $statusFilter = 'active';
    public string $search = '';
    public string $updateProgress = 'note';
    public string $updateNote = '';
    public string $actionOwner = 'teacher';
    public string $actionTitle = '';
    public string $actionDetails = '';
    public string $actionDueOn = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access student support'), 403);
        $this->reviewOn = now()->addWeeks(2)->toDateString();
        $this->actionDueOn = now()->addWeek()->toDateString();
        $this->selectedCaseId = $this->visibleCases()->whereNotIn('status', ['completed'])->latest('updated_at')->value('id')
            ?? $this->visibleCases()->latest('updated_at')->value('id');
    }

    public function openComposer(): void
    {
        abort_unless($this->canOpen(), 403);
        $this->showComposer = true;
        $this->resetValidation();
    }

    public function closeComposer(): void
    {
        $this->showComposer = false;
        $this->reset(['contactKey', 'title', 'concern', 'goal']);
        $this->category = 'academic'; $this->priority = 'medium';
        $this->reviewOn = now()->addWeeks(2)->toDateString();
    }

    public function createCase(StudentSupportNotificationService $notifications): void
    {
        abort_unless($this->canOpen(), 403);
        $this->validate([
            'contactKey' => ['required'], 'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'title' => ['required', 'string', 'max:180'], 'concern' => ['required', 'string', 'min:10', 'max:5000'],
            'goal' => ['required', 'string', 'min:5', 'max:3000'], 'reviewOn' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $contact = $this->caseContacts()->firstWhere('key', $this->contactKey);
        abort_unless($contact, 403, 'That parent–teacher assignment is no longer available.');
        $case = DB::transaction(function () use ($contact) {
            $case = StudentSupportCase::create([
                'school_id' => auth()->user()->school_id, 'student_id' => $contact['student_id'],
                'parent_id' => $contact['parent_id'], 'lead_teacher_id' => $contact['teacher_id'], 'opened_by' => auth()->id(),
                'source_type' => auth()->user()->hasRole('parent') ? 'parent_concern' : 'manual',
                'category' => $this->category, 'priority' => $this->priority, 'title' => trim($this->title),
                'concern' => trim($this->concern), 'goal' => trim($this->goal), 'status' => 'monitoring', 'review_on' => $this->reviewOn,
                'parent_acknowledged_at' => auth()->user()->hasRole('parent') ? now() : null,
            ]);
            $case->updates()->create(['author_id' => auth()->id(), 'progress' => 'opened', 'note' => 'Support plan opened with a review date of '.now()->parse($this->reviewOn)->format('j M Y').'.', 'parent_visible' => true]);
            return $case;
        });
        $result = $notifications->notifyBoth($case, 'opened', (string) $case->id);
        $this->selectedCaseId = $case->id; $this->closeComposer();
        $this->flashMailResult('Support plan opened.', $result);
    }

    public function selectCase(int $caseId): void { $this->visibleCases()->findOrFail($caseId); $this->selectedCaseId = $caseId; }

    public function acknowledge(StudentSupportNotificationService $notifications): void
    {
        $case = $this->selectedCase();
        abort_unless($case->parent_id === auth()->id(), 403);
        $case->update(['parent_acknowledged_at' => now()]);
        $case->updates()->create(['author_id' => auth()->id(), 'progress' => 'acknowledged', 'note' => 'Parent acknowledged the support plan.', 'parent_visible' => true]);
        $this->flashMailResult('Plan acknowledged.', $notifications->notifyBoth($case, 'updated', 'ack-'.$case->updated_at->timestamp));
    }

    public function addUpdate(StudentSupportNotificationService $notifications): void
    {
        $case = $this->selectedCase();
        $this->validate(['updateProgress' => ['required', Rule::in(['note', 'improving', 'no_change', 'concern', 'escalated'])], 'updateNote' => ['required', 'string', 'min:3', 'max:5000']]);
        $update = $case->updates()->create(['author_id' => auth()->id(), 'progress' => $this->updateProgress, 'note' => trim($this->updateNote), 'parent_visible' => true]);
        $case->touch(); $this->updateNote = ''; $this->updateProgress = 'note';
        $this->flashMailResult('Progress update added.', $notifications->notifyBoth($case, 'updated', 'update-'.$update->id));
    }

    public function addAction(StudentSupportNotificationService $notifications): void
    {
        abort_unless($this->canManage(), 403);
        $case = $this->selectedCase();
        $this->validate(['actionOwner' => ['required', Rule::in(['teacher', 'parent'])], 'actionTitle' => ['required', 'string', 'max:180'], 'actionDetails' => ['nullable', 'string', 'max:2000'], 'actionDueOn' => ['required', 'date', 'after_or_equal:today']]);
        $action = $case->actions()->create(['owner_id' => $this->actionOwner === 'parent' ? $case->parent_id : $case->lead_teacher_id, 'title' => trim($this->actionTitle), 'details' => trim($this->actionDetails), 'due_on' => $this->actionDueOn]);
        $case->updates()->create(['author_id' => auth()->id(), 'progress' => 'action', 'note' => 'Action assigned: '.$action->title, 'parent_visible' => true]);
        $case->update(['status' => 'support_in_progress']);
        $this->actionTitle = ''; $this->actionDetails = ''; $this->actionDueOn = now()->addWeek()->toDateString();
        $this->flashMailResult('Action added.', $notifications->notifyBoth($case, 'updated', 'action-'.$action->id));
    }

    public function toggleAction(int $actionId, StudentSupportNotificationService $notifications): void
    {
        $case = $this->selectedCase(); $action = $case->actions()->findOrFail($actionId);
        abort_unless($action->owner_id === auth()->id() || $this->canManage(), 403);
        $complete = $action->status !== 'completed';
        $action->update(['status' => $complete ? 'completed' : 'pending', 'completed_at' => $complete ? now() : null]);
        $case->updates()->create(['author_id' => auth()->id(), 'progress' => 'action', 'note' => ($complete ? 'Completed' : 'Reopened').' action: '.$action->title, 'parent_visible' => true]);
        $this->flashMailResult('Action updated.', $notifications->notifyBoth($case, 'updated', 'action-status-'.$action->id.'-'.$action->updated_at->timestamp));
    }

    public function changeStatus(string $status, StudentSupportNotificationService $notifications): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless(in_array($status, ['monitoring', 'support_in_progress', 'improving', 'escalated', 'completed'], true), 422);
        $case = $this->selectedCase();
        $case->update(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null]);
        $case->updates()->create(['author_id' => auth()->id(), 'progress' => 'status', 'note' => 'Plan status changed to '.str_replace('_', ' ', $status).'.', 'parent_visible' => true]);
        $this->flashMailResult('Plan status updated.', $notifications->notifyBoth($case, $status === 'completed' ? 'completed' : 'updated', 'status-'.$case->updated_at->timestamp));
    }

    protected function flashMailResult(string $message, array $result): void
    {
        session()->flash($result['failed'] ? 'warning' : 'success', $message.' Email sent to '.$result['sent'].' of 2 participants'.($result['failed'] ? '; failed deliveries are recorded for follow-up.' : '.'));
    }

    protected function visibleCases(): Builder
    {
        $user = auth()->user(); $query = StudentSupportCase::query()->where('school_id', $user->school_id);
        if (! $user->can('manage student support')) $query->where(fn ($q) => $q->where('parent_id', $user->id)->orWhere('lead_teacher_id', $user->id));
        return $query;
    }

    protected function selectedCase(): StudentSupportCase { abort_unless($this->selectedCaseId, 404); return $this->visibleCases()->findOrFail($this->selectedCaseId); }
    protected function canManage(): bool { return auth()->user()->can('manage student support'); }
    protected function canOpen(): bool { return $this->canManage() || auth()->user()->hasRole('parent'); }

    protected function caseContacts(): Collection
    {
        $user = auth()->user();
        if ($user->hasRole('parent')) return $this->parentCaseContacts($user);
        $rows = collect();
        $teacherIds = $this->canManage() && ! $user->hasRole('teacher')
            ? User::query()->inSchool($user->school_id)->role('teacher')->pluck('id') : collect([$user->id]);
        foreach ($teacherIds as $teacherId) {
            $classIds = DB::table('class_teacher')->where('teacher_id', $teacherId)->pluck('class_id');
            $subjectClasses = DB::table('subject_teacher')->where('user_id', $teacherId)->where('school_id', $user->school_id)->whereNotNull('my_class_id')->pluck('my_class_id');
            $generalSubjects = DB::table('subject_teacher')->where('user_id', $teacherId)->where('school_id', $user->school_id)->where('is_general', true)->pluck('subject_id');
            $classIds = $classIds->merge($subjectClasses)->merge(DB::table('class_subject')->whereIn('subject_id', $generalSubjects)->pluck('my_class_id'))->unique();
            $records = StudentRecord::whereIn('my_class_id', $classIds)->whereHas('user', fn ($q) => $q->where('school_id', $user->school_id))->with(['user.parents', 'baseClass'])->get();
            $teacher = User::find($teacherId);
            foreach ($records as $record) foreach ($record->user?->parents ?? [] as $parent) {
                $key = implode(':', [$record->user_id, $parent->id, $teacherId]);
                $rows->push(['key' => $key, 'student_id' => $record->user_id, 'parent_id' => $parent->id, 'teacher_id' => (int) $teacherId, 'label' => $record->user->name.' · '.$record->baseClass?->name.' — '.$teacher?->name.' / '.$parent->name]);
            }
        }
        return $rows->unique('key')->sortBy('label')->values();
    }

    protected function parentCaseContacts(User $parent): Collection
    {
        $rows = collect();
        $children = $parent->children()->where('users.school_id', $parent->school_id)->with('studentRecord')->orderBy('users.name')->get();
        foreach ($children as $student) {
            $classId = $student->studentRecord?->my_class_id;
            if (! $classId) continue;
            foreach (DB::table('class_teacher as ct')->join('users as u', 'u.id', '=', 'ct.teacher_id')->where('ct.class_id', $classId)->where('u.school_id', $parent->school_id)->get(['ct.teacher_id', 'u.name']) as $teacher) {
                $rows->push(['key' => implode(':', [$student->id, $parent->id, $teacher->teacher_id, 'class', 0]), 'student_id' => $student->id, 'parent_id' => $parent->id, 'teacher_id' => (int) $teacher->teacher_id, 'label' => $student->name.' — '.$teacher->name.' · Class teacher']);
            }
            $subjectTeachers = DB::table('subject_teacher as st')->join('users as u', 'u.id', '=', 'st.user_id')->join('subjects as s', 's.id', '=', 'st.subject_id')
                ->join('class_subject as cs', function ($join) use ($classId) { $join->on('cs.subject_id', '=', 'st.subject_id')->where('cs.my_class_id', '=', $classId); })
                ->where('st.school_id', $parent->school_id)->where(fn ($q) => $q->where('st.my_class_id', $classId)->orWhere('st.is_general', true))
                ->select('st.user_id as teacher_id', 'st.subject_id', 'u.name as teacher_name', 's.name as subject_name')->distinct()->get();
            foreach ($subjectTeachers as $teacher) {
                $rows->push(['key' => implode(':', [$student->id, $parent->id, $teacher->teacher_id, 'subject', $teacher->subject_id]), 'student_id' => $student->id, 'parent_id' => $parent->id, 'teacher_id' => (int) $teacher->teacher_id, 'label' => $student->name.' — '.$teacher->teacher_name.' · '.$teacher->subject_name]);
            }
        }

        return $rows->unique('key')->values();
    }

    protected function signals(?StudentSupportCase $case): array
    {
        if (! $case) return [];
        $recordId = StudentRecord::where('user_id', $case->student_id)->value('id'); if (! $recordId) return [];
        $since = now()->subDays(30)->toDateString();
        $absence = DB::table('attendance_records as ar')->join('attendance_sessions as s', 's.id', '=', 'ar.attendance_session_id')->where('ar.student_record_id', $recordId)->where('s.attendance_date', '>=', $since)->whereIn('ar.status', ['absent', 'late'])->count();
        $discipline = DB::table('discipline_incidents')->where('student_record_id', $recordId)->whereNull('resolved_at')->count();
        $missing = DB::table('assignment_recipients as r')->join('assignments as a', 'a.id', '=', 'r.assignment_id')->leftJoin('assignment_submissions as sub', fn ($j) => $j->on('sub.assignment_id', '=', 'a.id')->on('sub.student_record_id', '=', 'r.student_record_id'))->where('r.student_record_id', $recordId)->where('a.due_at', '<', now())->whereNull('sub.id')->count();
        $latestResultPeriod = DB::table('results')->where('student_record_id', $recordId)->where('approved', true)
            ->orderByDesc('academic_year_id')->orderByDesc('semester_id')->first(['academic_year_id', 'semester_id']);
        $average = $latestResultPeriod
            ? DB::table('results')->where('student_record_id', $recordId)->where('approved', true)
                ->where('academic_year_id', $latestResultPeriod->academic_year_id)->where('semester_id', $latestResultPeriod->semester_id)->avg('total_score')
            : null;
        $behaviour = DB::table('behaviour_reports')->where('student_id', $case->student_id)->where('status', 'open')->count();
        return [
            ['label' => 'Absence or lateness', 'value' => $absence, 'tone' => $absence >= 3 ? 'danger' : 'neutral', 'period' => 'Last 30 days'],
            ['label' => 'Missing assignments', 'value' => $missing, 'tone' => $missing >= 2 ? 'danger' : 'neutral', 'period' => 'Currently overdue'],
            ['label' => 'Open behaviour reports', 'value' => $behaviour, 'tone' => $behaviour ? 'warning' : 'neutral', 'period' => 'Active'],
            ['label' => 'Unresolved incidents', 'value' => $discipline, 'tone' => $discipline ? 'warning' : 'neutral', 'period' => 'Active'],
            ['label' => 'Latest average', 'value' => $average !== null ? number_format((float) $average, 1).'%' : '—', 'tone' => $average !== null && $average < 50 ? 'danger' : 'neutral', 'period' => 'Published result'],
        ];
    }

    protected function categories(): array { return ['academic' => 'Academic progress', 'attendance' => 'Attendance', 'behaviour' => 'Behaviour', 'wellbeing' => 'Wellbeing', 'engagement' => 'Engagement', 'combined' => 'Combined support']; }

    public function render()
    {
        $cases = $this->visibleCases()->with(['student:id,name', 'parent:id,name', 'leadTeacher:id,name'])->withCount(['actions as pending_actions_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('status', '!=', 'completed'))->when($this->statusFilter === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', '%'.$this->search.'%')->orWhereHas('student', fn ($student) => $student->where('name', 'like', '%'.$this->search.'%'))))->latest('updated_at')->get();
        $case = $this->selectedCaseId ? $this->visibleCases()->with(['student.studentRecord.baseClass', 'parent', 'leadTeacher', 'openedBy', 'actions.owner', 'updates' => fn ($q) => $q->when(auth()->user()->hasRole('parent'), fn ($u) => $u->where('parent_visible', true))->with('author')->latest()])->find($this->selectedCaseId) : null;
        return view('livewire.support.student-support-hub', ['cases' => $cases, 'case' => $case, 'contacts' => $this->canOpen() ? $this->caseContacts() : collect(), 'categories' => $this->categories(), 'signals' => $this->signals($case), 'canManage' => $this->canManage(), 'canOpen' => $this->canOpen()])
            ->layout('layouts.dashboard', ['breadcrumbs' => [['href' => route('dashboard'), 'text' => 'Dashboard'], ['href' => route('student-support.index'), 'text' => 'Student Support Centre', 'active' => true]]])->title('Student Support Centre');
    }
}
