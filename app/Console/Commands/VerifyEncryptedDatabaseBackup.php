<?php

namespace App\Console\Commands;

use App\Services\EncryptedDatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyEncryptedDatabaseBackup extends Command
{
    protected $signature = 'ops:backup-verify {file? : Backup filename inside the configured directory}';

    protected $description = 'Decrypt a backup and restore it into a disposable verification database';

    public function handle(EncryptedDatabaseBackupService $backups): int
    {
        try {
            $result = $backups->verifyRestore($this->argument('file'));
            $this->info("Restore test passed for {$result['file']} ({$result['tables_restored']} tables). ");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
