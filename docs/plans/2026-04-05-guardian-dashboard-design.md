# Guardian Dashboard & Security Hardening — Design

**Date:** 2026-04-05
**Status:** Approved

---

## Overview

Add a self-contained monitoring dashboard to the Guardian package and harden security across the existing codebase. The dashboard provides full visibility into all 9 monitoring tables via an embedded Blade UI with Alpine.js interactivity and Chart.js visualizations.

## Architecture

### Stack
- **Blade + Alpine.js + Chart.js via CDN** — zero build step, fully self-contained in the package
- **Polling** (30s interval) for data refresh — no WebSocket dependency
- **Embedded package routes** — own middleware group, own route prefix, own views (Horizon/Pulse pattern)

### Route Prefix
`/guardian` (configurable via `config('guardian.dashboard.path')`)

### Middleware Stack
```
web → guardian.gate → guardian.ip-filter
```

### Routes

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/guardian` | Overview dashboard |
| GET | `/guardian/requests` | Request logs page |
| GET | `/guardian/queries` | Query logs page |
| GET | `/guardian/outgoing-http` | Outgoing HTTP page |
| GET | `/guardian/jobs` | Commands & scheduled tasks |
| GET | `/guardian/mail` | Mail logs page |
| GET | `/guardian/notifications` | Notification logs page |
| GET | `/guardian/cache` | Cache performance page |
| GET | `/guardian/exceptions` | Exception log page |
| GET | `/guardian/health` | Health check status page |
| GET | `/guardian/api/{section}` | JSON API for polling |

### Isolation Guarantees
- Own middleware group — doesn't touch host's `web` middleware modifications
- Own exception handling in controllers — errors never bubble to host
- All DB queries scoped to `guardian_*` tables only
- Polling endpoints return JSON with `Cache-Control: no-store`
- Dedicated `guardian` rate limiter — doesn't consume host app's rate limit quota
- No session writes or cookie mutations on API endpoints

---

## Dashboard Pages

### Overview (`/guardian`)
- Status banner: green/yellow/red based on worst health check
- 6 metric cards: Total requests (24h), Error rate %, Avg response time, Cache hit rate, Failed jobs, Open exceptions
- Response time chart (line, last 24h, 1h intervals)
- Error rate chart (bar, last 24h)
- Recent alerts timeline (last 10 Discord notifications sent)

### Requests (`/guardian/requests`)
- Filters: method, status code range, date range, slow only
- Response time distribution chart (histogram)
- Slowest endpoints table (top 20, sortable)
- Full request log table with pagination (50/page)

### Queries (`/guardian/queries`)
- Tabs: Slow Queries | N+1 Detections | All
- Slow query chart (count over time)
- Query table: SQL (truncated), duration, file:line, connection
- N+1 grouped by normalized SQL pattern with occurrence count

### Outgoing HTTP (`/guardian/outgoing-http`)
- Filters: host, status code, failed only
- Performance by host chart (avg duration)
- Failure rate by host
- Full log table with pagination

### Jobs & Scheduler (`/guardian/jobs`)
- Two tabs: Commands | Scheduled Tasks
- Commands: exit code breakdown chart, slow commands, failure list
- Scheduled tasks: timeline view, status per task, missed/skipped/failed highlights

### Mail (`/guardian/mail`)
- Sent vs failed chart (daily)
- Recent failures with error messages
- Full log table: mailable, subject, recipients, status

### Notifications (`/guardian/notifications`)
- By channel breakdown (pie chart)
- Failure rate by channel
- Full log table: class, channel, notifiable, status

### Cache (`/guardian/cache`)
- Hit rate chart over time (line)
- Per-store breakdown (if multiple stores)
- Reads vs writes chart
- Current hit rate gauge

### Exceptions (`/guardian/exceptions`)
- Grouped by exception class + file:line (deduped)
- Occurrence count + last seen
- Expandable: full message, sanitized stack trace, request context
- Trend chart (exceptions per hour, last 48h)

### Health Checks (`/guardian/health`)
- Grid of all 22 checks with status icons
- Last run time, last result message
- History sparkline per check (last 7 days)
- Manual "Run Now" button per check

### Shared UI Components
- Layout: sidebar nav, dark/light mode toggle (Alpine.js)
- Date range picker (Alpine.js component)
- Sortable/filterable tables (Alpine.js)
- Auto-refresh indicator with pause button
- All charts via Chart.js

---

## Security Hardening

### Dashboard Access
- **GuardianGate middleware:** checks `viewGuardianDashboard` gate
- **GuardianIpFilter middleware:** checks request IP against `config('guardian.dashboard.allowed_ips')` — empty = disabled (gate only)
- Both must pass. Either fails → 403.
- Kill switch: `config('guardian.dashboard.enabled')` defaults to `true`

### SQL Sanitization
- `QuerySanitizer` class redacts sensitive values from SQL before storage
- Replaces string literals in WHERE/INSERT/UPDATE with `'[REDACTED]'`
- Keeps column names, table names, operators for debugging
- Config: `guardian.security.sanitize_sql` (default: `true`)

### Stack Trace Sanitization
- `StackTraceSanitizer` strips env vars and config values from exception messages
- Truncates file paths to relative (removes server base path)
- Redacts strings matching secret patterns (`password`, `token`, `secret`, `key`, `authorization`)

### Header Filtering
- Whitelist approach: only safe headers sent to Discord
- Default whitelist: `User-Agent`, `Referer`, `Accept`, `Content-Type`
- Strips `Authorization`, `Cookie`, `X-CSRF-Token`, `X-API-Key`
- Configurable via `guardian.security.safe_headers`

### IP Anonymization
- Config: `guardian.monitoring.requests.anonymize_ip` (default: `false`)
- Zeroes last octet of IPv4 (`1.2.3.0`), last 80 bits of IPv6
- GDPR-friendly option

### Mail Recipient Hashing
- Config: `guardian.security.hash_mail_recipients` (default: `false`)
- Stores SHA-256 of email addresses instead of plain text
- Allows counting unique recipients without storing PII

### Command Execution Hardening
- Replace `exec()` in `NpmAuditCheck` with Laravel `Process` facade
- Use `Process::path(base_path())->run(['npm', 'audit', '--json'])` — no shell injection vector
- Array syntax prevents argument injection

### Discord Webhook Validation
- Validate URL matches Discord pattern on boot
- Log warning if mismatch, still allow (could be proxy)

### Rate Limiting
- `throttle:60,1` on `/guardian/api/*` routes
- Dedicated rate limiter — doesn't consume host app quota

---

## Polling Implementation

- Alpine.js component per page calls `/guardian/api/{section}`
- 30s interval (configurable via `guardian.dashboard.poll_interval`)
- Pause button in UI
- Only polls when browser tab is visible (`document.visibilitychange`)
- Stops after 30 minutes of no user interaction, resumes on activity
- All API endpoints wrapped in try/catch — errors return `{"error": "..."}` with 500
- All queries use `->take()` limits — never unbounded
- Read-only selects on poll endpoints

### API Response Structure
```json
{
  "data": { ... },
  "meta": {
    "generated_at": "2026-04-05T12:00:00Z",
    "next_poll": 30
  }
}
```

---

## File Structure

```
src/
├── Http/
│   ├── Controllers/
│   │   └── DashboardController.php
│   ├── Middleware/
│   │   ├── GuardianGate.php
│   │   └── GuardianIpFilter.php
│   └── RequestMonitor.php              (existing)
├── Security/
│   ├── QuerySanitizer.php
│   ├── StackTraceSanitizer.php
│   └── HeaderFilter.php
├── Support/
│   └── IpAnonymizer.php
routes/
│   └── guardian.php
resources/
│   └── views/
│       └── guardian/
│           ├── layout.blade.php
│           ├── overview.blade.php
│           ├── requests.blade.php
│           ├── queries.blade.php
│           ├── outgoing-http.blade.php
│           ├── jobs.blade.php
│           ├── mail.blade.php
│           ├── notifications.blade.php
│           ├── cache.blade.php
│           ├── exceptions.blade.php
│           ├── health.blade.php
│           └── partials/
│               ├── metric-card.blade.php
│               ├── chart.blade.php
│               ├── data-table.blade.php
│               └── date-filter.blade.php
```

## Config Additions

```php
'dashboard' => [
    'enabled' => true,
    'path' => 'guardian',
    'allowed_ips' => [],
    'poll_interval' => 30,
    'per_page' => 50,
],

'security' => [
    'sanitize_sql' => true,
    'anonymize_ip' => false,
    'hash_mail_recipients' => false,
    'safe_headers' => ['User-Agent', 'Referer', 'Accept', 'Content-Type'],
],
```

## New Tests

- `tests/Unit/Security/QuerySanitizerTest.php`
- `tests/Unit/Security/StackTraceSanitizerTest.php`
- `tests/Unit/Security/HeaderFilterTest.php`
- `tests/Unit/Support/IpAnonymizerTest.php`
- `tests/Feature/DashboardAccessTest.php`
- `tests/Feature/DashboardApiTest.php`
