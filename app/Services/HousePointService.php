<?php

namespace App\Services;

use App\Models\HousePointAward;
use App\Models\HousePointRule;
use App\Models\HousePointTransaction;
use App\Models\SchoolHouseMembership;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HousePointService
{
    public function request(User $student, User $actor, HousePointRule $rule, array $data): HousePointAward
    {
        $rule->refresh();
        abort_unless($actor->can('award house points') && (int) $actor->school_id === (int) $student->school_id, 403);
        abort_unless($rule->active && (int) $rule->school_id === (int) $actor->school_id, 422);

        $yearId = (int) ($data['academic_year_id'] ?? $actor->school?->academic_year_id);
        $membership = SchoolHouseMembership::query()->where('student_id', $student->id)
            ->where('academic_year_id', $yearId)->whereNull('left_on')
            ->whereHas('house', fn ($query) => $query->where('school_id', $actor->school_id)->where('active', true))->first();
        if (! $membership) {
            throw ValidationException::withMessages(['studentId' => 'This student has no active house membership for the selected academic year.']);
        }

        $kind = $data['kind'] ?? 'award';
        if ($kind === 'deduction') {
            abort_unless($actor->can('deduct house points'), 403);
        }
        $points = (int) ($data['points'] ?? $rule->default_points);
        if ($points < 1) {
            throw ValidationException::withMessages(['points' => 'Points must be at least one.']);
        }

        $lockKey = "house-point-award:{$actor->school_id}:{$student->id}:{$rule->id}:{$kind}";

        return Cache::lock($lockKey, 10)->block(5, fn () => $this->requestLocked(
            $student, $actor, $rule, $data, $membership, $yearId, $kind, $points
        ));
    }

    private function requestLocked(User $student, User $actor, HousePointRule $rule, array $data, SchoolHouseMembership $membership, int $yearId, string $kind, int $points): HousePointAward
    {
        $windowStart = now()->parse($data['occurred_on'])->subDays(max(0, $rule->duplicate_window_days))->toDateString();
        $duplicate = HousePointAward::query()->where('student_id', $student->id)->where('house_point_rule_id', $rule->id)
            ->where('kind', $kind)->whereDate('occurred_on', '>=', $windowStart)->whereNotIn('status', ['rejected'])->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['ruleId' => 'This rule was already used for the student within its duplicate-prevention window.']);
        }
        if ($rule->max_awards_per_student_per_term) {
            $count = HousePointAward::query()->where('student_id', $student->id)->where('house_point_rule_id', $rule->id)
                ->where('academic_year_id', $yearId)->where('semester_id', $data['semester_id'] ?? null)
                ->whereNotIn('status', ['rejected'])->count();
            if ($count >= $rule->max_awards_per_student_per_term) {
                throw ValidationException::withMessages(['ruleId' => 'This rule has reached its term limit for the student.']);
            }
        }

        $highValue = $points >= 25;
        $needsApproval = $kind === 'deduction' || $rule->requires_approval || $highValue;
        $dedupe = hash('sha256', implode('|', [$student->id, $rule->id, $kind, $data['occurred_on'], $points, trim($data['reason'])]));

        return DB::transaction(function () use ($student, $actor, $rule, $data, $membership, $yearId, $kind, $points, $needsApproval, $dedupe): HousePointAward {
            $award = HousePointAward::create([
                'school_id' => $actor->school_id, 'student_id' => $student->id, 'school_house_id' => $membership->school_house_id,
                'house_point_rule_id' => $rule->id, 'student_activity_record_id' => $data['student_activity_record_id'] ?? null,
                'academic_year_id' => $yearId, 'semester_id' => $data['semester_id'] ?? null, 'kind' => $kind,
                'points' => $points, 'reason' => trim($data['reason']), 'occurred_on' => $data['occurred_on'],
                'status' => $needsApproval ? 'pending' : 'approved', 'deduplication_key' => $dedupe, 'requested_by' => $actor->id,
                'reviewed_by' => $needsApproval ? null : $actor->id, 'reviewed_at' => $needsApproval ? null : now(),
            ]);
            if (! $needsApproval) {
                $this->recordLedger($award, $actor);
            }

            return $award;
        });
    }

    public function review(HousePointAward $award, User $reviewer, string $decision, ?string $note = null): void
    {
        abort_unless($reviewer->can('approve house point awards') && (int) $award->school_id === (int) $reviewer->school_id, 403);
        abort_unless($award->status === 'pending' && in_array($decision, ['approved', 'rejected'], true), 422);
        abort_if((int) $award->requested_by === (int) $reviewer->id, 422, 'Sensitive and high-value awards require review by a different authorised staff member.');
        DB::transaction(function () use ($award, $reviewer, $decision, $note): void {
            $award = HousePointAward::query()->lockForUpdate()->findOrFail($award->id);
            abort_unless($award->status === 'pending', 422, 'This award has already been reviewed.');
            $award->update(['status' => $decision, 'reviewed_by' => $reviewer->id, 'review_note' => trim((string) $note) ?: null, 'reviewed_at' => now()]);
            if ($decision === 'approved') {
                $this->recordLedger($award, $reviewer);
            }
        });
    }

    private function recordLedger(HousePointAward $award, User $actor): HousePointTransaction
    {
        return HousePointTransaction::firstOrCreate(['house_point_award_id' => $award->id], [
            'school_id' => $award->school_id, 'student_id' => $award->student_id, 'school_house_id' => $award->school_house_id,
            'academic_year_id' => $award->academic_year_id, 'semester_id' => $award->semester_id,
            'points' => $award->kind === 'deduction' ? -$award->points : $award->points, 'reason' => $award->reason,
            'occurred_on' => $award->occurred_on, 'recorded_by' => $actor->id, 'recorded_at' => now(),
        ]);
    }
}
