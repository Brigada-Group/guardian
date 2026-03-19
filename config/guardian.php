<?php

return [
    'project_name' => env('GUARDIAN_PROJECT_NAME', env('APP_NAME', 'Laravel')),
    'environment' => env('GUARDIAN_ENVIRONMENT', env('APP_ENV', 'production')),
    'discord_webhook_url' => env('GUARDIAN_DISCORD_WEBHOOK'),
    'enabled_environments' => ['production'],
    'disabled_checks' => [],
    'thresholds' => [
        'disk_percent' => ['warning' => 80, 'critical' => 90],
        'memory_percent' => ['warning' => 80, 'critical' => 90],
        'failed_jobs_spike' => ['warning' => 5, 'critical' => 20],
        'stale_job_minutes' => 30,
        'queue_size' => ['warning' => 100, 'critical' => 500],
        'log_errors_per_hour' => ['warning' => 10, 'critical' => 50],
        'ssl_days_before_expiry' => ['warning' => 30, 'critical' => 7],
        'storage_size_gb' => ['warning' => 5, 'critical' => 10],
        'db_response_ms' => ['warning' => 100, 'critical' => 500],
        'redis_response_ms' => ['warning' => 50, 'critical' => 200],
    ],
    'queues' => ['default'],
    'notifications' => [
        'dedup_minutes' => 60,
        'daily_summary_time' => '06:00',
        'weekly_summary_day' => 'monday',
    ],
];
