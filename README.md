# Brigada Guardian

Private Laravel monitoring package. Runs security audits, health checks, and performance monitoring. Reports to Discord.

## Installation

```bash
composer require brigada/guardian
php artisan vendor:publish --tag=guardian-config
php artisan migrate
```

Add to `.env`:

```
GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/xxx/yyy
GUARDIAN_PROJECT_NAME="Your Project Name"
```

## What It Monitors

**Every 5 minutes:** Failed job spikes, stale queue jobs, scheduler heartbeat

**Hourly:** Disk space, memory, database/Redis connectivity, log error spikes, queue sizes, Horizon status, storage size

**Daily:** Composer audit, npm audit, SSL certificate expiry, .env safety, file permissions, pending migrations, PHP/OS version EOL, config cache staleness, insecure/abandoned packages, CSRF/CORS config

**Weekly:** Full trend report comparing this week vs last week

## Commands

```bash
php artisan guardian:run hourly          # Run hourly checks
php artisan guardian:run daily           # Run daily checks
php artisan guardian:run --check=DiskSpaceCheck  # Run single check
php artisan guardian:status              # View latest results locally
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
    // ...
],
```
