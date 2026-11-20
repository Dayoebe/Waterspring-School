<?php

namespace App\Livewire\Houses;

use App\Models\HouseBadge;
use App\Models\HouseChallenge;
use App\Models\HouseChallengeParticipation;
use App\Models\HousePointAward;
use App\Models\HousePointRule;
use App\Models\HousePointTransaction;
use App\Models\ParentRecord;
use App\Models\SchoolHouse;
use App\Models\SchoolHouseMembership;
use App\Models\StudentHouseBadge;
use App\Models\User;
use App\Services\HousePointService;
use App\Services\StudentRecordAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class HousePointsHub extends Component
{
    public string $houseName = '';

    public string $houseColour = '#2563eb';

    public string $houseMotto = '';

    public string $memberStudentId = '';

    public string $memberHouseId = '';

    public string $ruleName = '';

    public int $rulePoints = 5;

    public int $duplicateDays = 1;

    public string $ruleDescription = '';

    public bool $ruleRequiresApproval = false;

    public string $ruleTermLimit = '';

    public string $studentId = '';

    public string $ruleId = '';

    public int $points = 5;

    public string $awardKind = 'award';

    public string $awardReason = '';

    public string $occurredOn = '';

    public string $reviewNote = '';

    public string $challengeTitle = '';

    public string $challengeDescription = '';

    public string $challengeStartsAt = '';

    public string $challengeDeadlineAt = '';

    public int $challengePoints = 0;

    public string $badgeName = '';

    public string $badgeDescription = '';

    public string $badgeMinimumPoints = '';

    public string $badgeId = '';

    public string $badgeStudentId = '';

    public string $badgeReason = '';

    public string $resultScore = '';

    public string $resultPosition = '';

    public string $resultNote = '';

    public string $rankingPeriod = 'term';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view house points'), 403);
        $this->occurredOn = today()->toDateString();
        $this->challengeStartsAt = now()->format('Y-m-d\TH:i');
        $this->challengeDeadlineAt = now()->addWeek()->format('Y-m-d\TH:i');
    }

    public function createHouse(): void
    {
        abort_unless(auth()->user()->can('manage houses'), 403);
        $data = $this->validate(['houseName' => ['required', 'string', 'max:100'], 'houseColour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'houseMotto' => ['nullable', 'string', 'max:180']]);
        SchoolHouse::create(['school_id' => auth()->user()->school_id, 'name' => trim($data['houseName']), 'colour' => $data['houseColour'], 'motto' => trim($data['houseMotto']), 'created_by' => auth()->id()]);
        $this->reset(['houseName', 'houseMotto']);
        session()->flash('success', 'House created.');
    }

    public function assignMember(): void
    {
        abort_unless(auth()->user()->can('manage houses'), 403);
        $student = $this->manageableStudents()->findOrFail($this->memberStudentId);
        $house = SchoolHouse::query()->where('school_id', auth()->user()->school_id)->where('active', true)->findOrFail($this->memberHouseId);
        $yearId = auth()->user()->school?->academic_year_id;
        abort_unless($yearId, 422, 'Select an academic year first.');
        SchoolHouseMembership::updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $yearId], ['school_house_id' => $house->id, 'joined_on' => today(), 'left_on' => null, 'assigned_by' => auth()->id()]);
        $this->reset(['memberStudentId', 'memberHouseId']);
        session()->flash('success', 'House membership saved for the active academic year.');
    }

    public function createRule(): void
    {
        abort_unless(auth()->user()->can('manage houses'), 403);
        $data = $this->validate(['ruleName' => ['required', 'string', 'max:120'], 'ruleDescription' => ['nullable', 'string', 'max:2000'], 'rulePoints' => ['required', 'integer', 'min:1', 'max:500'], 'duplicateDays' => ['required', 'integer', 'min:0', 'max:365'], 'ruleTermLimit' => ['nullable', 'integer', 'min:1', 'max:999'], 'ruleRequiresApproval' => ['boolean']]);
        HousePointRule::create(['school_id' => auth()->user()->school_id, 'name' => trim($data['ruleName']), 'description' => trim($data['ruleDescription']), 'default_points' => $data['rulePoints'], 'duplicate_window_days' => $data['duplicateDays'], 'max_awards_per_student_per_term' => $data['ruleTermLimit'] ?: null, 'requires_approval' => $data['ruleRequiresApproval'], 'created_by' => auth()->id()]);
        $this->reset(['ruleName', 'ruleDescription', 'ruleTermLimit', 'ruleRequiresApproval']);
        session()->flash('success', 'Award rule created.');
    }

    public function requestPoints(HousePointService $service): void
    {
        abort_unless(auth()->user()->can('award house points'), 403);
        $data = $this->validate(['studentId' => ['required', 'integer'], 'ruleId' => ['required', 'integer'], 'points' => ['required', 'integer', 'min:1', 'max:500'], 'awardKind' => ['required', Rule::in(['award', 'deduction'])], 'awardReason' => ['required', 'string', 'min:3', 'max:500'], 'occurredOn' => ['required', 'date', 'before_or_equal:today']]);
        $student = $this->manageableStudents()->findOrFail($data['studentId']);
        $rule = HousePointRule::query()->where('school_id', auth()->user()->school_id)->findOrFail($data['ruleId']);
        $award = $service->request($student, auth()->user(), $rule, ['kind' => $data['awardKind'], 'points' => $data['points'], 'reason' => $data['awardReason'], 'occurred_on' => $data['occurredOn'], 'academic_year_id' => auth()->user()->school?->academic_year_id, 'semester_id' => auth()->user()->school?->semester_id]);
        $this->reset(['studentId', 'ruleId', 'awardReason']);
        $this->points = 5;
        session()->flash('success', $award->status === 'approved' ? 'Points recorded in the immutable ledger.' : 'Award submitted for independent approval.');
    }

    public function reviewAward(int $awardId, string $decision, HousePointService $service): void
    {
        $award = HousePointAward::query()->where('school_id', auth()->user()->school_id)->findOrFail($awardId);
        $service->review($award, auth()->user(), $decision, $this->reviewNote);
        $this->reviewNote = '';
        session()->flash('success', 'Award review saved.');
    }

    public function createChallenge(): void
    {
        abort_unless(auth()->user()->can('manage house challenges'), 403);
        $data = $this->validate(['challengeTitle' => ['required', 'string', 'max:180'], 'challengeDescription' => ['required', 'string', 'max:5000'], 'challengeStartsAt' => ['required', 'date'], 'challengeDeadlineAt' => ['required', 'date', 'after:challengeStartsAt'], 'challengePoints' => ['required', 'integer', 'min:0', 'max:500']]);
        HouseChallenge::create(['school_id' => auth()->user()->school_id, 'academic_year_id' => auth()->user()->school?->academic_year_id, 'semester_id' => auth()->user()->school?->semester_id, 'title' => trim($data['challengeTitle']), 'description' => trim($data['challengeDescription']), 'starts_at' => $data['challengeStartsAt'], 'deadline_at' => $data['challengeDeadlineAt'], 'completion_points' => $data['challengePoints'], 'status' => 'open', 'created_by' => auth()->id()]);
        $this->reset(['challengeTitle', 'challengeDescription']);
        session()->flash('success', 'Challenge opened for participation.');
    }

    public function joinChallenge(int $challengeId): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);
        $challenge = HouseChallenge::query()->where('school_id', auth()->user()->school_id)->where('status', 'open')->where('deadline_at', '>', now())->findOrFail($challengeId);
        $membership = SchoolHouseMembership::query()->where('student_id', auth()->id())->where('academic_year_id', $challenge->academic_year_id)->whereNull('left_on')
            ->whereHas('house', fn ($query) => $query->where('school_id', auth()->user()->school_id)->where('active', true))->firstOrFail();
        HouseChallengeParticipation::firstOrCreate(['house_challenge_id' => $challenge->id, 'student_id' => auth()->id()], ['school_house_id' => $membership->school_house_id, 'status' => 'joined']);
        session()->flash('success', 'Challenge joined. Your result must be verified by staff.');
    }

    public function verifyParticipation(int $participationId, string $status): void
    {
        abort_unless(auth()->user()->can('manage house challenges'), 403);
        abort_unless(in_array($status, ['completed', 'not_completed'], true), 422);
        $data = $this->validate(['resultScore' => ['nullable', 'numeric', 'min:0', 'max:999999'], 'resultPosition' => ['nullable', 'integer', 'min:1'], 'resultNote' => ['nullable', 'string', 'max:2000']]);
        $participation = HouseChallengeParticipation::query()->whereIn('student_id', $this->visibleStudentIds())->whereHas('challenge', fn ($q) => $q->where('school_id', auth()->user()->school_id))->findOrFail($participationId);
        $participation->update(['status' => $status, 'score' => $data['resultScore'] ?: null, 'position' => $data['resultPosition'] ?: null, 'result_note' => trim($data['resultNote']) ?: null, 'verified_by' => auth()->id(), 'verified_at' => now()]);
        $this->reset(['resultScore', 'resultPosition', 'resultNote']);
        session()->flash('success', 'Challenge result verified. Points remain subject to the award rules and approval workflow.');
    }

    public function createBadge(): void
    {
        abort_unless(auth()->user()->can('manage houses'), 403);
        $data = $this->validate(['badgeName' => ['required', 'string', 'max:120'], 'badgeDescription' => ['nullable', 'string', 'max:2000'], 'badgeMinimumPoints' => ['nullable', 'integer', 'min:1']]);
        HouseBadge::create(['school_id' => auth()->user()->school_id, 'name' => trim($data['badgeName']), 'description' => trim($data['badgeDescription']), 'minimum_points' => $data['badgeMinimumPoints'] ?: null]);
        $this->reset(['badgeName', 'badgeDescription', 'badgeMinimumPoints']);
        session()->flash('success', 'Badge definition created.');
    }

    public function awardBadge(): void
    {
        abort_unless(auth()->user()->can('manage houses'), 403);
        $data = $this->validate(['badgeId' => ['required', 'integer'], 'badgeStudentId' => ['required', 'integer'], 'badgeReason' => ['required', 'string', 'min:3', 'max:500']]);
        $badge = HouseBadge::query()->where('school_id', auth()->user()->school_id)->where('active', true)->findOrFail($data['badgeId']);
        $student = $this->manageableStudents()->findOrFail($data['badgeStudentId']);
        $yearId = auth()->user()->school?->academic_year_id;
        abort_unless($yearId, 422, 'Select an academic year first.');
        $points = HousePointTransaction::query()->where('student_id', $student->id)->where('academic_year_id', $yearId)->sum('points');
        abort_if($badge->minimum_points && $points < $badge->minimum_points, 422, "The student has {$points} points; this badge requires {$badge->minimum_points}.");
        StudentHouseBadge::firstOrCreate(['house_badge_id' => $badge->id, 'student_id' => $student->id, 'academic_year_id' => $yearId], ['awarded_by' => auth()->id(), 'reason' => trim($data['badgeReason']), 'awarded_at' => now()]);
        $this->reset(['badgeId', 'badgeStudentId', 'badgeReason']);
        session()->flash('success', 'Badge awarded with a traceable reason.');
    }

    private function visibleStudentIds()
    {
        $user = auth()->user();
        if ($user->hasRole('student')) {
            return collect([$user->id]);
        }
        if ($user->hasRole('parent')) {
            return ParentRecord::query()->active()->where('user_id', $user->id)->whereNotNull('verified_at')->where('can_view_growth_timeline', true)->pluck('student_id');
        }

        return app(StudentRecordAccessService::class)->permittedStudents($user, 'timeline')->pluck('id');
    }

    private function manageableStudents()
    {
        return User::query()->where('school_id', auth()->user()->school_id)->whereIn('id', $this->visibleStudentIds())->role('student');
    }

    public function render()
    {
        $user = auth()->user();
        $yearId = $user->school?->academic_year_id;
        $semesterId = $user->school?->semester_id;
        $houses = SchoolHouse::query()->where('school_id', $user->school_id)->where('active', true)->orderBy('name')->get();
        $students = $this->manageableStudents()->orderBy('name')->get(['id', 'name']);
        $rules = HousePointRule::query()->where('school_id', $user->school_id)->where('active', true)->orderBy('name')->get();
        $rankings = HousePointTransaction::query()->select('school_house_id', DB::raw('SUM(points) as total_points'))->where('academic_year_id', $yearId)
            ->when($this->rankingPeriod === 'term', fn ($q) => $q->where('semester_id', $semesterId))->groupBy('school_house_id')->with('house')->orderByDesc('total_points')->get();
        $studentLeaders = $user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin', 'teacher'])
            ? HousePointTransaction::query()->select('student_id', DB::raw('SUM(points) as total_points'))->where('academic_year_id', $yearId)->when($this->rankingPeriod === 'term', fn ($q) => $q->where('semester_id', $semesterId))->groupBy('student_id')->with('student:id,name')->orderByDesc('total_points')->limit(10)->get() : collect();
        $ownTransactions = HousePointTransaction::query()->whereIn('student_id', $this->visibleStudentIds())->with(['student:id,name', 'house:id,name,colour'])->latest('occurred_on')->limit(30)->get();
        $pendingAwards = $user->can('approve house point awards') ? HousePointAward::query()->where('school_id', $user->school_id)->where('status', 'pending')->where('requested_by', '!=', $user->id)->with(['student:id,name', 'house:id,name', 'rule:id,name'])->latest()->get() : collect();
        $visibleStudentIds = $this->visibleStudentIds();
        $challenges = HouseChallenge::query()->where('school_id', $user->school_id)->with(['participations' => fn ($q) => $q->whereIn('student_id', $visibleStudentIds), 'participations.student:id,name', 'participations.house:id,name'])->orderByDesc('deadline_at')->get();
        $badges = HouseBadge::query()->where('school_id', $user->school_id)->where('active', true)->get();
        $studentBadges = StudentHouseBadge::query()->where('academic_year_id', $yearId)->whereIn('student_id', $visibleStudentIds)->with(['badge:id,name,icon', 'student:id,name'])->latest('awarded_at')->get();
        $memberships = SchoolHouseMembership::query()->where('academic_year_id', $yearId)->whereIn('student_id', $this->visibleStudentIds())->with(['student:id,name', 'house:id,name,colour'])->get();

        return view('livewire.houses.house-points-hub', compact('houses', 'students', 'rules', 'rankings', 'studentLeaders', 'ownTransactions', 'pendingAwards', 'challenges', 'badges', 'studentBadges', 'memberships'))->layout('layouts.dashboard')->title('House Points & Challenges');
    }
}
