<?php

namespace App\Console;

use App\Console\Commands\AssignSubjectsCommand;
use App\Console\Commands\BaselineMigrations;
use App\Console\Commands\CleanupDeletedStudents;
use App\Console\Commands\CleanupInvalidResults;
use App\Console\Commands\SafeMigrateCommand;
use App\Console\Commands\SetDefaultPassword;
use App\Services\OperationalHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            Cache::put(OperationalHealthService::SCHEDULER_HEARTBEAT_KEY, now()->timestamp, 300);
        })->name('operations:scheduler-heartbeat')->everyMinute()->onOneServer();

        $schedule->command('assignments:send-reminders --hours=24')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('results:cleanup')
            ->weeklyOn(0, '3:00')
            ->onOneServer(); // If using multiple servers

        $schedule->command('support:send-review-reminders')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('appointments:send-reminders --hours=24')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('queue:prune-failed --hours=168')
            ->dailyAt('01:30')
            ->onOneServer();

        $schedule->command('ops:backup --prune')
            ->dailyAt('02:00')
            ->when(fn (): bool => (bool) config('operations.backups.enabled'))
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('ops:backup-verify')
            ->weeklyOn(0, '04:00')
            ->when(fn (): bool => (bool) config('operations.backups.enabled'))
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        BaselineMigrations::class,
        SafeMigrateCommand::class,
        AssignSubjectsCommand::class,
        SetDefaultPassword::class,
        CleanupDeletedStudents::class,
        CleanupInvalidResults::class,
    ];
}
