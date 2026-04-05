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
    'exceptions' => [
        'enabled' => true,
        'dedup_minutes' => 5,
        'ignored_exceptions' => [
            // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            // \Illuminate\Auth\AuthenticationException::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time Monitoring (Event-based)
    |--------------------------------------------------------------------------
    |
    | These settings control the event listeners and middleware that capture
    | requests, queries, mail, notifications, cache, commands, and scheduled
    | tasks in real-time. Each category can be individually enabled/disabled.
    |
    */

    'monitoring' => [
        'requests' => [
            'enabled' => true,
            'slow_threshold_ms' => 5000,
            'error_rate_threshold' => 50,      // alert when this many 5xx errors
            'error_rate_window_minutes' => 5,   // ...in this time window
            'dedup_minutes' => 5,
            'ignored_paths' => [
                '_debugbar*',
                'telescope*',
                'horizon*',
            ],
        ],

        'outgoing_http' => [
            'enabled' => true,
            'slow_threshold_ms' => 10000,
            'dedup_minutes' => 5,
            'ignored_hosts' => [],
        ],

        'queries' => [
            'enabled' => true,
            'slow_threshold_ms' => 500,
            'n_plus_one_threshold' => 10,       // flag after N identical queries per request
            'dedup_minutes' => 5,
        ],

        'mail' => [
            'enabled' => true,
            'dedup_minutes' => 5,
        ],

        'notifications' => [
            'enabled' => true,
            'dedup_minutes' => 5,
        ],

        'cache' => [
            'enabled' => true,
            'low_hit_rate_threshold' => 50,     // alert when hit rate drops below this %
            'dedup_minutes' => 30,
        ],

        'commands' => [
            'enabled' => true,
            'slow_threshold_ms' => 60000,       // 1 minute
            'dedup_minutes' => 5,
            'ignored' => [],
        ],

        'scheduled_tasks' => [
            'enabled' => true,
            'slow_threshold_ms' => 300000,      // 5 minutes
            'dedup_minutes' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'results_days' => 30,
        'request_logs_days' => 7,
        'outgoing_http_logs_days' => 7,
        'query_logs_days' => 7,
        'mail_logs_days' => 30,
        'notification_logs_days' => 30,
        'cache_logs_days' => 7,
        'command_logs_days' => 30,
        'scheduled_task_logs_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'sanitize_sql' => true,
        'anonymize_ip' => false,
        'hash_mail_recipients' => false,
        'safe_headers' => ['User-Agent', 'Referer', 'Accept', 'Content-Type'],
    ],
];
