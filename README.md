# Brigada Guardian

Private Laravel monitoring package. Runs security audits, health checks, and performance monitoring. Reports to Discord.

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0
- Discord webhook URL

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

Authenticate with GitHub (one-time per machine):

```bash
composer config github-oauth.github.com ghp_YOUR_TOKEN
```

Install the package:

```bash
composer require brigada/guardian
php artisan vendor:publish --tag=guardian-config
php artisan migrate
```

Add to your `.env`:

```
GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/xxx/yyy
GUARDIAN_PROJECT_NAME="Your Project Name"
GUARDIAN_ENVIRONMENT=production
```

## What It Monitors

**Every 5 minutes:** Failed job spikes, stale queue jobs, scheduler heartbeat

**Hourly:** Disk space, memory, database/Redis connectivity, log error spikes, queue sizes, Horizon status, storage size

**Daily:** Composer audit, npm audit, SSL certificate expiry, .env safety, file permissions, pending migrations, PHP/OS version EOL, config cache staleness, insecure/abandoned packages, CSRF/CORS config

**Weekly:** Full trend report comparing this week vs last week

## Commands

```bash
php artisan guardian:run hourly                    # Run hourly checks
php artisan guardian:run daily                     # Run daily checks
php artisan guardian:run --check=DiskSpaceCheck     # Run single check
php artisan guardian:status                        # View latest results locally
```

## Configuration

Disable checks per project:

```php
// config/guardian.php
'disabled_checks' => [
    \Brigada\Guardian\Checks\HorizonStatusCheck::class,
    \Brigada\Guardian\Checks\NpmAuditCheck::class,
],
```

Adjust thresholds:

```php
'thresholds' => [
    'disk_percent' => ['warning' => 70, 'critical' => 85],
    'queue_size'   => ['warning' => 50, 'critical' => 200],
],
```

Environment gating (default: production only):

```php
'enabled_environments' => ['production', 'staging'],
```

## Scheduling

Guardian auto-registers its schedule via the service provider. Ensure the Laravel scheduler is running:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## CI Setup (GitHub Actions)

```yaml
- run: composer config github-oauth.github.com ${{ secrets.COMPOSER_GITHUB_TOKEN }}
- run: composer require brigada/guardian
```

## Notification Behavior

- Alerts are sent to Discord with color-coded embeds (red = critical, orange = warning, green = ok)
- Duplicate notifications are suppressed within a configurable window (default: 60 minutes)
- Daily and weekly summary reports aggregate all check results
