<?php

namespace App\Livewire\Interventions;

use App\Models\InterventionRule;
use App\Models\StudentInterventionAlert;
use App\Models\User;
use App\Services\InterventionAlertService;
use App\Services\StudentRecordAccessService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SmartInterventionHub extends Component
{
    public ?int $selectedAlertId = null;

    public string $statusFilter = 'active';

    public string $severityFilter = '';

    public string $ruleName = '';

    public string $indicator = 'attendance_absences';

    public string $operator = '>=';

    public string $threshold = '3';

    public int $lookbackDays = 30;

    public int $minimumExpectedRecords = 5;

    public string $severity = 'medium';

    public string $ruleExplanation = '';

    public string $reviewNote = '';

    public string $dismissalReason = '';

    public string $followUpOwnerId = '';

    public string $followUpTitle = '';

    public string $followUpNotes = '';

    public string $followUpDueOn = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access intervention alerts'), 403);
        $this->followUpDueOn = now()->addWeek()->toDateString();
        $this->selectedAlertId = $this->visibleAlerts()->whereNotIn('status', ['dismissed', 'resolved'])->latest('detected_at')->value('id');
    }

    public function createRule(): void
    {
        abort_unless(auth()->user()->can('manage intervention rules'), 403);
        $data = $this->validate([
            'ruleName' => ['required', 'string', 'max:160'], 'indicator' => ['required', Rule::in(array_keys($this->indicators()))],
            'operator' => ['required', Rule::in(['>=', '>', '<=', '<', '='])], 'threshold' => ['required', 'numeric', 'min:0', 'max:1000'],
            'lookbackDays' => ['required', 'integer', 'min:1', 'max:365'], 'minimumExpectedRecords' => ['required', 'integer', 'min:1', 'max:500'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])], 'ruleExplanation' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        InterventionRule::create(['school_id' => auth()->user()->school_id, 'name' => trim($data['ruleName']), 'indicator' => $data['indicator'], 'operator' => $data['operator'], 'threshold' => $data['threshold'], 'lookback_days' => $data['lookbackDays'], 'minimum_expected_records' => $data['minimumExpectedRecords'], 'severity' => $data['severity'], 'explanation' => trim($data['ruleExplanation']), 'created_by' => auth()->id()]);
        $this->reset(['ruleName', 'ruleExplanation']);
        session()->flash('success', 'Transparent alert rule saved. It supports human review only.');
    }

    public function generate(InterventionAlertService $service): void
    {
        abort_unless(auth()->user()->can('generate intervention alerts'), 403);
        $result = $service->generateForSchool((int) auth()->user()->school_id);
        session()->flash('success', "Checked {$result['checked']} student-rule combinations; created {$result['created']} alerts. {$result['incomplete']} were skipped because the required source data was incomplete.");
    }

    public function selectAlert(int $alertId): void
    {
        $this->selectedAlertId = $this->visibleAlerts()->findOrFail($alertId)->id;
        $this->reset(['reviewNote', 'dismissalReason']);
    }

    public function review(): void
    {
        $alert = $this->selectedAlert();
        $data = $this->validate(['reviewNote' => ['required', 'string', 'min:3', 'max:3000']]);
        $alert->update(['status' => 'reviewed', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => trim($data['reviewNote'])]);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'reviewed', 'note' => trim($data['reviewNote'])]);
        $this->reviewNote = '';
        session()->flash('success', 'Alert reviewed by a person. No disciplinary action was taken automatically.');
    }

    public function dismiss(): void
    {
        $alert = $this->selectedAlert();
        $data = $this->validate(['dismissalReason' => ['required', 'string', 'min:5', 'max:3000']]);
        $alert->update(['status' => 'dismissed', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'dismissal_reason' => trim($data['dismissalReason'])]);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'dismissed', 'note' => trim($data['dismissalReason'])]);
        $this->dismissalReason = '';
        session()->flash('success', 'Alert dismissed with a recorded reason.');
    }

    public function addFollowUp(): void
    {
        $alert = $this->selectedAlert();
        $data = $this->validate(['followUpOwnerId' => ['required', 'integer'], 'followUpTitle' => ['required', 'string', 'max:180'], 'followUpNotes' => ['nullable', 'string', 'max:3000'], 'followUpDueOn' => ['required', 'date', 'after_or_equal:today']]);
        $owner = $this->staffUsers()->findOrFail($data['followUpOwnerId']);
        $followUp = $alert->followUps()->create(['owner_id' => $owner->id, 'title' => trim($data['followUpTitle']), 'notes' => trim($data['followUpNotes']), 'due_on' => $data['followUpDueOn']]);
        $alert->update(['status' => 'in_progress']);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'follow_up', 'note' => "Assigned '{$followUp->title}' to {$owner->name}, due {$followUp->due_on->format('j M Y')}."]);
        $this->reset(['followUpOwnerId', 'followUpTitle', 'followUpNotes']);
        $this->followUpDueOn = now()->addWeek()->toDateString();
        session()->flash('success', 'Follow-up task assigned.');
    }

    public function completeFollowUp(int $followUpId): void
    {
        $alert = $this->selectedAlert();
        $followUp = $alert->followUps()->findOrFail($followUpId);
        abort_unless($followUp->owner_id === auth()->id() || auth()->user()->can('manage intervention alerts'), 403);
        $followUp->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => auth()->id()]);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'follow_up_completed', 'note' => "Completed follow-up: {$followUp->title}."]);
        session()->flash('success', 'Follow-up completed.');
    }

    public function escalateFollowUp(int $followUpId): void
    {
        abort_unless(auth()->user()->can('manage intervention alerts'), 403);
        $alert = $this->selectedAlert();
        $followUp = $alert->followUps()->findOrFail($followUpId);
        $followUp->update(['status' => 'escalated', 'escalated_at' => now(), 'escalated_by' => auth()->id()]);
        $alert->update(['status' => 'escalated', 'severity' => $alert->severity === 'urgent' ? 'urgent' : 'high']);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'escalated', 'note' => "Escalated follow-up: {$followUp->title}."]);
        session()->flash('success', 'Follow-up escalated for human attention.');
    }

    public function resolve(): void
    {
        $alert = $this->selectedAlert();
        abort_if($alert->followUps()->whereIn('status', ['pending', 'escalated'])->exists(), 422, 'Complete outstanding follow-up tasks before resolving this alert.');
        $data = $this->validate(['reviewNote' => ['required', 'string', 'min:5', 'max:3000']]);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $alert->updates()->create(['author_id' => auth()->id(), 'event' => 'resolved', 'note' => trim($data['reviewNote'])]);
        $this->reviewNote = '';
        session()->flash('success', 'Alert resolved with a preserved history.');
    }

    private function selectedAlert(): StudentInterventionAlert
    {
        abort_unless($this->selectedAlertId, 404);

        return $this->visibleAlerts()->findOrFail($this->selectedAlertId);
    }

    private function visibleAlerts()
    {
        $studentIds = app(StudentRecordAccessService::class)->permittedStudents(auth()->user(), 'timeline')->pluck('id');

        return StudentInterventionAlert::query()->where('school_id', auth()->user()->school_id)->whereIn('student_id', $studentIds);
    }

    private function staffUsers()
    {
        return User::query()->where('school_id', auth()->user()->school_id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['teacher', 'principal', 'admin', 'super-admin', 'super_admin']));
    }

    public function indicators(): array
    {
        return ['attendance_absences' => 'Absence count', 'attendance_lateness' => 'Lateness count', 'missing_assignments' => 'Missing assignment count', 'assessment_average' => 'Approved assessment average'];
    }

    public function render()
    {
        $alerts = $this->visibleAlerts()->with(['student:id,name', 'rule:id,name,indicator,operator,threshold,explanation'])
            ->when($this->statusFilter === 'active', fn ($q) => $q->whereNotIn('status', ['dismissed', 'resolved']))
            ->when($this->statusFilter !== 'active' && $this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->severityFilter !== '', fn ($q) => $q->where('severity', $this->severityFilter))->latest('detected_at')->get();
        $alert = $this->selectedAlertId ? $this->visibleAlerts()->with(['student:id,name', 'rule', 'followUps.owner:id,name', 'updates.author:id,name'])->find($this->selectedAlertId) : null;
        $rules = InterventionRule::query()->where('school_id', auth()->user()->school_id)->orderBy('name')->get();
        $staff = $this->staffUsers()->orderBy('name')->get(['id', 'name']);
        $indicators = $this->indicators();

        return view('livewire.interventions.smart-intervention-hub', compact('alerts', 'alert', 'rules', 'staff', 'indicators'))->layout('layouts.dashboard')->title('Smart Intervention Alerts');
    }
}
