<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalHealthService
{
    public const SCHEDULER_HEARTBEAT_KEY = 'operations:scheduler:last_seen';

    public function check(): array
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'scheduler' => $this->schedulerCheck(),
            'queue' => $this->queueCheck(),
        ];

        return [
            'healthy' => collect($checks)->every(fn (array $check): bool => $check['healthy']),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function databaseCheck(): array
    {
        try {
            DB::select('SELECT 1');

            return ['healthy' => true, 'message' => 'Database connection is available.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['healthy' => false, 'message' => 'Database connection failed.'];
        }
    }

    private function schedulerCheck(): array
    {
        $lastSeen = (int) Cache::get(self::SCHEDULER_HEARTBEAT_KEY, 0);
        $age = $lastSeen > 0 ? now()->timestamp - $lastSeen : null;
        $healthy = $age !== null && $age <= config('operations.health.scheduler_grace_seconds');

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Scheduler heartbeat is current.' : 'Scheduler heartbeat is missing or stale.',
            'age_seconds' => $age,
        ];
    }

    private function queueCheck(): array
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            return ['healthy' => false, 'message' => 'Queue storage tables are missing.'];
        }

        $pending = (int) DB::table('jobs')->count();
        $failed = (int) DB::table('failed_jobs')->count();
        $oldestCreatedAt = DB::table('jobs')->min('created_at');
        $oldestAge = $oldestCreatedAt ? max(0, now()->timestamp - (int) $oldestCreatedAt) : 0;
        $healthy = $pending <= config('operations.health.queue_pending_warning')
            && $failed < config('operations.health.failed_jobs_warning')
            && $oldestAge <= config('operations.health.queue_oldest_warning_seconds');

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Queue is within configured thresholds.' : 'Queue requires attention.',
            'pending' => $pending,
            'failed' => $failed,
            'oldest_pending_age_seconds' => $oldestAge,
        ];
    }
}
