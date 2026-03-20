# Brigada Guardian

Private Laravel monitoring package. Runs security audits, health checks, and performance monitoring. Reports to Discord.

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

## CI / Server Setup

For CI pipelines and production servers, use a token stored as a secret — never hardcode it.

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

## Notification Behavior

- Alerts are sent to Discord with color-coded embeds (red = critical, orange = warning, green = ok)
- Duplicate notifications are suppressed within a configurable window (default: 60 minutes)
- Daily and weekly summary reports aggregate all check results
