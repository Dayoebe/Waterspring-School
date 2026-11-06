<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class EncryptedDatabaseBackupService
{
    private const MAGIC = "WSPBACKUP1\n";

    private const CHUNK_BYTES = 1024 * 1024;

    public function create(): array
    {
        $connection = $this->connection();
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory, 0700, true);

        $plainPath = $this->temporaryPath('sql');
        $encryptedPath = $directory.'/waterspring-db-'.now()->format('Ymd_His').'.sql.enc';

        try {
            $this->dumpMysql($connection, $plainPath);
            $this->encryptFile($plainPath, $encryptedPath);

            $manifest = [
                'format' => 'waterspring-encrypted-backup-v1',
                'file' => basename($encryptedPath),
                'created_at' => now()->toIso8601String(),
                'source_database' => (string) $connection['database'],
                'bytes' => filesize($encryptedPath),
                'sha256' => hash_file('sha256', $encryptedPath),
            ];
            File::put($encryptedPath.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
            @chmod($encryptedPath, 0600);
            @chmod($encryptedPath.'.json', 0600);

            return $manifest + ['path' => $encryptedPath];
        } catch (Throwable $exception) {
            File::delete([$encryptedPath, $encryptedPath.'.json']);
            throw $exception;
        } finally {
            File::delete($plainPath);
        }
    }

    public function verifyRestore(?string $file = null): array
    {
        $encryptedPath = $this->resolveBackupPath($file);
        $manifestPath = $encryptedPath.'.json';

        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('Backup manifest is missing.');
        }

        $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        if (! hash_equals((string) ($manifest['sha256'] ?? ''), hash_file('sha256', $encryptedPath))) {
            throw new RuntimeException('Encrypted backup checksum does not match its manifest.');
        }

        $connection = $this->connection();
        $plainPath = $this->temporaryPath('sql');
        $database = 'waterspring_restore_test_'.strtolower(Str::random(12));

        if (! preg_match('/^waterspring_restore_test_[a-z0-9]{12}$/', $database)) {
            throw new RuntimeException('Unsafe restore-test database name.');
        }

        try {
            $this->decryptFile($encryptedPath, $plainPath);
            DB::statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->importMysql($connection, $database, $plainPath);

            $tableCount = (int) DB::table('information_schema.tables')
                ->where('table_schema', $database)
                ->where('table_type', 'BASE TABLE')
                ->count();

            if ($tableCount < config('operations.backups.minimum_tables')) {
                throw new RuntimeException("Restore test produced only {$tableCount} tables.");
            }

            $hasMigrations = DB::table('information_schema.tables')
                ->where('table_schema', $database)
                ->where('table_name', 'migrations')
                ->exists();

            if (! $hasMigrations) {
                throw new RuntimeException('Restore test is missing the migrations table.');
            }

            return [
                'file' => basename($encryptedPath),
                'sha256' => $manifest['sha256'],
                'tables_restored' => $tableCount,
            ];
        } finally {
            File::delete($plainPath);
            DB::statement("DROP DATABASE IF EXISTS `{$database}`");
        }
    }

    public function prune(): int
    {
        $cutoff = now()->subDays(config('operations.backups.retention_days'))->timestamp;
        $removed = 0;

        foreach (File::glob($this->backupDirectory().'/*.sql.enc') ?: [] as $path) {
            if (filemtime($path) !== false && filemtime($path) < $cutoff) {
                File::delete([$path, $path.'.json']);
                $removed++;
            }
        }

        return $removed;
    }

    private function dumpMysql(array $connection, string $target): void
    {
        $command = $this->mysqlConnectionArguments($connection, $this->binary('mysqldump'));
        array_push(
            $command,
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            '--triggers',
            '--routines',
            '--events',
            '--result-file='.$target,
            (string) $connection['database']
        );

        $this->run($command, $connection);

        if (! File::isFile($target) || File::size($target) === 0) {
            throw new RuntimeException('Database dump is empty.');
        }
    }

    private function importMysql(array $connection, string $database, string $source): void
    {
        $command = $this->mysqlConnectionArguments($connection, $this->binary('mysql'));
        $command[] = $database;
        $input = fopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException('Unable to open decrypted backup for restore testing.');
        }

        try {
            $process = new Process($command, null, $this->mysqlEnvironment($connection), null, 900);
            $process->setInput($input);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database restore process failed.');
            }
        } finally {
            fclose($input);
        }
    }

    private function encryptFile(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($target, 'wb');
        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open backup encryption streams.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->encryptionKey());
            fwrite($output, self::MAGIC.$header);

            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read database dump during encryption.');
                }
                if ($chunk === '') {
                    break;
                }

                $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $chunk,
                    '',
                    SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
                );
                fwrite($output, pack('N', strlen($ciphertext)).$ciphertext);
            }

            $final = sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                '',
                '',
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
            );
            fwrite($output, pack('N', strlen($final)).$final);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function decryptFile(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($target, 'wb');
        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open backup decryption streams.');
        }

        try {
            $magic = fread($input, strlen(self::MAGIC));
            $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($magic !== self::MAGIC || strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('Encrypted backup format is invalid.');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->encryptionKey());
            $finalSeen = false;

            while (! feof($input)) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === '') {
                    break;
                }
                if ($lengthBytes === false || strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('Encrypted backup chunk header is truncated.');
                }

                $length = unpack('Nlength', $lengthBytes)['length'];
                $ciphertext = '';
                while (strlen($ciphertext) < $length) {
                    $part = fread($input, $length - strlen($ciphertext));
                    if ($part === false || $part === '') {
                        throw new RuntimeException('Encrypted backup chunk is truncated.');
                    }
                    $ciphertext .= $part;
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
                if ($result === false) {
                    throw new RuntimeException('Encrypted backup authentication failed.');
                }
                [$plain, $tag] = $result;
                fwrite($output, $plain);
                $finalSeen = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (! $finalSeen) {
                throw new RuntimeException('Encrypted backup is incomplete.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function connection(): array
    {
        $connection = (array) config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'mysql' || empty($connection['database'])) {
            throw new RuntimeException('Encrypted backups currently require a configured MySQL database.');
        }

        return $connection;
    }

    private function mysqlConnectionArguments(array $connection, string $binary): array
    {
        $arguments = [$binary, '--user='.(string) ($connection['username'] ?? '')];
        $socket = (string) ($connection['unix_socket'] ?? '');

        if ($socket !== '' && File::exists($socket)) {
            $arguments[] = '--socket='.$socket;
            $arguments[] = '--protocol=SOCKET';
        } else {
            $arguments[] = '--host='.(string) ($connection['host'] ?? '127.0.0.1');
            $arguments[] = '--port='.(string) ($connection['port'] ?? '3306');
        }

        return $arguments;
    }

    private function mysqlEnvironment(array $connection): array
    {
        $password = (string) ($connection['password'] ?? '');

        return $password === '' ? [] : ['MYSQL_PWD' => $password];
    }

    private function run(array $command, array $connection): void
    {
        $process = new Process($command, null, $this->mysqlEnvironment($connection), null, 900);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database backup process failed.');
        }
    }

    private function encryptionKey(): string
    {
        $configured = (string) config('operations.backups.encryption_key');
        $encoded = str_starts_with($configured, 'base64:') ? substr($configured, 7) : $configured;
        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }

    private function resolveBackupPath(?string $file): string
    {
        $directory = realpath($this->backupDirectory());
        if ($directory === false) {
            throw new RuntimeException('Backup directory does not exist.');
        }

        if ($file === null) {
            $files = File::glob($directory.'/*.sql.enc') ?: [];
            usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
            $path = $files[0] ?? null;
        } else {
            $path = realpath($file) ?: realpath($directory.'/'.basename($file));
        }

        if (! is_string($path) || ! File::isFile($path) || dirname($path) !== $directory || ! str_ends_with($path, '.sql.enc')) {
            throw new RuntimeException('Encrypted backup file was not found in the configured backup directory.');
        }

        return $path;
    }

    private function backupDirectory(): string
    {
        return rtrim((string) config('operations.backups.directory'), DIRECTORY_SEPARATOR);
    }

    private function temporaryPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'waterspring-backup-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary backup file.');
        }
        $target = $path.'.'.$extension;
        rename($path, $target);

        return $target;
    }

    private function binary(string $name): string
    {
        foreach (["/opt/lampp/bin/{$name}", "/usr/bin/{$name}", "/usr/local/bin/{$name}"] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Required database command is unavailable: {$name}");
    }
}
