# Brigada Guardian

Private Laravel monitoring package. Runs security audits, health checks, and real-time event monitoring. Reports everything to Discord.

**Think of it as Nightwatch for Laravel** — but self-hosted and Discord-native.

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0
- Discord webhook URL
- Membership in the [Brigada-Group](https://github.com/Brigada-Group) GitHub organization

## Team Access Setup

Before installing, each developer needs access to the private repository:

### For org admins

1. Go to [Brigada-Group members](https://github.com/orgs/Brigada-Group/people)
2. Click **Invite member** and enter the developer's GitHub username or email
3. Grant access to the `guardian` repository (at minimum **Read** role)

### For developers

Once you've accepted the org invite, create a personal access token:

1. Go to [GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)](https://github.com/settings/tokens)
2. Click **Generate new token (classic)**
3. Name it something like `composer-brigada`
4. Select the **`repo`** scope (required for private repos)
5. Click **Generate token** and copy the `ghp_...` value immediately

Then configure Composer (one-time per machine):

```bash
composer config --global github-oauth.github.com ghp_YOUR_TOKEN
```

> **Note:** Use `--global` so the token works across all projects on your machine. Never commit tokens to version control.

## Installation

Add the private repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Brigada-Group/guardian.git"
        }
    ]
}
```

Install the package:

```bash
composer require brigada/guardian
php artisan guardian:install
```

Add to your `.env`:

```
GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/xxx/yyy
GUARDIAN_PROJECT_NAME="Your Project Name"
GUARDIAN_ENVIRONMENT=production
```

Verify it works:

```bash
php artisan guardian:test
```

## What It Monitors

### Cron-Based Health Checks (23 checks)

| Schedule | Checks |
|----------|--------|
| **Every 5 min** | Failed job spikes, stale queue jobs, scheduler heartbeat |
| **Hourly** | Disk space, memory, database/Redis connectivity, log error spikes, queue sizes, Horizon status, storage size |
| **Daily** | Composer audit, npm audit, SSL certificate expiry, .env safety, file permissions, pending migrations, PHP/OS version EOL, config cache staleness, insecure packages, CSRF/CORS config |
| **Weekly** | Full trend report comparing this week vs last week |

### Real-Time Event Monitoring (8 categories)

| Category | What It Captures | Alerts On |
|----------|-----------------|-----------|
| **Requests** | Every HTTP request (method, URI, duration, status) | Slow requests (>5s), high error rates |
| **Outgoing HTTP** | External API calls via Laravel HTTP client | Slow responses, 5xx errors, connection failures |
| **Database Queries** | Slow queries and N+1 patterns | Queries exceeding threshold, N+1 detection |
| **Mail** | Email send/fail events | Delivery failures |
| **Notifications** | All notification channel results | Failed notifications on any channel |
| **Cache** | Hit/miss ratios (aggregated per minute) | Low hit rate (<50%) |
| **Commands** | Artisan command execution and exit codes | Failed commands (non-zero exit), slow commands |
| **Scheduled Tasks** | Individual task completion, duration, failures | Failed tasks, slow tasks |

### Exception Tracking

Every uncaught exception is sent to Discord in real-time with:
- Exception class, message, and stack trace
- URL, status code, user info, IP address
- Deduplication (same exception only alerts once per 5 minutes)

## Commands

```bash
php artisan guardian:install                     # One-liner setup (config + migrations)
php artisan guardian:test                        # Send test notification to Discord
php artisan guardian:run hourly                   # Run hourly checks
php artisan guardian:run daily                    # Run daily checks
php artisan guardian:run --check=DiskSpaceCheck   # Run a single check
php artisan guardian:status                       # View latest results locally
php artisan guardian:prune                        # Delete old monitoring data
php artisan guardian:prune --days=7               # Override retention period
php artisan guardian:prune --dry-run              # Preview what would be deleted
```

## Configuration

Publish and edit `config/guardian.php`:

```bash
php artisan vendor:publish --tag=guardian-config
```

### Disable checks

```php
'disabled_checks' => [
    \Brigada\Guardian\Checks\HorizonStatusCheck::class,
    \Brigada\Guardian\Checks\NpmAuditCheck::class,
],
```

### Adjust health check thresholds

```php
'thresholds' => [
    'disk_percent' => ['warning' => 70, 'critical' => 85],
    'queue_size'   => ['warning' => 50, 'critical' => 200],
    'db_response_ms' => ['warning' => 50, 'critical' => 200],
],
```

### Configure real-time monitoring

Each monitoring category can be individually enabled/disabled with its own thresholds:

```php
'monitoring' => [
    'requests' => [
        'enabled' => true,
        'slow_threshold_ms' => 5000,
        'error_rate_threshold' => 50,
        'error_rate_window_minutes' => 5,
    ],
    'queries' => [
        'enabled' => true,
        'slow_threshold_ms' => 500,
        'n_plus_one_threshold' => 10,
    ],
    'outgoing_http' => [
        'enabled' => true,
        'slow_threshold_ms' => 10000,
    ],
    'cache' => [
        'enabled' => true,
        'low_hit_rate_threshold' => 50,
    ],
    'commands' => [
        'enabled' => true,
        'slow_threshold_ms' => 60000,
        'ignored' => ['some:noisy-command'],
    ],
    'scheduled_tasks' => [
        'enabled' => true,
        'slow_threshold_ms' => 300000,
    ],
],
```

### Data retention

Control how long monitoring data is kept before `guardian:prune` cleans it up:

```php
'retention' => [
    'results_days' => 30,
    'request_logs_days' => 7,
    'query_logs_days' => 7,
    'cache_logs_days' => 7,
    'mail_logs_days' => 30,
    'command_logs_days' => 30,
    'scheduled_task_logs_days' => 30,
],
```

### Environment gating

```php
'enabled_environments' => ['production', 'staging'],
```

### Ignored exceptions

```php
'exceptions' => [
    'enabled' => true,
    'dedup_minutes' => 5,
    'ignored_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Auth\AuthenticationException::class,
    ],
],
```

## Request Monitoring Middleware

The request monitoring middleware is available as `Brigada\Guardian\Http\Middleware\RequestMonitor`. Register it in your application's middleware stack to capture request metrics.

For **Laravel 11+** (bootstrap/app.php):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Brigada\Guardian\Http\Middleware\RequestMonitor::class);
})
```

For **Laravel 10** (app/Http/Kernel.php):

```php
protected $middleware = [
    // ...
    \Brigada\Guardian\Http\Middleware\RequestMonitor::class,
];
```

## Scheduling

Guardian auto-registers its cron checks via the service provider. Ensure the Laravel scheduler is running:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Schedule times are configurable:

```php
'notifications' => [
    'daily_summary_time' => '06:00',
    'weekly_summary_day' => 'monday',
],
```

## Discord Notifications

- Color-coded embeds: red = critical, orange = warning, green = ok, blue = test
- Duplicate alerts suppressed within configurable windows (default: 60 min for checks, 5 min for events)
- Daily and weekly summary reports aggregate all check results
- Rate limit handling with automatic retry on 429 responses

## CI / Server Setup

For CI pipelines and production servers, use a token stored as a secret.

### GitHub Actions

Add `COMPOSER_GITHUB_TOKEN` as a repository secret, then:

```yaml
- run: composer config github-oauth.github.com ${{ secrets.COMPOSER_GITHUB_TOKEN }}
- run: composer install
```

### Production Servers

```bash
composer config --global github-oauth.github.com ghp_YOUR_DEPLOY_TOKEN
```

Use a dedicated machine user or fine-grained token with read-only access to the `guardian` repo.

## Database Tables

Guardian creates the following tables:

| Table | Purpose |
|-------|---------|
| `guardian_results` | Health check results + deduplication |
| `guardian_request_logs` | HTTP request metrics |
| `guardian_outgoing_http_logs` | External API call tracking |
| `guardian_query_logs` | Slow queries and N+1 patterns |
| `guardian_mail_logs` | Email delivery tracking |
| `guardian_notification_logs` | Notification channel results |
| `guardian_cache_logs` | Cache hit/miss aggregations |
| `guardian_command_logs` | Artisan command execution |
| `guardian_scheduled_task_logs` | Scheduled task tracking |
