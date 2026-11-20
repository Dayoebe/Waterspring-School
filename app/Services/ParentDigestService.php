<?php

namespace App\Services;

use App\Jobs\SendParentWeeklyDigest;
use App\Models\ParentDigestDelivery;
use App\Models\ParentDigestSetting;
use App\Models\ParentRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ParentDigestService
{
    public function queueForSchool(int $schoolId, ?CarbonImmutable $weekStart = null): array
    {
        $settings = ParentDigestSetting::forSchool($schoolId);
        if (! $settings->exists || ! $settings->enabled) {
            return ['queued' => 0, 'skipped' => 0];
        }

        $start = ($weekStart ?: CarbonImmutable::now($settings->timezone)->subWeek()->startOfWeek())->startOfDay();
        $end = $start->endOfWeek();
        $guardianIds = ParentRecord::query()->active()->where('receives_digest', true)->whereNotNull('verified_at')
            ->whereHas('guardian', fn ($query) => $query->where('school_id', $schoolId)->where('locked', false)->whereNotNull('email'))
            ->pluck('user_id')->unique();
        $queued = 0;
        $skipped = 0;

        foreach ($guardianIds as $guardianId) {
            $delivery = DB::transaction(function () use ($schoolId, $guardianId, $start, $end): ParentDigestDelivery {
                return ParentDigestDelivery::query()->withoutGlobalScope('school')->firstOrCreate(
                    ['guardian_id' => $guardianId, 'week_start_date' => $start->toDateString()],
                    ['school_id' => $schoolId, 'week_end_date' => $end->toDateString(), 'status' => 'queued', 'queued_at' => now()],
                );
            });

            if (! $delivery->wasRecentlyCreated && $delivery->status !== 'failed') {
                $skipped++;

                continue;
            }
            if ($delivery->status === 'failed') {
                $delivery->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'failure_code' => null]);
            }
            SendParentWeeklyDigest::dispatch($delivery->id);
            $queued++;
        }

        return compact('queued', 'skipped');
    }

    public function preview(User $guardian, ?CarbonImmutable $weekStart = null): array
    {
        $settings = ParentDigestSetting::forSchool((int) $guardian->school_id);
        $start = ($weekStart ?: CarbonImmutable::now($settings->timezone)->subWeek()->startOfWeek())->startOfDay();

        return app(ParentDigestBuilder::class)->build($guardian->loadMissing('school'), $start, $start->endOfWeek(), (int) $settings->upcoming_days);
    }
}
