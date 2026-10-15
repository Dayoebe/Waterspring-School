<?php

namespace App\Livewire\Clubs;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubTermPlan;
use App\Models\ClubWeeklyAgenda;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClubHub extends Component
{
    public string $search = '';

    public ?int $selectedClubId = null;

    public bool $showClubForm = false;

    public ?int $editingClubId = null;

    public string $name = '';

    public string $category = '';

    public string $description = '';

    public string $meetingDay = '';

    public string $meetingTime = '';

    public string $meetingLocation = '';

    public ?int $capacity = null;

    public string $colour = 'sky';

    public bool $isActive = true;

    public ?int $selectedInstructorId = null;

    public ?int $selectedStudentId = null;

    public bool $showActivityForm = false;

    public ?int $editingActivityId = null;

    public string $activityTitle = '';

    public string $activityDescription = '';

    public string $activityStartsAt = '';

    public string $activityEndsAt = '';

    public string $activityLocation = '';

    public string $activityStatus = 'scheduled';

    public bool $showPlanForm = false;

    public string $planGoal = '';

    public string $planObjectives = '';

    public string $planStrengths = '';

    public string $planFocusAreas = '';

    public string $planResourcesNeeded = '';

    public string $planSuccessIndicators = '';

    public string $planNotes = '';

    public bool $publishPlan = true;

    public bool $showAgendaForm = false;

    public ?int $editingAgendaId = null;

    public int $agendaWeekNumber = 1;

    public string $agendaScheduledFor = '';

    public string $agendaTitle = '';

    public string $agendaDetails = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view clubs'), 403);
        $this->selectedClubId = $this->visibleClubQuery()->value('id');
    }

    public function selectClub(int $clubId): void
    {
        $this->visibleClubQuery()->findOrFail($clubId);
        $this->selectedClubId = $clubId;
        $this->resetClubForms();
    }

    public function createClub(): void
    {
        $this->ensureCanManageAll();
        $this->resetClubForm();
        $this->showClubForm = true;
    }

    public function editClub(int $clubId): void
    {
        $this->ensureCanManageAll();
        $club = Club::query()->findOrFail($clubId);
        $this->editingClubId = $club->id;
        $this->name = $club->name;
        $this->category = (string) $club->category;
        $this->description = $club->description;
        $this->meetingDay = (string) $club->meeting_day;
        $this->meetingTime = $club->meeting_time ? substr($club->meeting_time, 0, 5) : '';
        $this->meetingLocation = (string) $club->meeting_location;
        $this->capacity = $club->capacity;
        $this->colour = $club->colour;
        $this->isActive = $club->is_active;
        $this->showClubForm = true;
    }

    public function saveClub(): void
    {
        $this->ensureCanManageAll();
        $schoolId = (int) auth()->user()->school_id;
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('clubs')->where('school_id', $schoolId)->ignore($this->editingClubId)],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:5000'],
            'meetingDay' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])],
            'meetingTime' => ['nullable', 'date_format:H:i'],
            'meetingLocation' => ['nullable', 'string', 'max:180'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'colour' => ['required', Rule::in(['sky', 'violet', 'emerald', 'amber', 'rose', 'indigo'])],
            'isActive' => ['boolean'],
        ]);

        $club = $this->editingClubId ? Club::query()->findOrFail($this->editingClubId) : new Club(['created_by' => auth()->id()]);
        $club->fill([
            'school_id' => $schoolId, 'name' => trim($validated['name']), 'category' => trim($validated['category']) ?: null,
            'description' => trim($validated['description']), 'meeting_day' => $validated['meetingDay'] ?: null,
            'meeting_time' => $validated['meetingTime'] ?: null, 'meeting_location' => trim($validated['meetingLocation']) ?: null,
            'capacity' => $validated['capacity'], 'colour' => $validated['colour'], 'is_active' => $validated['isActive'],
        ])->save();

        $this->selectedClubId = $club->id;
        $this->resetClubForm();
        session()->flash('success', 'Club saved successfully.');
    }

    public function deleteClub(int $clubId): void
    {
        $this->ensureCanManageAll();
        Club::query()->findOrFail($clubId)->delete();
        $this->selectedClubId = $this->visibleClubQuery()->value('id');
        session()->flash('success', 'Club and its related records were deleted.');
    }

    public function joinClub(int $clubId): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('student') && $user->can('join clubs'), 403);
        $club = Club::query()->where('is_active', true)->findOrFail($clubId);
        $activeCount = $club->members()->wherePivot('status', 'active')->count();
        abort_if($club->capacity && $activeCount >= $club->capacity && ! $club->members()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 422, 'This club has reached its capacity.');
        $club->members()->syncWithoutDetaching([$user->id => ['joined_on' => now()->toDateString(), 'status' => 'active', 'added_by' => $user->id]]);
        $club->members()->updateExistingPivot($user->id, ['status' => 'active', 'joined_on' => now()->toDateString(), 'added_by' => $user->id]);
        $this->selectedClubId = $club->id;
        session()->flash('success', 'You have joined '.$club->name.'.');
    }

    public function leaveClub(int $clubId): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('student') && $user->can('join clubs'), 403);
        Club::query()->findOrFail($clubId)->members()->updateExistingPivot($user->id, ['status' => 'left']);
        session()->flash('success', 'You have left the club.');
    }

    public function addInstructor(): void
    {
        $this->ensureCanManageAll();
        $this->validate(['selectedInstructorId' => ['required', 'integer']]);
        $club = Club::query()->findOrFail($this->selectedClubId);
        $teacher = $this->teacherQuery()->findOrFail($this->selectedInstructorId);
        $club->instructors()->syncWithoutDetaching([$teacher->id => ['is_lead' => $club->instructors()->count() === 0]]);
        $this->selectedInstructorId = null;
        session()->flash('success', 'Club instructor assigned.');
    }

    public function removeInstructor(int $teacherId): void
    {
        $this->ensureCanManageAll();
        Club::query()->findOrFail($this->selectedClubId)->instructors()->detach($teacherId);
        session()->flash('success', 'Club instructor removed.');
    }

    public function addStudent(): void
    {
        $this->ensureCanManageAll();
        $this->validate(['selectedStudentId' => ['required', 'integer']]);
        $club = Club::query()->findOrFail($this->selectedClubId);
        $student = $this->studentQuery()->findOrFail($this->selectedStudentId);
        $activeCount = $club->members()->wherePivot('status', 'active')->count();
        if ($club->capacity && $activeCount >= $club->capacity && ! $club->members()->whereKey($student->id)->wherePivot('status', 'active')->exists()) {
            $this->addError('selectedStudentId', 'This club has reached its capacity.');

            return;
        }
        $club->members()->syncWithoutDetaching([$student->id => ['joined_on' => now()->toDateString(), 'status' => 'active', 'added_by' => auth()->id()]]);
        $club->members()->updateExistingPivot($student->id, ['status' => 'active', 'joined_on' => now()->toDateString(), 'added_by' => auth()->id()]);
        $this->selectedStudentId = null;
        session()->flash('success', 'Student added to the club.');
    }

    public function removeStudent(int $studentId): void
    {
        $this->ensureCanManageAll();
        Club::query()->findOrFail($this->selectedClubId)->members()->updateExistingPivot($studentId, ['status' => 'left']);
        session()->flash('success', 'Student removed from the active club list.');
    }

    public function createActivity(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        $this->resetActivityForm();
        $this->activityStartsAt = now()->addDay()->format('Y-m-d\TH:i');
        $this->showActivityForm = true;
    }

    public function editActivity(int $activityId): void
    {
        $activity = ClubActivity::query()->with('club')->findOrFail($activityId);
        $this->ensureCanManageClub($activity->club);
        $this->editingActivityId = $activity->id;
        $this->activityTitle = $activity->title;
        $this->activityDescription = (string) $activity->description;
        $this->activityStartsAt = $activity->starts_at->format('Y-m-d\TH:i');
        $this->activityEndsAt = $activity->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->activityLocation = (string) $activity->location;
        $this->activityStatus = $activity->status;
        $this->showActivityForm = true;
    }

    public function saveActivity(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        $validated = $this->validate([
            'activityTitle' => ['required', 'string', 'max:180'], 'activityDescription' => ['nullable', 'string', 'max:5000'],
            'activityStartsAt' => ['required', 'date'], 'activityEndsAt' => ['nullable', 'date', 'after_or_equal:activityStartsAt'],
            'activityLocation' => ['nullable', 'string', 'max:180'], 'activityStatus' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
        ]);
        $activity = $this->editingActivityId ? ClubActivity::query()->where('club_id', $club->id)->findOrFail($this->editingActivityId) : new ClubActivity(['club_id' => $club->id, 'created_by' => auth()->id()]);
        $activity->fill([
            'title' => trim($validated['activityTitle']), 'description' => trim($validated['activityDescription']) ?: null,
            'starts_at' => $validated['activityStartsAt'], 'ends_at' => $validated['activityEndsAt'] ?: null,
            'location' => trim($validated['activityLocation']) ?: null, 'status' => $validated['activityStatus'],
        ])->save();
        $this->resetActivityForm();
        session()->flash('success', 'Club activity saved.');
    }

    public function deleteActivity(int $activityId): void
    {
        $activity = ClubActivity::query()->with('club')->findOrFail($activityId);
        $this->ensureCanManageClub($activity->club);
        $activity->delete();
        $this->resetActivityForm();
        session()->flash('success', 'Club activity deleted.');
    }

    public function editTermPlan(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        $plan = $this->currentTermPlan($club);
        $this->planGoal = (string) $plan?->goal;
        $this->planObjectives = (string) $plan?->objectives;
        $this->planStrengths = (string) $plan?->strengths;
        $this->planFocusAreas = (string) $plan?->focus_areas;
        $this->planResourcesNeeded = (string) $plan?->resources_needed;
        $this->planSuccessIndicators = (string) $plan?->success_indicators;
        $this->planNotes = (string) $plan?->notes;
        $this->publishPlan = (bool) $plan?->published_at;
        $this->showPlanForm = true;
    }

    public function saveTermPlan(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        $user = auth()->user();
        abort_unless($user->school?->academic_year_id && $user->school?->semester_id, 422, 'Set the current academic year and term first.');
        $validated = $this->validate([
            'planGoal' => ['required', 'string', 'max:5000'],
            'planObjectives' => ['required', 'string', 'max:10000'],
            'planStrengths' => ['nullable', 'string', 'max:5000'],
            'planFocusAreas' => ['nullable', 'string', 'max:5000'],
            'planResourcesNeeded' => ['nullable', 'string', 'max:5000'],
            'planSuccessIndicators' => ['nullable', 'string', 'max:5000'],
            'planNotes' => ['nullable', 'string', 'max:5000'],
            'publishPlan' => ['boolean'],
        ]);

        $plan = ClubTermPlan::query()->firstOrNew([
            'club_id' => $club->id,
            'academic_year_id' => $user->school->academic_year_id,
            'semester_id' => $user->school->semester_id,
        ]);
        $plan->fill([
            'goal' => trim($validated['planGoal']), 'objectives' => trim($validated['planObjectives']),
            'strengths' => trim($validated['planStrengths']) ?: null, 'focus_areas' => trim($validated['planFocusAreas']) ?: null,
            'resources_needed' => trim($validated['planResourcesNeeded']) ?: null,
            'success_indicators' => trim($validated['planSuccessIndicators']) ?: null,
            'notes' => trim($validated['planNotes']) ?: null,
            'published_at' => $validated['publishPlan'] ? ($plan->published_at ?: now()) : null,
            'created_by' => $plan->created_by ?: $user->id, 'updated_by' => $user->id,
        ])->save();
        $this->showPlanForm = false;
        session()->flash('success', 'The club plan for this term has been saved.');
    }

    public function createAgenda(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        abort_unless($this->currentTermPlan($club), 422, 'Save the term plan before adding a weekly agenda.');
        $this->resetAgendaForm();
        $this->agendaWeekNumber = (($this->currentTermPlan($club)?->agendas()->max('week_number')) ?? 0) + 1;
        $this->showAgendaForm = true;
    }

    public function editAgenda(int $agendaId): void
    {
        $agenda = ClubWeeklyAgenda::query()->with('termPlan.club')->findOrFail($agendaId);
        $this->ensureCanManageClub($agenda->termPlan->club);
        $this->editingAgendaId = $agenda->id;
        $this->agendaWeekNumber = $agenda->week_number;
        $this->agendaScheduledFor = $agenda->scheduled_for?->format('Y-m-d') ?? '';
        $this->agendaTitle = $agenda->title;
        $this->agendaDetails = $agenda->agenda;
        $this->showAgendaForm = true;
    }

    public function saveAgenda(): void
    {
        $club = Club::query()->findOrFail($this->selectedClubId);
        $this->ensureCanManageClub($club);
        $plan = $this->currentTermPlan($club);
        abort_unless($plan, 422, 'Save the term plan before adding a weekly agenda.');
        $validated = $this->validate([
            'agendaWeekNumber' => ['required', 'integer', 'min:1', 'max:60', Rule::unique('club_weekly_agendas', 'week_number')->where('club_term_plan_id', $plan->id)->ignore($this->editingAgendaId)],
            'agendaScheduledFor' => ['nullable', 'date'], 'agendaTitle' => ['required', 'string', 'max:180'],
            'agendaDetails' => ['required', 'string', 'max:10000'],
        ]);
        $agenda = $this->editingAgendaId
            ? ClubWeeklyAgenda::query()->where('club_term_plan_id', $plan->id)->findOrFail($this->editingAgendaId)
            : new ClubWeeklyAgenda(['club_term_plan_id' => $plan->id, 'created_by' => auth()->id()]);
        $agenda->fill([
            'week_number' => $validated['agendaWeekNumber'], 'scheduled_for' => $validated['agendaScheduledFor'] ?: null,
            'title' => trim($validated['agendaTitle']), 'agenda' => trim($validated['agendaDetails']),
        ])->save();
        $this->resetAgendaForm();
        session()->flash('success', 'Weekly agenda saved.');
    }

    public function toggleAgendaDone(int $agendaId): void
    {
        $agenda = ClubWeeklyAgenda::query()->with('termPlan.club')->findOrFail($agendaId);
        $this->ensureCanManageClub($agenda->termPlan->club);
        $done = $agenda->status !== 'done';
        $agenda->update(['status' => $done ? 'done' : 'planned', 'completed_at' => $done ? now() : null, 'completed_by' => $done ? auth()->id() : null]);
        session()->flash('success', $done ? 'Weekly agenda marked as done.' : 'Weekly agenda reopened.');
    }

    public function deleteAgenda(int $agendaId): void
    {
        $agenda = ClubWeeklyAgenda::query()->with('termPlan.club')->findOrFail($agendaId);
        $this->ensureCanManageClub($agenda->termPlan->club);
        $agenda->delete();
        $this->resetAgendaForm();
        session()->flash('success', 'Weekly agenda deleted.');
    }

    public function cancelClubForm(): void
    {
        $this->resetClubForm();
    }

    public function cancelActivityForm(): void
    {
        $this->resetActivityForm();
    }

    public function cancelPlanForm(): void
    {
        $this->showPlanForm = false;
        $this->resetValidation();
    }

    public function cancelAgendaForm(): void
    {
        $this->resetAgendaForm();
    }

    protected function currentTermPlan(Club $club): ?ClubTermPlan
    {
        $school = auth()->user()->school;
        if (! $school?->academic_year_id || ! $school?->semester_id) {
            return null;
        }

        return $club->termPlans()->where('academic_year_id', $school->academic_year_id)->where('semester_id', $school->semester_id)->first();
    }

    protected function visibleClubQuery(): Builder
    {
        $user = auth()->user();
        $query = Club::query();
        if ($user->can('manage clubs')) {
            return $query;
        }
        if ($user->hasRole('teacher')) {
            return $query->whereHas('instructors', fn (Builder $q) => $q->where('users.id', $user->id));
        }
        if ($user->hasRole('parent')) {
            $childIds = $user->children()->pluck('users.id');

            return $query->whereHas('members', fn (Builder $q) => $q->whereIn('users.id', $childIds)->where('club_memberships.status', 'active'));
        }

        return $query->where('is_active', true);
    }

    protected function teacherQuery(): Builder
    {
        return User::query()->where('school_id', auth()->user()->school_id)->where('locked', false)->role('teacher')->orderBy('name');
    }

    protected function studentQuery(): Builder
    {
        return User::query()->where('school_id', auth()->user()->school_id)->where('locked', false)->role('student')->orderBy('name');
    }

    protected function ensureCanManageAll(): void
    {
        abort_unless(auth()->user()?->can('manage clubs'), 403);
    }

    protected function ensureCanManageClub(Club $club): void
    {
        $user = auth()->user();
        abort_unless($user?->can('manage clubs') || ($user?->can('manage assigned clubs') && $club->instructors()->whereKey($user->id)->exists()), 403);
    }

    protected function resetClubForms(): void
    {
        $this->resetClubForm();
        $this->resetActivityForm();
        $this->showPlanForm = false;
        $this->resetAgendaForm();
    }

    protected function resetClubForm(): void
    {
        $this->reset(['showClubForm', 'editingClubId', 'name', 'category', 'description', 'meetingDay', 'meetingTime', 'meetingLocation', 'capacity']);
        $this->colour = 'sky';
        $this->isActive = true;
        $this->resetValidation();
    }

    protected function resetActivityForm(): void
    {
        $this->reset(['showActivityForm', 'editingActivityId', 'activityTitle', 'activityDescription', 'activityStartsAt', 'activityEndsAt', 'activityLocation']);
        $this->activityStatus = 'scheduled';
        $this->resetValidation();
    }

    protected function resetAgendaForm(): void
    {
        $this->reset(['showAgendaForm', 'editingAgendaId', 'agendaScheduledFor', 'agendaTitle', 'agendaDetails']);
        $this->agendaWeekNumber = 1;
        $this->resetValidation();
    }

    public function render()
    {
        $user = auth()->user();
        $clubs = $this->visibleClubQuery()->withCount(['members as active_members_count' => fn (Builder $q) => $q->where('club_memberships.status', 'active')])
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('category', 'like', '%'.$this->search.'%')))
            ->orderByDesc('is_active')->orderBy('name')->get();
        $selectedClub = $this->selectedClubId ? $this->visibleClubQuery()->with([
            'instructors:id,name,email',
            'members' => fn ($q) => $q->wherePivot('status', 'active')
                ->when($user->hasRole('parent'), fn ($q) => $q->whereIn('users.id', $user->children()->pluck('users.id')))
                ->with('studentRecord.baseClass'),
            'activities' => fn ($q) => $q->orderByDesc('starts_at'),
            'termPlans' => fn ($q) => $q->where('academic_year_id', $user->school?->academic_year_id)
                ->where('semester_id', $user->school?->semester_id)->with(['academicYear', 'semester', 'agendas' => fn ($q) => $q->orderBy('week_number')]),
        ])->find($this->selectedClubId) : null;
        if (! $selectedClub && $clubs->isNotEmpty()) {
            $this->selectedClubId = $clubs->first()->id;
            $selectedClub = $this->visibleClubQuery()->with([
                'instructors:id,name,email',
                'members' => fn ($q) => $q->wherePivot('status', 'active')
                    ->when($user->hasRole('parent'), fn ($q) => $q->whereIn('users.id', $user->children()->pluck('users.id')))
                    ->with('studentRecord.baseClass'),
                'activities' => fn ($q) => $q->orderByDesc('starts_at'),
                'termPlans' => fn ($q) => $q->where('academic_year_id', $user->school?->academic_year_id)
                    ->where('semester_id', $user->school?->semester_id)->with(['academicYear', 'semester', 'agendas' => fn ($q) => $q->orderBy('week_number')]),
            ])->find($this->selectedClubId);
        }
        $isJoined = $selectedClub && $user->hasRole('student') ? $selectedClub->members()->whereKey($user->id)->wherePivot('status', 'active')->exists() : false;
        $canViewActivities = $selectedClub && ($user->can('manage clubs') || $user->hasRole('teacher') || $user->hasRole('parent') || $isJoined);
        $canManageSelected = $selectedClub && ($user->can('manage clubs') || ($user->can('manage assigned clubs') && $selectedClub->instructors->contains('id', $user->id)));
        $termPlan = $selectedClub?->termPlans->first();
        if (! $canViewActivities || (! $canManageSelected && ! $termPlan?->published_at)) {
            $termPlan = null;
        }

        return view('livewire.clubs.club-hub', [
            'clubs' => $clubs, 'selectedClub' => $selectedClub, 'isJoined' => $isJoined,
            'canViewActivities' => $canViewActivities, 'canManageAll' => $user->can('manage clubs'),
            'canManageSelected' => $canManageSelected,
            'termPlan' => $termPlan,
            'teachers' => $user->can('manage clubs') ? $this->teacherQuery()->get(['id', 'name']) : collect(),
            'students' => $user->can('manage clubs') ? $this->studentQuery()->get(['id', 'name']) : collect(),
            'children' => $user->hasRole('parent') ? $user->children()->orderBy('name')->get(['users.id', 'users.name']) : collect(),
        ])->layout('layouts.dashboard', [
            'breadcrumbs' => [['href' => route('dashboard'), 'text' => 'Dashboard'], ['href' => route('clubs.index'), 'text' => 'Clubs & Activities', 'active' => true]],
        ])->title('Clubs & Activities');
    }
}
