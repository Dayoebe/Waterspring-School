<?php

namespace App\Console\Commands;

use App\Models\ParentDigestSetting;
use App\Services\ParentDigestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendParentWeeklyDigests extends Command
{
    protected $signature = 'digests:send-weekly {--school= : Limit delivery to one school ID} {--force-time : Ignore configured delivery day and hour}';

    protected $description = 'Queue privacy-scoped weekly parent and guardian digests';

    public function handle(ParentDigestService $digests): int
    {
        $queued = 0;
        $skipped = 0;
        ParentDigestSetting::query()->where('enabled', true)
            ->when($this->option('school'), fn ($query) => $query->where('school_id', (int) $this->option('school')))
            ->each(function (ParentDigestSetting $settings) use ($digests, &$queued, &$skipped): void {
                $now = CarbonImmutable::now($settings->timezone);
                $hour = substr((string) $settings->delivery_time, 0, 5);
                if (! $this->option('force-time') && ($now->isoWeekday() !== $settings->delivery_day || $now->format('H:i') !== $hour)) {
                    return;
                }
                $result = $digests->queueForSchool((int) $settings->school_id);
                $queued += $result['queued'];
                $skipped += $result['skipped'];
            });

        $this->info("Weekly digests queued: {$queued}; duplicates skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
