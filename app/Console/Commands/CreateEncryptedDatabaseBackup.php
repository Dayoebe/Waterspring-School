<?php

namespace App\Console\Commands;

use App\Services\EncryptedDatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateEncryptedDatabaseBackup extends Command
{
    protected $signature = 'ops:backup {--prune : Remove encrypted backups older than the retention period}';

    protected $description = 'Create an authenticated, encrypted database backup';

    public function handle(EncryptedDatabaseBackupService $backups): int
    {
        if (! config('operations.backups.enabled')) {
            $this->warn('Scheduled backups are disabled. Set BACKUPS_ENABLED=true after configuring the encryption key.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->create();
            $this->info("Encrypted backup created: {$result['file']}");
            $this->line("SHA-256: {$result['sha256']}");

            if ($this->option('prune')) {
                $this->info('Expired backups removed: '.$backups->prune());
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
