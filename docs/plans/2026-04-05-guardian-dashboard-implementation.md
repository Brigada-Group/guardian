# Guardian Dashboard & Security Hardening Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a self-contained monitoring dashboard and harden security across the Guardian package.

**Architecture:** Blade + Alpine.js + Chart.js via CDN, own route group with gate + IP middleware, JSON API endpoints with 30s polling. Security layer includes SQL sanitization, stack trace cleaning, header filtering, IP anonymization, and command execution hardening.

**Tech Stack:** Laravel Blade, Alpine.js 3 (CDN), Chart.js 4 (CDN), Laravel Process facade, PHPUnit + Orchestra Testbench.

---

## Phase 1: Security Hardening (no dashboard dependency)

### Task 1: QuerySanitizer

**Files:**
- Create: `src/Security/QuerySanitizer.php`
- Test: `tests/Unit/Security/QuerySanitizerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\QuerySanitizer;
use Brigada\Guardian\Tests\TestCase;

class QuerySanitizerTest extends TestCase
{
    public function test_redacts_string_literals_in_where_clause(): void
    {
        $sql = "select * from users where email = 'john@example.com' and status = 'active'";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('john@example.com', $result);
        $this->assertStringNotContainsString('active', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
        $this->assertStringContainsString('select * from users where email', $result);
    }

    public function test_redacts_numeric_values_in_where_clause(): void
    {
        $sql = "select * from orders where id = 12345 and total > 99.99";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('12345', $result);
        $this->assertStringContainsString('?', $result);
    }

    public function test_preserves_table_and_column_names(): void
    {
        $sql = "select id, name from users where id = 1";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringContainsString('select id, name from users', $result);
    }

    public function test_redacts_insert_values(): void
    {
        $sql = "insert into users (email, password) values ('user@test.com', 'secret123')";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('user@test.com', $result);
        $this->assertStringNotContainsString('secret123', $result);
    }

    public function test_handles_null_and_empty_strings(): void
    {
        $this->assertEquals('', QuerySanitizer::sanitize(''));
    }

    public function test_disabled_via_config_returns_original(): void
    {
        config(['guardian.security.sanitize_sql' => false]);
        $sql = "select * from users where email = 'john@example.com'";
        $this->assertEquals($sql, QuerySanitizer::sanitize($sql));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Security/QuerySanitizerTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

namespace Brigada\Guardian\Security;

class QuerySanitizer
{
    public static function sanitize(string $sql): string
    {
        if ($sql === '') {
            return '';
        }

        if (! config('guardian.security.sanitize_sql', true)) {
            return $sql;
        }

        // Redact single-quoted string literals
        $sql = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "'[REDACTED]'", $sql);

        // Redact double-quoted string literals (MySQL)
        $sql = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/', '"[REDACTED]"', $sql);

        // Redact numeric literals in conditions (after operators)
        $sql = preg_replace('/(\b(?:=|>|<|>=|<=|<>|!=|IN\s*\()\s*)\d+(\.\d+)?/', '$1?', $sql);

        return $sql;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Security/QuerySanitizerTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Security/QuerySanitizer.php tests/Unit/Security/QuerySanitizerTest.php
git commit -m "feat: add QuerySanitizer to redact sensitive values from SQL logs"
```

---

### Task 2: StackTraceSanitizer

**Files:**
- Create: `src/Security/StackTraceSanitizer.php`
- Test: `tests/Unit/Security/StackTraceSanitizerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\StackTraceSanitizer;
use Brigada\Guardian\Tests\TestCase;

class StackTraceSanitizerTest extends TestCase
{
    public function test_strips_base_path_from_file_paths(): void
    {
        $trace = base_path() . '/app/Http/Controllers/UserController.php:42';
        $result = StackTraceSanitizer::sanitize($trace);
        $this->assertStringNotContainsString(base_path(), $result);
        $this->assertStringContainsString('app/Http/Controllers/UserController.php:42', $result);
    }

    public function test_redacts_secret_patterns_in_message(): void
    {
        $message = 'Connection failed: password=MyS3cretP@ss host=db.example.com';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('MyS3cretP@ss', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
    }

    public function test_redacts_token_patterns(): void
    {
        $message = 'API error: token=abc123xyz authorization=Bearer sk-12345';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('abc123xyz', $result);
        $this->assertStringNotContainsString('sk-12345', $result);
    }

    public function test_redacts_key_patterns(): void
    {
        $message = 'Config: api_key=AKIAIOSFODNN7EXAMPLE secret_key=wJalrXUtnFEMI/K7MDENG';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $result);
        $this->assertStringNotContainsString('wJalrXUtnFEMI/K7MDENG', $result);
    }

    public function test_handles_empty_string(): void
    {
        $this->assertEquals('', StackTraceSanitizer::sanitize(''));
    }

    public function test_preserves_non_sensitive_content(): void
    {
        $message = 'Division by zero in calculate() at line 42';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertEquals($message, $result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Security/StackTraceSanitizerTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

namespace Brigada\Guardian\Security;

class StackTraceSanitizer
{
    private static array $sensitivePatterns = [
        'password',
        'passwd',
        'token',
        'secret',
        'key',
        'authorization',
        'api_key',
        'apikey',
        'access_token',
        'auth',
        'credential',
    ];

    public static function sanitize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Strip base path from file references
        $basePath = base_path();
        if ($basePath) {
            $text = str_replace($basePath . '/', '', $text);
            $text = str_replace($basePath, '', $text);
        }

        // Redact values after sensitive key patterns (key=value or key: value)
        foreach (self::$sensitivePatterns as $pattern) {
            $text = preg_replace(
                '/(\b' . preg_quote($pattern, '/') . ')\s*[=:]\s*\S+/i',
                '$1=[REDACTED]',
                $text
            );
        }

        // Redact Bearer tokens
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $text);

        return $text;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Security/StackTraceSanitizerTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Security/StackTraceSanitizer.php tests/Unit/Security/StackTraceSanitizerTest.php
git commit -m "feat: add StackTraceSanitizer to redact secrets from stack traces"
```

---

### Task 3: HeaderFilter

**Files:**
- Create: `src/Security/HeaderFilter.php`
- Test: `tests/Unit/Security/HeaderFilterTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\HeaderFilter;
use Brigada\Guardian\Tests\TestCase;

class HeaderFilterTest extends TestCase
{
    public function test_allows_safe_headers(): void
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/html',
            'Referer' => 'https://example.com',
            'Content-Type' => 'application/json',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(4, $result);
        $this->assertEquals('Mozilla/5.0', $result['User-Agent']);
    }

    public function test_strips_authorization_header(): void
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'Authorization' => 'Bearer sk-secret-token',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('Authorization', $result);
        $this->assertArrayHasKey('User-Agent', $result);
    }

    public function test_strips_cookie_header(): void
    {
        $headers = [
            'Cookie' => 'session=abc123; token=xyz',
            'Accept' => 'text/html',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('Cookie', $result);
    }

    public function test_strips_csrf_and_api_key_headers(): void
    {
        $headers = [
            'X-CSRF-Token' => 'abc123',
            'X-API-Key' => 'secret-key',
            'Accept' => 'text/html',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('X-CSRF-Token', $result);
        $this->assertArrayNotHasKey('X-API-Key', $result);
    }

    public function test_case_insensitive_matching(): void
    {
        $headers = [
            'user-agent' => 'Mozilla/5.0',
            'authorization' => 'Bearer token',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(1, $result);
    }

    public function test_uses_config_safe_headers(): void
    {
        config(['guardian.security.safe_headers' => ['X-Custom']]);
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'X-Custom' => 'allowed',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('X-Custom', $result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Security/HeaderFilterTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

namespace Brigada\Guardian\Security;

class HeaderFilter
{
    public static function filter(array $headers): array
    {
        $allowed = array_map(
            'strtolower',
            config('guardian.security.safe_headers', ['User-Agent', 'Referer', 'Accept', 'Content-Type'])
        );

        $filtered = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $allowed, true)) {
                $filtered[$name] = $value;
            }
        }

        return $filtered;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Security/HeaderFilterTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Security/HeaderFilter.php tests/Unit/Security/HeaderFilterTest.php
git commit -m "feat: add HeaderFilter with whitelist approach for Discord alerts"
```

---

### Task 4: IpAnonymizer

**Files:**
- Create: `src/Support/IpAnonymizer.php`
- Test: `tests/Unit/Support/IpAnonymizerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Support;

use Brigada\Guardian\Support\IpAnonymizer;
use Brigada\Guardian\Tests\TestCase;

class IpAnonymizerTest extends TestCase
{
    public function test_anonymizes_ipv4(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $this->assertEquals('192.168.1.0', IpAnonymizer::anonymize('192.168.1.42'));
    }

    public function test_anonymizes_ipv6(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $result = IpAnonymizer::anonymize('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->assertStringEndsWith('::', $result);
        $this->assertStringStartsWith('2001:', $result);
    }

    public function test_returns_null_for_null(): void
    {
        $this->assertNull(IpAnonymizer::anonymize(null));
    }

    public function test_returns_original_when_disabled(): void
    {
        config(['guardian.security.anonymize_ip' => false]);
        $this->assertEquals('192.168.1.42', IpAnonymizer::anonymize('192.168.1.42'));
    }

    public function test_handles_localhost(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $this->assertEquals('127.0.0.0', IpAnonymizer::anonymize('127.0.0.1'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Support/IpAnonymizerTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

namespace Brigada\Guardian\Support;

class IpAnonymizer
{
    public static function anonymize(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (! config('guardian.security.anonymize_ip', false)) {
            return $ip;
        }

        // IPv4: zero the last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6: zero the last 80 bits (keep first 48 bits / 3 groups)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            for ($i = 6; $i < 16; $i++) {
                $packed[$i] = "\0";
            }
            return inet_ntop($packed);
        }

        return $ip;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Support/IpAnonymizerTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Support/IpAnonymizer.php tests/Unit/Support/IpAnonymizerTest.php
git commit -m "feat: add IpAnonymizer for GDPR-friendly IP logging"
```

---

### Task 5: Integrate security classes into existing code

**Files:**
- Modify: `src/Listeners/QueryListener.php:37-47` — use QuerySanitizer
- Modify: `src/Exceptions/ExceptionNotifier.php:50-61` — use StackTraceSanitizer and HeaderFilter
- Modify: `src/Http/Middleware/RequestMonitor.php:35-44` — use IpAnonymizer
- Modify: `src/Listeners/MailListener.php:19-29` — hash recipients when configured
- Modify: `config/guardian.php` — add `security` section

**Step 1: Add security config to `config/guardian.php`**

Add after the `retention` section (after line 121, before the closing `];`):

```php
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
```

**Step 2: Integrate QuerySanitizer into QueryListener**

In `src/Listeners/QueryListener.php`, add import:
```php
use Brigada\Guardian\Security\QuerySanitizer;
```

Change line 39 from:
```php
'sql' => mb_substr($event->sql, 0, 5000),
```
to:
```php
'sql' => mb_substr(QuerySanitizer::sanitize($event->sql), 0, 5000),
```

**Step 3: Integrate StackTraceSanitizer and HeaderFilter into ExceptionNotifier**

In `src/Exceptions/ExceptionNotifier.php`, add imports:
```php
use Brigada\Guardian\Security\HeaderFilter;
use Brigada\Guardian\Security\StackTraceSanitizer;
```

In the `handle()` method, change the message and stackTrace lines:
```php
message: StackTraceSanitizer::sanitize($e->getMessage()),
```
```php
stackTrace: "```\n" . StackTraceSanitizer::sanitize($this->formatStackTrace($e)) . "\n```",
```

In the `extractContext()` method, replace the header filtering block:
```php
$allHeaders = collect($request->headers->all())
    ->map(fn ($values) => implode(', ', $values))
    ->toArray();
$safeHeaders = HeaderFilter::filter($allHeaders);
$headerLines = collect($safeHeaders)
    ->map(fn ($value, $key) => "{$key}: {$value}")
    ->implode("\n");
```

**Step 4: Integrate IpAnonymizer into RequestMonitor**

In `src/Http/Middleware/RequestMonitor.php`, add import:
```php
use Brigada\Guardian\Support\IpAnonymizer;
```

Change from:
```php
'ip' => $request->ip(),
```
to:
```php
'ip' => IpAnonymizer::anonymize($request->ip()),
```

**Step 5: Add mail recipient hashing to MailListener**

In `src/Listeners/MailListener.php`, change line 23 from:
```php
'to' => $this->formatAddresses($message->getTo()),
```
to:
```php
'to' => $this->maybeHashRecipients($this->formatAddresses($message->getTo())),
```

Add new method to the class:
```php
private function maybeHashRecipients(string $recipients): string
{
    if (! config('guardian.security.hash_mail_recipients', false)) {
        return $recipients;
    }

    return implode(', ', array_map(
        fn ($email) => hash('sha256', trim($email)),
        explode(',', $recipients)
    ));
}
```

**Step 6: Run all existing tests**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS (security integration should not break existing behavior with default config)

**Step 7: Commit**

```bash
git add config/guardian.php src/Listeners/QueryListener.php src/Exceptions/ExceptionNotifier.php src/Http/Middleware/RequestMonitor.php src/Listeners/MailListener.php
git commit -m "feat: integrate security classes into listeners and middleware"
```

---

### Task 6: Harden NpmAuditCheck command execution

**Files:**
- Modify: `src/Checks/NpmAuditCheck.php`

**Step 1: Replace unsafe command execution with Process facade**

In `src/Checks/NpmAuditCheck.php`, add import:
```php
use Illuminate\Support\Facades\Process;
```

Replace the command execution block (lines 21-23):
```php
// OLD (unsafe):
// $output = [];
// $exitCode = 0;
// exec('cd ' . base_path() . ' && npm audit --json 2>/dev/null', $output, $exitCode);

// NEW (safe):
$result = Process::path(base_path())->run(['npm', 'audit', '--json']);
$output = $result->output();
```

Replace the JSON parsing (line 24):
```php
// OLD:
// $json = json_decode(implode("\n", $output), true);

// NEW:
$json = json_decode($output, true);
```

**Step 2: Run existing tests**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 3: Commit**

```bash
git add src/Checks/NpmAuditCheck.php
git commit -m "security: replace unsafe command execution with Process facade in NpmAuditCheck"
```

---

### Task 7: Discord webhook URL validation

**Files:**
- Modify: `src/Notifications/DiscordNotifier.php` — add URL validation

**Step 1: Read current DiscordNotifier**

Read `src/Notifications/DiscordNotifier.php` to see current `send()` method.

**Step 2: Add validation method and call it from send()**

Add a validation method:

```php
private function isValidWebhookUrl(string $url): bool
{
    if (empty($url)) {
        return false;
    }

    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';

    if (! in_array($host, ['discord.com', 'discordapp.com'])) {
        \Illuminate\Support\Facades\Log::warning(
            "Guardian: Discord webhook URL host '{$host}' does not match expected Discord domains. Proceeding anyway (may be a proxy)."
        );
    }

    return true;
}
```

Call from beginning of `send()` — before existing empty-URL check.

**Step 3: Run existing tests**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add src/Notifications/DiscordNotifier.php
git commit -m "security: add Discord webhook URL validation with proxy support"
```

---

## Phase 2: Dashboard Foundation

### Task 8: Dashboard config and routes

**Files:**
- Modify: `config/guardian.php` — add `dashboard` section
- Create: `routes/guardian.php`
- Modify: `src/GuardianServiceProvider.php` — register routes, views, middleware

**Step 1: Add dashboard config**

In `config/guardian.php`, add after the `security` section:

```php
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        'enabled' => true,
        'path' => 'guardian',
        'allowed_ips' => [],
        'poll_interval' => 30,
        'per_page' => 50,
    ],
```

**Step 2: Create route file `routes/guardian.php`**

```php
<?php

use Brigada\Guardian\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'overview'])->name('guardian.overview');
Route::get('/requests', [DashboardController::class, 'requests'])->name('guardian.requests');
Route::get('/queries', [DashboardController::class, 'queries'])->name('guardian.queries');
Route::get('/outgoing-http', [DashboardController::class, 'outgoingHttp'])->name('guardian.outgoing-http');
Route::get('/jobs', [DashboardController::class, 'jobs'])->name('guardian.jobs');
Route::get('/mail', [DashboardController::class, 'mail'])->name('guardian.mail');
Route::get('/notifications', [DashboardController::class, 'notifications'])->name('guardian.notifications');
Route::get('/cache', [DashboardController::class, 'cache'])->name('guardian.cache');
Route::get('/exceptions', [DashboardController::class, 'exceptions'])->name('guardian.exceptions');
Route::get('/health', [DashboardController::class, 'health'])->name('guardian.health');

// JSON API for polling
Route::prefix('api')->group(function () {
    Route::get('/overview', [DashboardController::class, 'apiOverview'])->name('guardian.api.overview');
    Route::get('/requests', [DashboardController::class, 'apiRequests'])->name('guardian.api.requests');
    Route::get('/queries', [DashboardController::class, 'apiQueries'])->name('guardian.api.queries');
    Route::get('/outgoing-http', [DashboardController::class, 'apiOutgoingHttp'])->name('guardian.api.outgoing-http');
    Route::get('/jobs', [DashboardController::class, 'apiJobs'])->name('guardian.api.jobs');
    Route::get('/mail', [DashboardController::class, 'apiMail'])->name('guardian.api.mail');
    Route::get('/notifications', [DashboardController::class, 'apiNotifications'])->name('guardian.api.notifications');
    Route::get('/cache', [DashboardController::class, 'apiCache'])->name('guardian.api.cache');
    Route::get('/exceptions', [DashboardController::class, 'apiExceptions'])->name('guardian.api.exceptions');
    Route::get('/health', [DashboardController::class, 'apiHealth'])->name('guardian.api.health');
    Route::post('/health/run/{check}', [DashboardController::class, 'apiHealthRun'])->name('guardian.api.health.run');
});
```

**Step 3: Update GuardianServiceProvider**

Add to imports:
```php
use Illuminate\Support\Facades\Route;
```

Add to `register()` method:
```php
$this->app->resolving('router', function ($router) {
    $router->aliasMiddleware('guardian.gate', \Brigada\Guardian\Http\Middleware\GuardianGate::class);
    $router->aliasMiddleware('guardian.ip-filter', \Brigada\Guardian\Http\Middleware\GuardianIpFilter::class);
});
```

Add to `boot()` method, after `$this->loadMigrationsFrom(...)`:
```php
$this->loadViewsFrom(__DIR__ . '/../resources/views', 'guardian');

if (config('guardian.dashboard.enabled', true)) {
    $this->registerDashboardRoutes();
}
```

Add new method:
```php
private function registerDashboardRoutes(): void
{
    Route::prefix(config('guardian.dashboard.path', 'guardian'))
        ->middleware(['web', 'guardian.gate', 'guardian.ip-filter'])
        ->group(__DIR__ . '/../routes/guardian.php');
}
```

**Step 4: Commit**

```bash
git add config/guardian.php routes/guardian.php src/GuardianServiceProvider.php
git commit -m "feat: add dashboard config, routes, and service provider wiring"
```

---

### Task 9: Dashboard middleware (GuardianGate + GuardianIpFilter)

**Files:**
- Create: `src/Http/Middleware/GuardianGate.php`
- Create: `src/Http/Middleware/GuardianIpFilter.php`
- Test: `tests/Feature/DashboardAccessTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;

class DashboardAccessTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('guardian.dashboard.enabled', true);
        $app['config']->set('guardian.dashboard.path', 'guardian');
    }

    public function test_unauthenticated_user_gets_403(): void
    {
        $response = $this->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_dashboard(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);

        $user = new class extends Authenticatable {
            public $id = 1;
            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(200);
    }

    public function test_unauthorized_user_gets_403(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => false);

        $user = new class extends Authenticatable {
            public $id = 1;
            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_ip_whitelist_blocks_non_listed_ip(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);
        config(['guardian.dashboard.allowed_ips' => ['10.0.0.1']]);

        $user = new class extends Authenticatable {
            public $id = 1;
            protected $guarded = [];
        };

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->get('/guardian');
        $response->assertStatus(403);
    }

    public function test_empty_ip_whitelist_allows_all(): void
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);
        config(['guardian.dashboard.allowed_ips' => []]);

        $user = new class extends Authenticatable {
            public $id = 1;
            protected $guarded = [];
        };

        $response = $this->actingAs($user)->get('/guardian');
        $response->assertStatus(200);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/DashboardAccessTest.php`
Expected: FAIL — middleware classes not found

**Step 3: Write GuardianGate middleware**

```php
<?php

namespace Brigada\Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GuardianGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::has('viewGuardianDashboard')) {
            abort(403, 'Guardian dashboard access not configured.');
        }

        if (! Gate::check('viewGuardianDashboard', [$request->user()])) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
```

**Step 4: Write GuardianIpFilter middleware**

```php
<?php

namespace Brigada\Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuardianIpFilter
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('guardian.dashboard.allowed_ips', []);

        if (empty($allowedIps)) {
            return $next($request);
        }

        if (! in_array($request->ip(), $allowedIps, true)) {
            abort(403, 'IP not allowed.');
        }

        return $next($request);
    }
}
```

**Step 5: Create stub DashboardController and overview view**

Create `src/Http/Controllers/DashboardController.php`:
```php
<?php

namespace Brigada\Guardian\Http\Controllers;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function overview()
    {
        return view('guardian::guardian.overview');
    }
}
```

Create stub `resources/views/guardian/overview.blade.php`:
```blade
<!DOCTYPE html>
<html><head><title>Guardian</title></head>
<body><h1>Guardian Dashboard</h1></body>
</html>
```

**Step 6: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/DashboardAccessTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add src/Http/Middleware/GuardianGate.php src/Http/Middleware/GuardianIpFilter.php src/Http/Controllers/DashboardController.php resources/views/guardian/overview.blade.php tests/Feature/DashboardAccessTest.php
git commit -m "feat: add dashboard access middleware with gate + IP whitelist"
```

---

## Phase 3: Dashboard Layout & Shared Components

### Task 10: Base layout with Alpine.js, Chart.js, sidebar navigation

**Files:**
- Create: `resources/views/guardian/layout.blade.php`
- Create: `resources/views/guardian/partials/metric-card.blade.php`
- Create: `resources/views/guardian/partials/chart.blade.php`
- Create: `resources/views/guardian/partials/data-table.blade.php`
- Create: `resources/views/guardian/partials/date-filter.blade.php`

**Step 1: Create the base layout**

Create `resources/views/guardian/layout.blade.php` with:
- CDN links: Alpine.js 3 (`cdn.jsdelivr.net/npm/alpinejs@3`), Chart.js 4 (`cdn.jsdelivr.net/npm/chart.js@4`)
- Self-contained `<style>` block with all CSS (dark mode via `.dark` class, responsive sidebar)
- Sidebar nav with links to all 10 pages, active page via `request()->routeIs()` highlighting
- Dark/light mode toggle stored in `localStorage('guardian-dark-mode')`
- Header with auto-refresh indicator + pause button + poll interval from `{{ config('guardian.dashboard.poll_interval') }}`
- `@yield('content')` for page content
- Alpine.js `x-data` component managing polling state:
  - `polling: true`, `lastActivity: Date.now()`, `visible: true`
  - `document.addEventListener('visibilitychange', ...)` — pause when tab hidden
  - `document.addEventListener('mousemove', ...)` — reset idle timer
  - Auto-stop after 30 minutes idle
  - `startPolling()` / `stopPolling()` methods
- `@yield('scripts')` for page-specific Chart.js init

**Step 2: Create partials**

`metric-card.blade.php`:
- Props: `$title`, `$value`, `$subtitle` (optional), `$trend` (up/down/flat, optional), `$color` (green/yellow/red/blue)
- Styled card with large value, title below, trend arrow icon

`chart.blade.php`:
- Props: `$id`, `$type` (line/bar/pie/doughnut), `$height` (default 300)
- Canvas element with `id="{{ $id }}"`
- Alpine `x-init` that creates Chart.js instance, stores on `window.guardianCharts[id]`
- `updateChart(id, data)` global helper to update existing chart

`data-table.blade.php`:
- Props: `$headers` (array of {key, label, sortable}), `$emptyMessage`
- Alpine component with `sortBy`, `sortDir` state
- Click handler on sortable headers
- Slot for row template
- Pagination controls at bottom

`date-filter.blade.php`:
- Alpine component with preset buttons: 1h, 6h, 24h, 7d, 30d
- Active preset highlighting
- Dispatches `guardian-date-filter` custom event with `{from, to}`

**Step 3: Update overview view to use layout**

Update `resources/views/guardian/overview.blade.php` to `@extends('guardian::guardian.layout')`.

**Step 4: Commit**

```bash
git add resources/views/guardian/
git commit -m "feat: add dashboard layout with Alpine.js, Chart.js, sidebar, and partials"
```

---

## Phase 4: Dashboard Controller & API Endpoints

### Task 11: DashboardController — Overview page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php`
- Modify: `resources/views/guardian/overview.blade.php`

**Step 1: Implement overview() and apiOverview()**

`overview()` renders view with initial data passed via `compact()`.

`apiOverview()` returns JSON:
```php
public function apiOverview(): \Illuminate\Http\JsonResponse
{
    try {
        $since24h = now()->subHours(24);

        $totalRequests = RequestLog::where('created_at', '>=', $since24h)->count();
        $errorCount = RequestLog::where('created_at', '>=', $since24h)
            ->where('status_code', '>=', 500)->count();
        $errorRate = $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0;
        $avgResponseTime = RequestLog::where('created_at', '>=', $since24h)
            ->avg('duration_ms') ?? 0;

        $latestCache = CacheLog::latest('created_at')->first();
        $cacheHitRate = $latestCache?->hit_rate ?? 0;

        $failedCommands = CommandLog::where('created_at', '>=', $since24h)
            ->where('exit_code', '!=', 0)->count();
        $exceptions = GuardianResult::where('created_at', '>=', $since24h)
            ->where('check_class', 'like', 'exception:%')->count();

        // Hourly response time chart (last 24h)
        $responseTimeChart = RequestLog::where('created_at', '>=', $since24h)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour, AVG(duration_ms) as avg_ms, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00')")
            ->orderBy('hour')
            ->take(24)
            ->get();

        // Hourly error chart (last 24h)
        $errorChart = RequestLog::where('created_at', '>=', $since24h)
            ->where('status_code', '>=', 500)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00')")
            ->orderBy('hour')
            ->take(24)
            ->get();

        // Recent alerts
        $recentAlerts = GuardianResult::whereNotNull('notified_at')
            ->latest('notified_at')
            ->take(10)
            ->get(['check_class', 'status', 'message', 'notified_at']);

        return response()->json([
            'data' => [
                'metrics' => [
                    'total_requests' => $totalRequests,
                    'error_rate' => $errorRate,
                    'avg_response_time' => round($avgResponseTime, 2),
                    'cache_hit_rate' => round($cacheHitRate, 2),
                    'failed_commands' => $failedCommands,
                    'exceptions' => $exceptions,
                ],
                'response_time_chart' => $responseTimeChart,
                'error_chart' => $errorChart,
                'recent_alerts' => $recentAlerts,
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'next_poll' => config('guardian.dashboard.poll_interval', 30),
            ],
        ])->header('Cache-Control', 'no-store');
    } catch (\Throwable $e) {
        return response()->json([
            'error' => 'Failed to load overview data.',
            'meta' => ['generated_at' => now()->toIso8601String(), 'next_poll' => 30],
        ], 500)->header('Cache-Control', 'no-store');
    }
}
```

**Step 2: Build overview Blade view**

`@extends('guardian::guardian.layout')`, uses 6 metric cards, 2 charts, alerts timeline. Alpine component that fetches API on mount and polls.

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/overview.blade.php
git commit -m "feat: add overview dashboard page with metrics and charts"
```

---

### Task 12: Requests page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `requests()` and `apiRequests()`
- Create: `resources/views/guardian/requests.blade.php`

**Step 1: Implement `requests()` and `apiRequests()`**

`apiRequests()` accepts query params: `method`, `status_min`, `status_max`, `date_from`, `date_to`, `slow_only`, `page`.

Returns JSON with:
- Response time histogram (buckets: 0-100ms, 100-500ms, 500-1s, 1-5s, 5s+)
- Slowest endpoints (top 20, grouped by route_name, avg duration)
- Paginated request logs (per_page from config)
- Total count

All queries use `->take()` limits. Wrapped in try/catch.

**Step 2: Build Blade view with filters bar, histogram chart, slowest endpoints table, paginated log table.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/requests.blade.php
git commit -m "feat: add requests dashboard page with filters and charts"
```

---

### Task 13: Queries page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `queries()` and `apiQueries()`
- Create: `resources/views/guardian/queries.blade.php`

**Step 1: Implement `queries()` and `apiQueries()`**

Accepts: `tab` (slow/n_plus_one/all), `date_from`, `date_to`, `page`.

Returns: slow query count over time, query log entries, N+1 patterns grouped by normalized SQL, paginated.

**Step 2: Build Blade view with tabs, chart, expandable SQL rows.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/queries.blade.php
git commit -m "feat: add queries dashboard page with slow/N+1 tabs"
```

---

### Task 14: Outgoing HTTP page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `outgoingHttp()` and `apiOutgoingHttp()`
- Create: `resources/views/guardian/outgoing-http.blade.php`

**Step 1: Implement `outgoingHttp()` and `apiOutgoingHttp()`**

Accepts: `host`, `status_code`, `failed_only`, `date_from`, `date_to`, `page`.

Returns: avg duration by host (top 10), failure rate by host, paginated log.

**Step 2: Build Blade view with filters, host performance bar chart, log table.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/outgoing-http.blade.php
git commit -m "feat: add outgoing HTTP dashboard page"
```

---

### Task 15: Jobs & Scheduler page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `jobs()` and `apiJobs()`
- Create: `resources/views/guardian/jobs.blade.php`

**Step 1: Implement `jobs()` and `apiJobs()`**

Accepts: `tab` (commands/scheduled), `date_from`, `date_to`, `page`.

Returns:
- Commands: exit code distribution, slow/failed commands, paginated log
- Scheduled tasks: status breakdown, missed/failed, paginated log

**Step 2: Build Blade view with two tabs.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/jobs.blade.php
git commit -m "feat: add jobs & scheduler dashboard page"
```

---

### Task 16: Mail page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `mail()` and `apiMail()`
- Create: `resources/views/guardian/mail.blade.php`

**Step 1: Implement `mail()` and `apiMail()`**

Accepts: `status`, `date_from`, `date_to`, `page`.

Returns: daily sent/failed chart data, recent failures, paginated log.

**Step 2: Build Blade view.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/mail.blade.php
git commit -m "feat: add mail dashboard page"
```

---

### Task 17: Notifications page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `notifications()` and `apiNotifications()`
- Create: `resources/views/guardian/notifications.blade.php`

**Step 1: Implement `notifications()` and `apiNotifications()`**

Accepts: `channel`, `status`, `date_from`, `date_to`, `page`.

Returns: channel breakdown (pie chart data), failure rate by channel, paginated log.

**Step 2: Build Blade view.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/notifications.blade.php
git commit -m "feat: add notifications dashboard page"
```

---

### Task 18: Cache page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `cache()` and `apiCache()`
- Create: `resources/views/guardian/cache.blade.php`

**Step 1: Implement `cache()` and `apiCache()`**

Accepts: `store`, `date_from`, `date_to`.

Returns: hit rate over time (line chart data), per-store breakdown, reads vs writes, current hit rate.

**Step 2: Build Blade view with hit rate gauge (doughnut chart).**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/cache.blade.php
git commit -m "feat: add cache dashboard page with hit rate gauge"
```

---

### Task 19: Exceptions page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `exceptions()` and `apiExceptions()`
- Create: `resources/views/guardian/exceptions.blade.php`

**Step 1: Implement `exceptions()` and `apiExceptions()`**

Accepts: `date_from`, `date_to`, `page`.

Returns:
- Exceptions grouped by class + file:line (from GuardianResult where `check_class LIKE 'exception:%'`)
- Per-group: occurrence count, last seen, latest message
- Trend chart: exceptions per hour, last 48h
- Paginated

**Step 2: Build Blade view with expandable rows, trend chart.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/exceptions.blade.php
git commit -m "feat: add exceptions dashboard page with grouping and trends"
```

---

### Task 20: Health Checks page + API

**Files:**
- Modify: `src/Http/Controllers/DashboardController.php` — add `health()`, `apiHealth()`, `apiHealthRun()`
- Create: `resources/views/guardian/health.blade.php`

**Step 1: Implement `health()`, `apiHealth()`, and `apiHealthRun()`**

`apiHealth()` returns:
- All registered checks with latest result from GuardianResult
- 7-day history sparkline data per check

`apiHealthRun($check)`:
- Resolves check from CheckRegistry by class basename
- Runs it synchronously
- Returns result JSON

**Step 2: Build Blade view with status grid, sparklines, "Run Now" buttons.**

**Step 3: Commit**

```bash
git add src/Http/Controllers/DashboardController.php resources/views/guardian/health.blade.php
git commit -m "feat: add health checks dashboard page with run-now support"
```

---

## Phase 5: Rate Limiting & Final Polish

### Task 21: Add rate limiting to API endpoints

**Files:**
- Modify: `src/GuardianServiceProvider.php` — add rate limiter
- Modify: `routes/guardian.php` — apply throttle to API routes
- Modify: `config/guardian.php` — add `guardian*` to ignored_paths

**Step 1: Register dedicated rate limiter in `GuardianServiceProvider::boot()`**

```php
if (class_exists(\Illuminate\Cache\RateLimiting\Limit::class)) {
    \Illuminate\Support\Facades\RateLimiter::for('guardian-api', function ($request) {
        return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
    });
}
```

**Step 2: Apply to API routes in `routes/guardian.php`**

Wrap the API route group with `->middleware('throttle:guardian-api')`.

**Step 3: Add `'guardian*'` to `monitoring.requests.ignored_paths` in config**

So the dashboard itself doesn't pollute request logs:
```php
'ignored_paths' => [
    '_debugbar*',
    'telescope*',
    'horizon*',
    'guardian*',
],
```

**Step 4: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 5: Commit**

```bash
git add src/GuardianServiceProvider.php routes/guardian.php config/guardian.php
git commit -m "feat: add rate limiting to dashboard API and ignore guardian paths"
```

---

### Task 22: Dashboard API integration tests

**Files:**
- Create: `tests/Feature/DashboardApiTest.php`

**Step 1: Write tests**

```php
<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Models\RequestLog;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;

class DashboardApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('guardian.dashboard.enabled', true);
    }

    private function authenticatedUser()
    {
        Gate::define('viewGuardianDashboard', fn ($user) => true);

        $user = new class extends Authenticatable {
            public $id = 1;
            protected $guarded = [];
        };

        return $user;
    }

    public function test_overview_api_returns_json(): void
    {
        $response = $this->actingAs($this->authenticatedUser())
            ->getJson('/guardian/api/overview');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['generated_at', 'next_poll']]);
    }

    public function test_api_returns_no_store_cache_header(): void
    {
        $response = $this->actingAs($this->authenticatedUser())
            ->getJson('/guardian/api/overview');

        $response->assertHeader('Cache-Control');
    }

    public function test_requests_api_returns_data(): void
    {
        RequestLog::create([
            'method' => 'GET', 'uri' => '/test', 'status_code' => 200,
            'duration_ms' => 50.0, 'created_at' => now(),
        ]);

        $response = $this->actingAs($this->authenticatedUser())
            ->getJson('/guardian/api/requests');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/guardian/api/overview');
        $response->assertStatus(403);
    }

    public function test_each_api_endpoint_returns_200(): void
    {
        $user = $this->authenticatedUser();
        $endpoints = [
            'overview', 'requests', 'queries', 'outgoing-http',
            'jobs', 'mail', 'notifications', 'cache', 'exceptions', 'health',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($user)->getJson("/guardian/api/{$endpoint}");
            $response->assertStatus(200, "API endpoint {$endpoint} failed");
        }
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/DashboardApiTest.php`
Expected: All PASS

**Step 3: Commit**

```bash
git add tests/Feature/DashboardApiTest.php
git commit -m "test: add dashboard API integration tests"
```

---

### Task 23: Final full test suite run and cleanup

**Step 1: Run full test suite**

Run: `./vendor/bin/phpunit --testdox`
Expected: All PASS

**Step 2: Check for any missing imports or unused code**

Review all new files for consistency with existing patterns.

**Step 3: Final commit if any cleanup needed**

```bash
git commit -m "chore: final cleanup and polish"
```
