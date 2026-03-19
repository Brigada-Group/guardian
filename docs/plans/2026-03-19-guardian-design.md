# Brigada Guardian — Design Document

**Package:** `brigada/guardian`
**Namespace:** `Brigada\Guardian`
**Type:** Private Composer package (GitHub repo)
**Purpose:** Self-monitoring package installed in each Laravel project, reports security, health, and performance issues to a shared Discord channel.

---

## Architecture: Modular Check Classes

Each check is its own class implementing a `HealthCheck` interface. A registry holds all checks grouped by schedule. The Laravel scheduler runs checks at their configured frequency.

### Package Structure

```
src/
  GuardianServiceProvider.php
  Contracts/
    HealthCheck.php
  Enums/
    Severity.php                      # critical, warning, info
    Schedule.php                      # every_5_min, hourly, daily, weekly
    Status.php                        # ok, warning, critical, error
  Checks/
    # Every 5 min
    FailedJobsSpikeCheck.php
    StaleJobsCheck.php
    SchedulerHeartbeatCheck.php
    # Hourly
    DiskSpaceCheck.php
    MemoryCheck.php
    DatabaseCheck.php
    RedisCheck.php
    LogErrorSpikeCheck.php
    QueueSizeCheck.php
    HorizonStatusCheck.php
    StorageSizeCheck.php
    # Daily
    ComposerAuditCheck.php
    NpmAuditCheck.php
    SslCertificateCheck.php
    EnvSafetyCheck.php
    FilePermissionsCheck.php
    PendingMigrationsCheck.php
    PhpVersionCheck.php
    OsVersionCheck.php
    ConfigCacheStalenessCheck.php
    InsecurePackagesCheck.php
    CsrfCorsCheck.php
    # Weekly
    FullReportCheck.php
  Results/
    CheckResult.php
  Notifications/
    DiscordNotifier.php
    DiscordMessageBuilder.php
  Commands/
    RunChecksCommand.php              # php artisan guardian:run {schedule}
    StatusCommand.php                 # php artisan guardian:status
  Support/
    CheckRegistry.php
    Deduplicator.php
config/
  guardian.php
database/
  migrations/
    create_guardian_results_table.php
```

---

## Core Contracts

### HealthCheck Interface

```php
interface HealthCheck
{
    public function name(): string;
    public function schedule(): Schedule;
    public function run(): CheckResult;
}
```

### CheckResult Value Object

```php
class CheckResult
{
    public function __construct(
        public Status $status,       // ok, warning, critical, error
        public string $message,
        public array $metadata = [],
    ) {}
}
```

---

## Configuration

Published to `config/guardian.php`:

```php
return [
    'project_name' => env('GUARDIAN_PROJECT_NAME', config('app.name')),
    'environment' => env('GUARDIAN_ENVIRONMENT', config('app.env')),
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
```

Per-project `.env`:

```
GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/xxx/yyy
GUARDIAN_PROJECT_NAME="Client Portal"
```

---

## Schedule Tiers

| Frequency | Checks |
|-----------|--------|
| Every 5 min | Failed jobs spike, stale jobs, scheduler heartbeat |
| Hourly | Disk space, memory, DB/Redis connectivity, log error spike, queue sizes, Horizon status, storage size |
| Daily (6 AM) | Composer audit, npm audit, SSL expiry, .env safety, file permissions, pending migrations, PHP/OS EOL, config cache staleness, insecure packages, CSRF/CORS, daily summary |
| Weekly (Monday) | Full comprehensive report with trend data |

---

## Discord Notification Format

### Critical/Warning Alert (immediate)

```
[Client Portal] CRITICAL — Disk Space
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Disk usage at 94.2% (2.1GB free of 40GB)
Environment: production
Server: forge@client-portal
Timestamp: 2026-03-19 14:30:00 UTC
```

Embed colors: red (#FF0000) for critical, orange (#FFA500) for warning.

### Daily Summary (6 AM)

```
[Client Portal] Daily Health Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Disk Space         — 62.3% used (15.1GB free)
Memory             — 71.2% used (1.8GB free)
Database           — Connected (12ms)
Redis              — Connected (3ms, 48MB)
Queue Sizes        — default: 0 | high: 2
Failed Jobs (24h)  — 0
Horizon            — Running
Scheduler          — Last heartbeat 4m ago
Storage            — 1.2GB
Composer Audit     — 2 vulnerabilities found
  - laravel/framework (CVE-2026-1234) — Update to ^11.5
  - guzzlehttp/guzzle (CVE-2026-5678) — Update to ^7.9
NPM Audit          — No vulnerabilities
SSL Certificate    — Expires in 84 days
.env Safety        — APP_DEBUG=false, APP_ENV=production
File Permissions   — OK
Pending Migrations — None
PHP Version        — 8.3.4 (supported until 2028-11)
OS Version         — Ubuntu 22.04 (supported until 2027-04)
Config Cache       — Fresh

Overall: 1 issue needs attention
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Environment: production | Server: forge@client-portal
```

### Weekly Full Report (Monday)

Same as daily with trend data: "Disk usage up 3% from last week", "12 failed jobs this week vs 3 last week".

### Deduplication

Same alert not repeated within `dedup_minutes` window (default 60 min).

---

## Database Table: guardian_results

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint | Primary key |
| check_class | string | Which check ran |
| status | string | ok / warning / critical / error |
| message | text | Human-readable result |
| metadata | json | Raw values, thresholds, etc. |
| notified_at | timestamp | Last Discord notification (for dedup) |
| created_at | timestamp | When the check ran |

Auto-prune: keep 30 days of results.

---

## Scheduling (Auto-registered)

```php
// In GuardianServiceProvider::boot()
$this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
    $schedule->command('guardian:run every_5_min')->everyFiveMinutes();
    $schedule->command('guardian:run hourly')->hourly();
    $schedule->command('guardian:run daily')->dailyAt(config('guardian.notifications.daily_summary_time'));
    $schedule->command('guardian:run weekly')->weeklyOn(1, '07:00');
});
```

No extra cron entries needed — hooks into Laravel's existing `schedule:run`.

---

## Installation

```bash
composer require brigada/guardian
php artisan vendor:publish --tag=guardian-config
php artisan migrate
# Add to .env:
# GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/xxx/yyy
# GUARDIAN_PROJECT_NAME="Client Portal"
```

---

## Commands

```bash
php artisan guardian:run every_5_min    # Run 5-min checks
php artisan guardian:run hourly         # Run hourly checks
php artisan guardian:run daily          # Run daily checks
php artisan guardian:run weekly         # Run weekly checks
php artisan guardian:run --check=DiskSpaceCheck  # Run single check
php artisan guardian:status             # Local status overview (no Discord)
```

---

## What's NOT in Scope

- Uptime monitoring (handled by Forge heartbeat)
- CPU load monitoring (handled by Forge)
- APM/tracing (use Telescope or Sentry)
- Web dashboard (Discord is the interface)
