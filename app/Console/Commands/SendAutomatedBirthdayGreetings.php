<?php

namespace App\Console\Commands;

use App\Models\BirthdaySetting;
use App\Models\User;
use App\Services\BirthdayService;
use App\Services\BirthdayWishService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendAutomatedBirthdayGreetings extends Command
{
    protected $signature = 'birthdays:send-automated {--school= : Limit delivery to one school ID} {--force-time : Ignore the configured delivery hour}';

    protected $description = 'Queue idempotent automated school birthday greetings';

    public function handle(BirthdayService $birthdays, BirthdayWishService $wishes): int
    {
        $queued = 0;
        $skipped = 0;

        BirthdaySetting::query()
            ->where('feature_enabled', true)
            ->where('automated_emails_enabled', true)
            ->when($this->option('school'), fn ($query, $schoolId) => $query->where('school_id', (int) $schoolId))
            ->with('school')
            ->each(function (BirthdaySetting $settings) use ($birthdays, $wishes, &$queued, &$skipped): void {
                $now = CarbonImmutable::now($settings->timezone ?: config('birthdays.timezone'));
                $deliveryHour = (int) substr((string) $settings->delivery_time, 0, 2);
                if (! $this->option('force-time') && $now->hour !== $deliveryHour) {
                    $skipped++;

                    return;
                }

                $ids = $birthdays->today((int) $settings->school_id, $now)->pluck('id');
                User::query()->whereIn('id', $ids)->with(['roles', 'parents'])->each(function (User $celebrant) use ($wishes, $settings, $now, &$queued): void {
                    if ($wishes->queueAutomated($celebrant, $settings, $now->year)) {
                        $queued++;
                    }
                });
            });

        $this->info("Automated birthday greetings complete: {$queued} queued, {$skipped} school schedules not due.");

        return self::SUCCESS;
    }
}
