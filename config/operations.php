<?php

return [
    'force_https' => env('FORCE_HTTPS', env('APP_ENV') === 'production'),
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'health' => [
        'scheduler_grace_seconds' => (int) env('HEALTH_SCHEDULER_GRACE_SECONDS', 180),
        'queue_pending_warning' => (int) env('HEALTH_QUEUE_PENDING_WARNING', 100),
        'queue_oldest_warning_seconds' => (int) env('HEALTH_QUEUE_OLDEST_WARNING_SECONDS', 900),
        'failed_jobs_warning' => (int) env('HEALTH_FAILED_JOBS_WARNING', 1),
    ],

    'backups' => [
        'enabled' => env('BACKUPS_ENABLED', false),
        'directory' => env('BACKUPS_DIRECTORY', storage_path('app/private/backups')),
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'minimum_tables' => (int) env('BACKUP_RESTORE_MINIMUM_TABLES', 10),
    ],
];
