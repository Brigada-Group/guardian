# Guardian Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build `brigada/guardian`, a private Composer package that monitors Laravel projects for security, health, and performance issues and reports to Discord.

**Architecture:** Modular check classes implementing a `HealthCheck` interface, grouped by schedule frequency. A service provider auto-registers scheduler commands. Results stored in DB for deduplication. Discord webhook embeds for notifications.

**Tech Stack:** PHP 8.2+, Laravel 11+, Discord Webhooks, Composer package (with ServiceProvider auto-discovery)

---

### Task 1: Package Scaffolding

**Files:**
- Create: `composer.json`
- Create: `src/GuardianServiceProvider.php`
- Create: `config/guardian.php`
- Create: `database/migrations/2026_03_19_000000_create_guardian_results_table.php`

**Step 1: Create composer.json**

```json
{
    "name": "brigada/guardian",
    "description": "Laravel project monitoring package — security audits, health checks, Discord notifications",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0",
        "illuminate/console": "^11.0|^12.0",
        "illuminate/database": "^11.0|^12.0",
        "illuminate/http": "^11.0|^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0|^10.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Brigada\\Guardian\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Brigada\\Guardian\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Brigada\\Guardian\\GuardianServiceProvider"
            ]
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

**Step 2: Create config/guardian.php**

```php
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
```

**Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_class');
            $table->string('status'); // ok, warning, critical, error
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['check_class', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_results');
    }
};
```

**Step 4: Create a minimal ServiceProvider (will expand later)**

```php
<?php

namespace Brigada\Guardian;

use Illuminate\Support\ServiceProvider;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/guardian.php', 'guardian');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

**Step 5: Run composer install and verify autoloading**

Run: `cd /Users/endritsaiti/Sites/bg-check && composer install`
Expected: Dependencies installed, autoloader generated.

**Step 6: Commit**

```bash
git add composer.json composer.lock src/GuardianServiceProvider.php config/guardian.php database/
git commit -m "feat: scaffold guardian package with config and migration"
```

---

### Task 2: Enums and Core Contracts

**Files:**
- Create: `src/Enums/Status.php`
- Create: `src/Enums/Schedule.php`
- Create: `src/Enums/Severity.php`
- Create: `src/Contracts/HealthCheck.php`
- Create: `src/Results/CheckResult.php`
- Create: `tests/Unit/CheckResultTest.php`

**Step 1: Write the test for CheckResult**

```php
<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use PHPUnit\Framework\TestCase;

class CheckResultTest extends TestCase
{
    public function test_it_creates_a_check_result(): void
    {
        $result = new CheckResult(
            status: Status::Ok,
            message: 'All good',
            metadata: ['disk_percent' => 42.5],
        );

        $this->assertSame(Status::Ok, $result->status);
        $this->assertSame('All good', $result->message);
        $this->assertSame(['disk_percent' => 42.5], $result->metadata);
    }

    public function test_it_identifies_critical_status(): void
    {
        $result = new CheckResult(Status::Critical, 'Disk full');

        $this->assertTrue($result->isCritical());
        $this->assertFalse($result->isOk());
    }

    public function test_it_identifies_ok_status(): void
    {
        $result = new CheckResult(Status::Ok, 'Fine');

        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isCritical());
        $this->assertFalse($result->isWarning());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `cd /Users/endritsaiti/Sites/bg-check && ./vendor/bin/phpunit tests/Unit/CheckResultTest.php -v`
Expected: FAIL — classes don't exist yet.

**Step 3: Create Status enum**

```php
<?php

namespace Brigada\Guardian\Enums;

enum Status: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Error = 'error';
}
```

**Step 4: Create Schedule enum**

```php
<?php

namespace Brigada\Guardian\Enums;

enum Schedule: string
{
    case EveryFiveMinutes = 'every_5_min';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
```

**Step 5: Create Severity enum**

```php
<?php

namespace Brigada\Guardian\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';
}
```

**Step 6: Create HealthCheck interface**

```php
<?php

namespace Brigada\Guardian\Contracts;

use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Results\CheckResult;

interface HealthCheck
{
    public function name(): string;

    public function schedule(): Schedule;

    public function run(): CheckResult;
}
```

**Step 7: Create CheckResult value object**

```php
<?php

namespace Brigada\Guardian\Results;

use Brigada\Guardian\Enums\Status;

class CheckResult
{
    public function __construct(
        public readonly Status $status,
        public readonly string $message,
        public readonly array $metadata = [],
    ) {}

    public function isOk(): bool
    {
        return $this->status === Status::Ok;
    }

    public function isWarning(): bool
    {
        return $this->status === Status::Warning;
    }

    public function isCritical(): bool
    {
        return $this->status === Status::Critical;
    }

    public function isError(): bool
    {
        return $this->status === Status::Error;
    }
}
```

**Step 8: Run tests to verify they pass**

Run: `cd /Users/endritsaiti/Sites/bg-check && ./vendor/bin/phpunit tests/Unit/CheckResultTest.php -v`
Expected: 3 tests, 3 passed.

**Step 9: Commit**

```bash
git add src/Enums/ src/Contracts/ src/Results/ tests/Unit/CheckResultTest.php
git commit -m "feat: add enums, HealthCheck contract, and CheckResult value object"
```

---

### Task 3: CheckRegistry and Deduplicator

**Files:**
- Create: `src/Support/CheckRegistry.php`
- Create: `src/Support/Deduplicator.php`
- Create: `src/Models/GuardianResult.php`
- Create: `tests/Unit/CheckRegistryTest.php`
- Create: `tests/Unit/DeduplicatorTest.php`

**Step 1: Write the test for CheckRegistry**

```php
<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Support\CheckRegistry;
use PHPUnit\Framework\TestCase;

class CheckRegistryTest extends TestCase
{
    public function test_it_registers_and_retrieves_checks(): void
    {
        $registry = new CheckRegistry();
        $check = $this->createFakeCheck('Disk Space', Schedule::Hourly);

        $registry->register($check);

        $hourlyChecks = $registry->forSchedule(Schedule::Hourly);
        $this->assertCount(1, $hourlyChecks);
        $this->assertSame('Disk Space', $hourlyChecks[0]->name());
    }

    public function test_it_returns_empty_for_schedule_with_no_checks(): void
    {
        $registry = new CheckRegistry();

        $this->assertSame([], $registry->forSchedule(Schedule::Daily));
    }

    public function test_it_returns_all_checks(): void
    {
        $registry = new CheckRegistry();
        $registry->register($this->createFakeCheck('A', Schedule::Hourly));
        $registry->register($this->createFakeCheck('B', Schedule::Daily));

        $this->assertCount(2, $registry->all());
    }

    private function createFakeCheck(string $name, Schedule $schedule): HealthCheck
    {
        return new class($name, $schedule) implements HealthCheck {
            public function __construct(private string $n, private Schedule $s) {}
            public function name(): string { return $this->n; }
            public function schedule(): Schedule { return $this->s; }
            public function run(): CheckResult { return new CheckResult(Status::Ok, 'ok'); }
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/CheckRegistryTest.php -v`
Expected: FAIL.

**Step 3: Implement CheckRegistry**

```php
<?php

namespace Brigada\Guardian\Support;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;

class CheckRegistry
{
    /** @var HealthCheck[] */
    private array $checks = [];

    public function register(HealthCheck $check): void
    {
        $this->checks[] = $check;
    }

    /** @return HealthCheck[] */
    public function forSchedule(Schedule $schedule): array
    {
        return array_values(
            array_filter($this->checks, fn (HealthCheck $c) => $c->schedule() === $schedule)
        );
    }

    /** @return HealthCheck[] */
    public function all(): array
    {
        return $this->checks;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/CheckRegistryTest.php -v`
Expected: 3 tests, 3 passed.

**Step 5: Create GuardianResult model**

```php
<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class GuardianResult extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_results';

    protected $fillable = [
        'check_class',
        'status',
        'message',
        'metadata',
        'notified_at',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
```

**Step 6: Write the test for Deduplicator**

```php
<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class DeduplicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    public function test_it_allows_notification_when_no_prior_result(): void
    {
        $dedup = new Deduplicator();
        $result = new CheckResult(Status::Critical, 'Disk full');

        $this->assertTrue($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_blocks_duplicate_notification_within_window(): void
    {
        $dedup = new Deduplicator();

        GuardianResult::create([
            'check_class' => 'DiskSpaceCheck',
            'status' => 'critical',
            'message' => 'Disk full',
            'notified_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
        ]);

        $result = new CheckResult(Status::Critical, 'Disk full');
        $this->assertFalse($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_allows_notification_after_window_expires(): void
    {
        $dedup = new Deduplicator();

        GuardianResult::create([
            'check_class' => 'DiskSpaceCheck',
            'status' => 'critical',
            'message' => 'Disk full',
            'notified_at' => now()->subMinutes(120),
            'created_at' => now()->subMinutes(120),
        ]);

        $result = new CheckResult(Status::Critical, 'Disk full');
        $this->assertTrue($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_always_allows_ok_status(): void
    {
        $dedup = new Deduplicator();
        $result = new CheckResult(Status::Ok, 'All good');

        $this->assertFalse($dedup->shouldNotify('DiskSpaceCheck', $result));
    }
}
```

**Step 7: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/DeduplicatorTest.php -v`
Expected: FAIL.

**Step 8: Implement Deduplicator**

```php
<?php

namespace Brigada\Guardian\Support;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;

class Deduplicator
{
    public function shouldNotify(string $checkClass, CheckResult $result): bool
    {
        if ($result->status === Status::Ok) {
            return false;
        }

        $dedupMinutes = config('guardian.notifications.dedup_minutes', 60);

        $lastNotified = GuardianResult::where('check_class', $checkClass)
            ->whereNotNull('notified_at')
            ->where('status', $result->status->value)
            ->latest('notified_at')
            ->first();

        if (! $lastNotified) {
            return true;
        }

        return $lastNotified->notified_at->diffInMinutes(now()) >= $dedupMinutes;
    }

    public function record(string $checkClass, CheckResult $result, bool $notified): void
    {
        GuardianResult::create([
            'check_class' => $checkClass,
            'status' => $result->status->value,
            'message' => $result->message,
            'metadata' => $result->metadata,
            'notified_at' => $notified ? now() : null,
            'created_at' => now(),
        ]);
    }

    public function prune(int $days = 30): int
    {
        return GuardianResult::where('created_at', '<', now()->subDays($days))->delete();
    }
}
```

**Step 9: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/DeduplicatorTest.php -v`
Expected: 4 tests, 4 passed.

**Step 10: Commit**

```bash
git add src/Support/ src/Models/ tests/Unit/CheckRegistryTest.php tests/Unit/DeduplicatorTest.php
git commit -m "feat: add CheckRegistry, Deduplicator, and GuardianResult model"
```

---

### Task 4: Discord Notifications

**Files:**
- Create: `src/Notifications/DiscordMessageBuilder.php`
- Create: `src/Notifications/DiscordNotifier.php`
- Create: `tests/Unit/DiscordMessageBuilderTest.php`

**Step 1: Write the test for DiscordMessageBuilder**

```php
<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Results\CheckResult;
use PHPUnit\Framework\TestCase;

class DiscordMessageBuilderTest extends TestCase
{
    public function test_it_builds_critical_alert_embed(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $payload = $builder->buildAlert('Disk Space', new CheckResult(
            Status::Critical,
            'Disk usage at 94.2% (2.1GB free of 40GB)',
            ['percent_used' => 94.2],
        ));

        $this->assertArrayHasKey('embeds', $payload);
        $embed = $payload['embeds'][0];
        $this->assertSame(0xFF0000, $embed['color']);
        $this->assertStringContainsString('Client Portal', $embed['title']);
        $this->assertStringContainsString('CRITICAL', $embed['title']);
        $this->assertStringContainsString('Disk Space', $embed['title']);
    }

    public function test_it_builds_warning_alert_embed(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $payload = $builder->buildAlert('Memory', new CheckResult(
            Status::Warning,
            'Memory at 82%',
        ));

        $embed = $payload['embeds'][0];
        $this->assertSame(0xFFA500, $embed['color']);
        $this->assertStringContainsString('WARNING', $embed['title']);
    }

    public function test_it_builds_daily_summary(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $results = [
            'Disk Space' => new CheckResult(Status::Ok, '62.3% used'),
            'Composer Audit' => new CheckResult(Status::Critical, '2 vulnerabilities'),
        ];

        $payload = $builder->buildSummary('Daily Health Summary', $results);

        $embed = $payload['embeds'][0];
        $this->assertStringContainsString('Daily Health Summary', $embed['title']);
        $this->assertCount(2, $embed['fields']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/DiscordMessageBuilderTest.php -v`
Expected: FAIL.

**Step 3: Implement DiscordMessageBuilder**

```php
<?php

namespace Brigada\Guardian\Notifications;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class DiscordMessageBuilder
{
    private const COLORS = [
        'critical' => 0xFF0000,
        'warning' => 0xFFA500,
        'ok' => 0x00FF00,
        'error' => 0xFF0000,
    ];

    private const STATUS_ICONS = [
        'ok' => "\xF0\x9F\x9F\xA2",       // green circle
        'warning' => "\xF0\x9F\x9F\xA0",   // orange circle
        'critical' => "\xF0\x9F\x94\xB4",  // red circle
        'error' => "\xF0\x9F\x94\xB4",     // red circle
    ];

    public function __construct(
        private readonly string $projectName,
        private readonly string $environment,
    ) {}

    public function buildAlert(string $checkName, CheckResult $result): array
    {
        $statusLabel = strtoupper($result->status->value);
        $color = self::COLORS[$result->status->value] ?? self::COLORS['error'];

        return [
            'embeds' => [
                [
                    'title' => "[{$this->projectName}] {$statusLabel} — {$checkName}",
                    'description' => $result->message,
                    'color' => $color,
                    'fields' => [
                        ['name' => 'Environment', 'value' => $this->environment, 'inline' => true],
                        ['name' => 'Server', 'value' => gethostname() ?: 'unknown', 'inline' => true],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /** @param array<string, CheckResult> $results */
    public function buildSummary(string $title, array $results): array
    {
        $fields = [];
        $worstStatus = Status::Ok;

        foreach ($results as $checkName => $result) {
            $icon = self::STATUS_ICONS[$result->status->value] ?? self::STATUS_ICONS['error'];
            $fields[] = [
                'name' => "{$icon} {$checkName}",
                'value' => $result->message,
                'inline' => false,
            ];

            if ($this->severityRank($result->status) > $this->severityRank($worstStatus)) {
                $worstStatus = $result->status;
            }
        }

        $color = self::COLORS[$worstStatus->value] ?? self::COLORS['ok'];

        $issueCount = count(array_filter($results, fn (CheckResult $r) => ! $r->isOk()));
        $footer = $issueCount > 0
            ? "{$issueCount} issue(s) need attention"
            : 'All systems operational';

        return [
            'embeds' => [
                [
                    'title' => "[{$this->projectName}] {$title}",
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => [
                        'text' => "{$footer} | {$this->environment} | " . (gethostname() ?: 'unknown'),
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    private function severityRank(Status $status): int
    {
        return match ($status) {
            Status::Ok => 0,
            Status::Warning => 1,
            Status::Critical => 2,
            Status::Error => 3,
        };
    }
}
```

**Step 4: Implement DiscordNotifier**

```php
<?php

namespace Brigada\Guardian\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {}

    public function send(array $payload): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Guardian: Discord webhook URL not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Guardian: Failed to send Discord notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
```

**Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/DiscordMessageBuilderTest.php -v`
Expected: 3 tests, 3 passed.

**Step 6: Commit**

```bash
git add src/Notifications/ tests/Unit/DiscordMessageBuilderTest.php
git commit -m "feat: add Discord notification builder and notifier"
```

---

### Task 5: Every-5-Minute Checks (FailedJobsSpike, StaleJobs, SchedulerHeartbeat)

**Files:**
- Create: `src/Checks/FailedJobsSpikeCheck.php`
- Create: `src/Checks/StaleJobsCheck.php`
- Create: `src/Checks/SchedulerHeartbeatCheck.php`
- Create: `tests/Unit/Checks/FailedJobsSpikeCheckTest.php`
- Create: `tests/Unit/Checks/StaleJobsCheckTest.php`
- Create: `tests/Unit/Checks/SchedulerHeartbeatCheckTest.php`

**Step 1: Write test for FailedJobsSpikeCheck**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Checks;

use Brigada\Guardian\Checks\FailedJobsSpikeCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;

class FailedJobsSpikeCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('guardian.thresholds.failed_jobs_spike', ['warning' => 5, 'critical' => 20]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    public function test_it_returns_ok_when_no_failed_jobs(): void
    {
        $check = new FailedJobsSpikeCheck();
        $result = $check->run();

        $this->assertSame(Status::Ok, $result->status);
    }

    public function test_it_returns_warning_when_threshold_exceeded(): void
    {
        for ($i = 0; $i < 6; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Error',
                'failed_at' => now()->subMinutes(10),
            ]);
        }

        $check = new FailedJobsSpikeCheck();
        $result = $check->run();

        $this->assertSame(Status::Warning, $result->status);
    }

    public function test_schedule_is_every_five_minutes(): void
    {
        $check = new FailedJobsSpikeCheck();
        $this->assertSame(Schedule::EveryFiveMinutes, $check->schedule());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Checks/FailedJobsSpikeCheckTest.php -v`
Expected: FAIL.

**Step 3: Implement FailedJobsSpikeCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\DB;

class FailedJobsSpikeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Failed Jobs Spike';
    }

    public function schedule(): Schedule
    {
        return Schedule::EveryFiveMinutes;
    }

    public function run(): CheckResult
    {
        try {
            $count = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            $thresholds = config('guardian.thresholds.failed_jobs_spike');

            if ($count >= $thresholds['critical']) {
                return new CheckResult(Status::Critical, "{$count} failed jobs in the last hour", compact('count'));
            }

            if ($count >= $thresholds['warning']) {
                return new CheckResult(Status::Warning, "{$count} failed jobs in the last hour", compact('count'));
            }

            return new CheckResult(Status::Ok, "{$count} failed jobs in the last hour", compact('count'));
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Could not check failed jobs: {$e->getMessage()}");
        }
    }
}
```

**Step 4: Implement StaleJobsCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Redis;

class StaleJobsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Stale Jobs';
    }

    public function schedule(): Schedule
    {
        return Schedule::EveryFiveMinutes;
    }

    public function run(): CheckResult
    {
        try {
            $staleMinutes = config('guardian.thresholds.stale_job_minutes', 30);
            $queues = config('guardian.queues', ['default']);
            $staleQueues = [];

            foreach ($queues as $queue) {
                $size = Redis::llen("queues:{$queue}");
                if ($size > 0) {
                    $oldestJob = Redis::lindex("queues:{$queue}", -1);
                    if ($oldestJob) {
                        $job = json_decode($oldestJob, true);
                        $pushedAt = $job['pushedAt'] ?? null;
                        if ($pushedAt && (now()->timestamp - $pushedAt) > ($staleMinutes * 60)) {
                            $staleQueues[$queue] = $size;
                        }
                    }
                }
            }

            if (! empty($staleQueues)) {
                $details = collect($staleQueues)
                    ->map(fn ($size, $queue) => "{$queue}: {$size} jobs")
                    ->implode(', ');

                return new CheckResult(
                    Status::Warning,
                    "Stale jobs detected (>{$staleMinutes}m): {$details}",
                    ['stale_queues' => $staleQueues],
                );
            }

            return new CheckResult(Status::Ok, 'No stale jobs detected');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Could not check stale jobs: {$e->getMessage()}");
        }
    }
}
```

**Step 5: Implement SchedulerHeartbeatCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeatCheck implements HealthCheck
{
    public const CACHE_KEY = 'guardian:scheduler:heartbeat';

    public function name(): string
    {
        return 'Scheduler Heartbeat';
    }

    public function schedule(): Schedule
    {
        return Schedule::EveryFiveMinutes;
    }

    public function run(): CheckResult
    {
        $lastBeat = Cache::get(self::CACHE_KEY);

        // Record current heartbeat
        Cache::put(self::CACHE_KEY, now()->timestamp, now()->addMinutes(15));

        if ($lastBeat === null) {
            return new CheckResult(Status::Ok, 'First heartbeat recorded');
        }

        $minutesSinceLastBeat = (int) ((now()->timestamp - $lastBeat) / 60);

        if ($minutesSinceLastBeat > 10) {
            return new CheckResult(
                Status::Critical,
                "Scheduler may be down — last heartbeat {$minutesSinceLastBeat}m ago",
                ['minutes_since_last' => $minutesSinceLastBeat],
            );
        }

        return new CheckResult(
            Status::Ok,
            "Scheduler healthy — last heartbeat {$minutesSinceLastBeat}m ago",
            ['minutes_since_last' => $minutesSinceLastBeat],
        );
    }
}
```

**Step 6: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Checks/ -v`
Expected: All pass.

**Step 7: Commit**

```bash
git add src/Checks/FailedJobsSpikeCheck.php src/Checks/StaleJobsCheck.php src/Checks/SchedulerHeartbeatCheck.php tests/Unit/Checks/
git commit -m "feat: add every-5-minute checks (failed jobs spike, stale jobs, scheduler heartbeat)"
```

---

### Task 6: Hourly Checks (Disk, Memory, Database, Redis, LogErrors, QueueSize, Horizon, Storage)

**Files:**
- Create: `src/Checks/DiskSpaceCheck.php`
- Create: `src/Checks/MemoryCheck.php`
- Create: `src/Checks/DatabaseCheck.php`
- Create: `src/Checks/RedisCheck.php`
- Create: `src/Checks/LogErrorSpikeCheck.php`
- Create: `src/Checks/QueueSizeCheck.php`
- Create: `src/Checks/HorizonStatusCheck.php`
- Create: `src/Checks/StorageSizeCheck.php`
- Create: `tests/Unit/Checks/DiskSpaceCheckTest.php`
- Create: `tests/Unit/Checks/DatabaseCheckTest.php`

**Step 1: Implement DiskSpaceCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class DiskSpaceCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Disk Space';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $totalBytes = disk_total_space('/');
            $freeBytes = disk_free_space('/');

            if ($totalBytes === false || $freeBytes === false) {
                return new CheckResult(Status::Error, 'Could not read disk space');
            }

            $usedBytes = $totalBytes - $freeBytes;
            $percentUsed = round(($usedBytes / $totalBytes) * 100, 1);
            $freeGb = round($freeBytes / 1024 / 1024 / 1024, 1);
            $totalGb = round($totalBytes / 1024 / 1024 / 1024, 1);

            $thresholds = config('guardian.thresholds.disk_percent');

            $status = match (true) {
                $percentUsed >= $thresholds['critical'] => Status::Critical,
                $percentUsed >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult(
                $status,
                "{$percentUsed}% used ({$freeGb}GB free of {$totalGb}GB)",
                ['percent_used' => $percentUsed, 'free_gb' => $freeGb, 'total_gb' => $totalGb],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Disk check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 2: Implement MemoryCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class MemoryCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Memory';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return new CheckResult(Status::Ok, 'Memory check only available on Linux', ['available' => false]);
        }

        try {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatch);

            $totalKb = (int) ($totalMatch[1] ?? 0);
            $availableKb = (int) ($availableMatch[1] ?? 0);

            if ($totalKb === 0) {
                return new CheckResult(Status::Error, 'Could not parse memory info');
            }

            $usedKb = $totalKb - $availableKb;
            $percentUsed = round(($usedKb / $totalKb) * 100, 1);
            $totalMb = round($totalKb / 1024);
            $freeMb = round($availableKb / 1024);

            $thresholds = config('guardian.thresholds.memory_percent');

            $status = match (true) {
                $percentUsed >= $thresholds['critical'] => Status::Critical,
                $percentUsed >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult(
                $status,
                "{$percentUsed}% used ({$freeMb}MB free of {$totalMb}MB)",
                ['percent_used' => $percentUsed, 'free_mb' => $freeMb, 'total_mb' => $totalMb],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Memory check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 3: Implement DatabaseCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\DB;

class DatabaseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Database';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $pingMs = round((microtime(true) - $start) * 1000, 2);

            $thresholds = config('guardian.thresholds.db_response_ms');

            $status = match (true) {
                $pingMs >= $thresholds['critical'] => Status::Critical,
                $pingMs >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult($status, "Connected ({$pingMs}ms)", ['ping_ms' => $pingMs]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Critical, "Database unreachable: {$e->getMessage()}");
        }
    }
}
```

**Step 4: Implement RedisCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Redis;

class RedisCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Redis';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $pingMs = round((microtime(true) - $start) * 1000, 2);

            $info = Redis::info('memory');
            $memory = $info['used_memory_human'] ?? 'N/A';

            $thresholds = config('guardian.thresholds.redis_response_ms');

            $status = match (true) {
                $pingMs >= $thresholds['critical'] => Status::Critical,
                $pingMs >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult(
                $status,
                "Connected ({$pingMs}ms, {$memory})",
                ['ping_ms' => $pingMs, 'memory' => $memory],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Critical, "Redis unreachable: {$e->getMessage()}");
        }
    }
}
```

**Step 5: Implement LogErrorSpikeCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class LogErrorSpikeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Log Error Spike';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (! file_exists($logFile)) {
                return new CheckResult(Status::Ok, 'No log file found', ['count' => 0]);
            }

            $oneHourAgo = now()->subHour();
            $errorCount = 0;
            $criticalCount = 0;

            $handle = fopen($logFile, 'r');
            if (! $handle) {
                return new CheckResult(Status::Error, 'Could not open log file');
            }

            // Read from end of file for efficiency
            fseek($handle, max(0, filesize($logFile) - 1024 * 1024)); // Last 1MB

            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    $timestamp = strtotime($matches[1]);
                    if ($timestamp && $timestamp >= $oneHourAgo->timestamp) {
                        if (str_contains($line, '.ERROR:')) {
                            $errorCount++;
                        }
                        if (str_contains($line, '.CRITICAL:')) {
                            $criticalCount++;
                        }
                    }
                }
            }

            fclose($handle);

            $totalErrors = $errorCount + $criticalCount;
            $thresholds = config('guardian.thresholds.log_errors_per_hour');

            $status = match (true) {
                $totalErrors >= $thresholds['critical'] => Status::Critical,
                $totalErrors >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult(
                $status,
                "{$totalErrors} errors in the last hour ({$errorCount} ERROR, {$criticalCount} CRITICAL)",
                ['error_count' => $errorCount, 'critical_count' => $criticalCount],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Log check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 6: Implement QueueSizeCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Redis;

class QueueSizeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Queue Sizes';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $queues = config('guardian.queues', ['default']);
            $sizes = [];
            $totalSize = 0;

            foreach ($queues as $queue) {
                try {
                    $size = Redis::llen("queues:{$queue}");
                    $sizes[$queue] = $size;
                    $totalSize += $size;
                } catch (\Throwable $e) {
                    $sizes[$queue] = 'N/A';
                }
            }

            $thresholds = config('guardian.thresholds.queue_size');

            $status = match (true) {
                $totalSize >= $thresholds['critical'] => Status::Critical,
                $totalSize >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            $details = collect($sizes)
                ->map(fn ($size, $queue) => "{$queue}: {$size}")
                ->implode(' | ');

            return new CheckResult($status, $details, ['queues' => $sizes, 'total' => $totalSize]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Queue check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 7: Implement HorizonStatusCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class HorizonStatusCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Horizon';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return new CheckResult(Status::Ok, 'Horizon not installed', ['installed' => false]);
        }

        try {
            $status = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)
                ->all();

            if (empty($status)) {
                return new CheckResult(Status::Critical, 'Horizon is not running');
            }

            foreach ($status as $supervisor) {
                if ($supervisor->status === 'paused') {
                    return new CheckResult(Status::Warning, 'Horizon is paused');
                }
            }

            return new CheckResult(Status::Ok, 'Horizon is running');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Horizon check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 8: Implement StorageSizeCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class StorageSizeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Storage Size';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $paths = [
                'storage/app' => storage_path('app'),
                'storage/logs' => storage_path('logs'),
            ];

            $totalBytes = 0;
            $breakdown = [];

            foreach ($paths as $label => $path) {
                if (is_dir($path)) {
                    $size = $this->directorySize($path);
                    $breakdown[$label] = round($size / 1024 / 1024 / 1024, 2);
                    $totalBytes += $size;
                }
            }

            $totalGb = round($totalBytes / 1024 / 1024 / 1024, 2);
            $thresholds = config('guardian.thresholds.storage_size_gb');

            $status = match (true) {
                $totalGb >= $thresholds['critical'] => Status::Critical,
                $totalGb >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult(
                $status,
                "{$totalGb}GB total storage used",
                ['total_gb' => $totalGb, 'breakdown' => $breakdown],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Storage check failed: {$e->getMessage()}");
        }
    }

    private function directorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
```

**Step 9: Write test for DatabaseCheck**

```php
<?php

namespace Brigada\Guardian\Tests\Unit\Checks;

use Brigada\Guardian\Checks\DatabaseCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Orchestra\Testbench\TestCase;

class DatabaseCheckTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    public function test_it_reports_database_connected(): void
    {
        $check = new DatabaseCheck();
        $result = $check->run();

        $this->assertSame(Status::Ok, $result->status);
        $this->assertStringContainsString('Connected', $result->message);
        $this->assertArrayHasKey('ping_ms', $result->metadata);
    }

    public function test_schedule_is_hourly(): void
    {
        $check = new DatabaseCheck();
        $this->assertSame(Schedule::Hourly, $check->schedule());
    }
}
```

**Step 10: Run all tests**

Run: `./vendor/bin/phpunit tests/ -v`
Expected: All pass.

**Step 11: Commit**

```bash
git add src/Checks/ tests/Unit/Checks/
git commit -m "feat: add hourly checks (disk, memory, database, redis, log errors, queues, horizon, storage)"
```

---

### Task 7: Daily Checks (Composer Audit, NPM Audit, SSL, Env Safety, File Permissions, Migrations, PHP/OS Version, Config Cache, Insecure Packages, CSRF/CORS)

**Files:**
- Create: `src/Checks/ComposerAuditCheck.php`
- Create: `src/Checks/NpmAuditCheck.php`
- Create: `src/Checks/SslCertificateCheck.php`
- Create: `src/Checks/EnvSafetyCheck.php`
- Create: `src/Checks/FilePermissionsCheck.php`
- Create: `src/Checks/PendingMigrationsCheck.php`
- Create: `src/Checks/PhpVersionCheck.php`
- Create: `src/Checks/OsVersionCheck.php`
- Create: `src/Checks/ConfigCacheStalenessCheck.php`
- Create: `src/Checks/InsecurePackagesCheck.php`
- Create: `src/Checks/CsrfCorsCheck.php`

**Step 1: Implement ComposerAuditCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class ComposerAuditCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Composer Audit';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        try {
            $output = [];
            $exitCode = 0;
            exec('composer audit --format=json --no-interaction 2>/dev/null', $output, $exitCode);

            $json = json_decode(implode("\n", $output), true);

            if ($json === null) {
                return new CheckResult(Status::Error, 'Could not parse composer audit output');
            }

            $advisories = $json['advisories'] ?? [];
            $totalVulnerabilities = 0;
            $details = [];

            foreach ($advisories as $package => $issues) {
                foreach ($issues as $issue) {
                    $totalVulnerabilities++;
                    $cve = $issue['cve'] ?? 'N/A';
                    $title = $issue['title'] ?? 'Unknown';
                    $details[] = "{$package} ({$cve}) — {$title}";
                }
            }

            if ($totalVulnerabilities > 0) {
                $message = "{$totalVulnerabilities} vulnerabilities found\n" . implode("\n", $details);
                return new CheckResult(Status::Critical, $message, [
                    'count' => $totalVulnerabilities,
                    'details' => $details,
                ]);
            }

            return new CheckResult(Status::Ok, 'No vulnerabilities found');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Composer audit failed: {$e->getMessage()}");
        }
    }
}
```

**Step 2: Implement NpmAuditCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class NpmAuditCheck implements HealthCheck
{
    public function name(): string
    {
        return 'NPM Audit';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        try {
            if (! file_exists(base_path('package-lock.json'))) {
                return new CheckResult(Status::Ok, 'No package-lock.json found — skipping', ['skipped' => true]);
            }

            $output = [];
            $exitCode = 0;
            exec('cd ' . base_path() . ' && npm audit --json 2>/dev/null', $output, $exitCode);

            $json = json_decode(implode("\n", $output), true);

            if ($json === null) {
                return new CheckResult(Status::Error, 'Could not parse npm audit output');
            }

            $vulnerabilities = $json['metadata']['vulnerabilities'] ?? [];
            $total = ($vulnerabilities['high'] ?? 0) + ($vulnerabilities['critical'] ?? 0);
            $moderate = $vulnerabilities['moderate'] ?? 0;

            if ($total > 0) {
                return new CheckResult(
                    Status::Critical,
                    "{$total} high/critical vulnerabilities found",
                    ['vulnerabilities' => $vulnerabilities],
                );
            }

            if ($moderate > 0) {
                return new CheckResult(
                    Status::Warning,
                    "{$moderate} moderate vulnerabilities found",
                    ['vulnerabilities' => $vulnerabilities],
                );
            }

            return new CheckResult(Status::Ok, 'No vulnerabilities found');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "NPM audit failed: {$e->getMessage()}");
        }
    }
}
```

**Step 3: Implement SslCertificateCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class SslCertificateCheck implements HealthCheck
{
    public function name(): string
    {
        return 'SSL Certificate';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        try {
            $url = config('app.url');

            if (! $url || ! str_starts_with($url, 'https://')) {
                return new CheckResult(Status::Ok, 'No HTTPS URL configured — skipping', ['skipped' => true]);
            }

            $host = parse_url($url, PHP_URL_HOST);
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $client = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if (! $client) {
                return new CheckResult(Status::Critical, "Could not connect to {$host}: {$errstr}");
            }

            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            fclose($client);

            if (! $cert || ! isset($cert['validTo_time_t'])) {
                return new CheckResult(Status::Error, 'Could not parse SSL certificate');
            }

            $expiresAt = $cert['validTo_time_t'];
            $daysUntilExpiry = (int) (($expiresAt - time()) / 86400);

            $thresholds = config('guardian.thresholds.ssl_days_before_expiry');

            $status = match (true) {
                $daysUntilExpiry <= 0 => Status::Critical,
                $daysUntilExpiry <= $thresholds['critical'] => Status::Critical,
                $daysUntilExpiry <= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            $message = $daysUntilExpiry <= 0
                ? 'SSL certificate has EXPIRED'
                : "Expires in {$daysUntilExpiry} days";

            return new CheckResult($status, $message, [
                'days_until_expiry' => $daysUntilExpiry,
                'expires_at' => date('Y-m-d', $expiresAt),
                'host' => $host,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "SSL check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 4: Implement EnvSafetyCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class EnvSafetyCheck implements HealthCheck
{
    public function name(): string
    {
        return '.env Safety';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        $issues = [];

        if (config('app.debug') === true) {
            $issues[] = 'APP_DEBUG is enabled';
        }

        if (config('app.env') !== 'production') {
            $issues[] = 'APP_ENV is not "production" (is "' . config('app.env') . '")';
        }

        if (config('app.key') === '' || config('app.key') === null) {
            $issues[] = 'APP_KEY is not set';
        }

        if (config('session.driver') === 'array') {
            $issues[] = 'Session driver is "array" (non-persistent)';
        }

        if (config('logging.default') === 'stack' || config('logging.default') === 'single') {
            // This is fine
        }

        if (! empty($issues)) {
            return new CheckResult(
                Status::Critical,
                implode('; ', $issues),
                ['issues' => $issues],
            );
        }

        return new CheckResult(
            Status::Ok,
            'APP_DEBUG=false, APP_ENV=production',
        );
    }
}
```

**Step 5: Implement FilePermissionsCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class FilePermissionsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'File Permissions';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        $issues = [];

        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $perms = fileperms($envFile) & 0777;
            if ($perms & 0004) {
                $issues[] = '.env is world-readable (' . sprintf('%o', $perms) . ')';
            }
        }

        $storagePath = storage_path();
        if (is_dir($storagePath)) {
            $perms = fileperms($storagePath) & 0777;
            if ($perms & 0002) {
                $issues[] = 'storage/ is world-writable (' . sprintf('%o', $perms) . ')';
            }
        }

        if (! empty($issues)) {
            return new CheckResult(Status::Warning, implode('; ', $issues), ['issues' => $issues]);
        }

        return new CheckResult(Status::Ok, 'File permissions OK');
    }
}
```

**Step 6: Implement PendingMigrationsCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Artisan;

class PendingMigrationsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Pending Migrations';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        try {
            Artisan::call('migrate:status', ['--no-interaction' => true]);
            $output = Artisan::output();

            $pendingCount = substr_count($output, 'Pending');

            if ($pendingCount > 0) {
                return new CheckResult(
                    Status::Warning,
                    "{$pendingCount} pending migration(s)",
                    ['pending_count' => $pendingCount],
                );
            }

            return new CheckResult(Status::Ok, 'No pending migrations');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Migration check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 7: Implement PhpVersionCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class PhpVersionCheck implements HealthCheck
{
    // PHP EOL dates (major.minor => EOL date)
    private const EOL_DATES = [
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
    ];

    public function name(): string
    {
        return 'PHP Version';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $fullVersion = PHP_VERSION;
        $eolDate = self::EOL_DATES[$version] ?? null;

        if ($eolDate === null) {
            return new CheckResult(
                Status::Warning,
                "PHP {$fullVersion} — unknown EOL date",
                ['version' => $fullVersion],
            );
        }

        $daysUntilEol = (int) ((strtotime($eolDate) - time()) / 86400);

        if ($daysUntilEol <= 0) {
            return new CheckResult(
                Status::Critical,
                "PHP {$fullVersion} is END OF LIFE (EOL: {$eolDate})",
                ['version' => $fullVersion, 'eol_date' => $eolDate],
            );
        }

        if ($daysUntilEol <= 180) {
            return new CheckResult(
                Status::Warning,
                "PHP {$fullVersion} EOL in {$daysUntilEol} days ({$eolDate})",
                ['version' => $fullVersion, 'eol_date' => $eolDate, 'days_until_eol' => $daysUntilEol],
            );
        }

        return new CheckResult(
            Status::Ok,
            "PHP {$fullVersion} (supported until {$eolDate})",
            ['version' => $fullVersion, 'eol_date' => $eolDate, 'days_until_eol' => $daysUntilEol],
        );
    }
}
```

**Step 8: Implement OsVersionCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class OsVersionCheck implements HealthCheck
{
    // Ubuntu LTS EOL dates
    private const UBUNTU_EOL = [
        '20.04' => '2025-04-30',
        '22.04' => '2027-04-30',
        '24.04' => '2029-04-30',
    ];

    public function name(): string
    {
        return 'OS Version';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return new CheckResult(Status::Ok, 'OS check only available on Linux', ['available' => false]);
        }

        try {
            $releaseFile = '/etc/os-release';
            if (! file_exists($releaseFile)) {
                return new CheckResult(Status::Ok, 'Could not determine OS version');
            }

            $content = file_get_contents($releaseFile);
            preg_match('/^NAME="?(.+?)"?$/m', $content, $nameMatch);
            preg_match('/^VERSION_ID="?(.+?)"?$/m', $content, $versionMatch);

            $name = $nameMatch[1] ?? 'Unknown';
            $version = $versionMatch[1] ?? 'Unknown';

            $eolDate = self::UBUNTU_EOL[$version] ?? null;

            if ($eolDate) {
                $daysUntilEol = (int) ((strtotime($eolDate) - time()) / 86400);

                if ($daysUntilEol <= 0) {
                    return new CheckResult(
                        Status::Critical,
                        "{$name} {$version} is END OF LIFE",
                        ['os' => $name, 'version' => $version, 'eol_date' => $eolDate],
                    );
                }

                if ($daysUntilEol <= 365) {
                    return new CheckResult(
                        Status::Warning,
                        "{$name} {$version} EOL in {$daysUntilEol} days ({$eolDate})",
                        ['os' => $name, 'version' => $version, 'eol_date' => $eolDate],
                    );
                }

                return new CheckResult(
                    Status::Ok,
                    "{$name} {$version} (supported until {$eolDate})",
                    ['os' => $name, 'version' => $version, 'eol_date' => $eolDate],
                );
            }

            return new CheckResult(
                Status::Ok,
                "{$name} {$version}",
                ['os' => $name, 'version' => $version],
            );
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "OS check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 9: Implement ConfigCacheStalenessCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class ConfigCacheStalenessCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Config Cache';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        $cacheFile = base_path('bootstrap/cache/config.php');

        if (! file_exists($cacheFile)) {
            return new CheckResult(Status::Warning, 'Config is not cached — run php artisan config:cache');
        }

        $cacheTime = filemtime($cacheFile);
        $configDir = config_path();

        $newestConfig = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($configDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $newestConfig = max($newestConfig, $file->getMTime());
            }
        }

        // Also check .env
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $newestConfig = max($newestConfig, filemtime($envFile));
        }

        if ($newestConfig > $cacheTime) {
            return new CheckResult(
                Status::Warning,
                'Config cache is stale — config files changed since last cache',
                ['cache_time' => date('Y-m-d H:i:s', $cacheTime), 'newest_config' => date('Y-m-d H:i:s', $newestConfig)],
            );
        }

        return new CheckResult(Status::Ok, 'Config cache is fresh');
    }
}
```

**Step 10: Implement InsecurePackagesCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class InsecurePackagesCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Insecure Packages';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        try {
            $lockFile = base_path('composer.lock');

            if (! file_exists($lockFile)) {
                return new CheckResult(Status::Error, 'No composer.lock found');
            }

            $lock = json_decode(file_get_contents($lockFile), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

            $outdated = [];

            foreach ($packages as $package) {
                $name = $package['name'];
                $version = $package['version'] ?? 'unknown';

                // Flag abandoned packages
                if (! empty($package['abandoned'])) {
                    $replacement = is_string($package['abandoned']) ? " (use {$package['abandoned']})" : '';
                    $outdated[] = "{$name} {$version} is ABANDONED{$replacement}";
                }
            }

            if (! empty($outdated)) {
                return new CheckResult(
                    Status::Warning,
                    count($outdated) . " package issue(s) found:\n" . implode("\n", $outdated),
                    ['issues' => $outdated],
                );
            }

            return new CheckResult(Status::Ok, 'No known insecure or abandoned packages');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Package check failed: {$e->getMessage()}");
        }
    }
}
```

**Step 11: Implement CsrfCorsCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class CsrfCorsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'CSRF/CORS';
    }

    public function schedule(): Schedule
    {
        return Schedule::Daily;
    }

    public function run(): CheckResult
    {
        $issues = [];

        // Check CORS config
        $corsConfig = config('cors');
        if ($corsConfig) {
            $allowedOrigins = $corsConfig['allowed_origins'] ?? [];
            if (in_array('*', $allowedOrigins)) {
                $issues[] = 'CORS allows all origins (wildcard *)';
            }

            $allowedMethods = $corsConfig['allowed_methods'] ?? [];
            if (in_array('*', $allowedMethods)) {
                $issues[] = 'CORS allows all HTTP methods (wildcard *)';
            }
        }

        // Check if VerifyCsrfToken middleware has broad exclusions
        try {
            $csrfMiddleware = app(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
            $reflection = new \ReflectionClass($csrfMiddleware);

            if ($reflection->hasProperty('except')) {
                $prop = $reflection->getProperty('except');
                $prop->setAccessible(true);
                $except = $prop->getValue($csrfMiddleware);

                if (in_array('*', $except)) {
                    $issues[] = 'CSRF protection is disabled for all routes';
                } elseif (count($except) > 10) {
                    $issues[] = 'CSRF protection excluded for ' . count($except) . ' routes';
                }
            }
        } catch (\Throwable $e) {
            // Middleware may not be resolvable in all contexts
        }

        if (! empty($issues)) {
            return new CheckResult(Status::Warning, implode('; ', $issues), ['issues' => $issues]);
        }

        return new CheckResult(Status::Ok, 'CSRF/CORS configuration looks secure');
    }
}
```

**Step 12: Commit**

```bash
git add src/Checks/
git commit -m "feat: add daily checks (composer/npm audit, SSL, env safety, permissions, migrations, PHP/OS version, config cache, insecure packages, CSRF/CORS)"
```

---

### Task 8: Weekly Full Report Check

**Files:**
- Create: `src/Checks/FullReportCheck.php`

**Step 1: Implement FullReportCheck**

```php
<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;

class FullReportCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Weekly Full Report';
    }

    public function schedule(): Schedule
    {
        return Schedule::Weekly;
    }

    public function run(): CheckResult
    {
        $thisWeek = GuardianResult::where('created_at', '>=', now()->subWeek())->get();
        $lastWeek = GuardianResult::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->get();

        $thisWeekFailed = $thisWeek->where('status', 'critical')->count();
        $lastWeekFailed = $lastWeek->where('status', 'critical')->count();

        $thisWeekWarnings = $thisWeek->where('status', 'warning')->count();
        $lastWeekWarnings = $lastWeek->where('status', 'warning')->count();

        $trends = [];
        $trends[] = "Critical alerts: {$thisWeekFailed} this week vs {$lastWeekFailed} last week";
        $trends[] = "Warnings: {$thisWeekWarnings} this week vs {$lastWeekWarnings} last week";

        // Get latest result per check for current status
        $latestPerCheck = $thisWeek->groupBy('check_class')->map(fn ($results) => $results->sortByDesc('created_at')->first());

        $overallStatus = Status::Ok;
        if ($latestPerCheck->contains(fn ($r) => $r->status === 'critical')) {
            $overallStatus = Status::Critical;
        } elseif ($latestPerCheck->contains(fn ($r) => $r->status === 'warning')) {
            $overallStatus = Status::Warning;
        }

        return new CheckResult(
            $overallStatus,
            implode("\n", $trends),
            [
                'this_week_critical' => $thisWeekFailed,
                'last_week_critical' => $lastWeekFailed,
                'this_week_warnings' => $thisWeekWarnings,
                'last_week_warnings' => $lastWeekWarnings,
            ],
        );
    }
}
```

**Step 2: Commit**

```bash
git add src/Checks/FullReportCheck.php
git commit -m "feat: add weekly full report check with trend data"
```

---

### Task 9: Artisan Commands (RunChecks, Status)

**Files:**
- Create: `src/Commands/RunChecksCommand.php`
- Create: `src/Commands/StatusCommand.php`
- Modify: `src/GuardianServiceProvider.php`

**Step 1: Implement RunChecksCommand**

```php
<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Notifications\DiscordNotifier;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Support\CheckRegistry;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Console\Command;

class RunChecksCommand extends Command
{
    protected $signature = 'guardian:run
        {schedule? : Schedule group to run (every_5_min, hourly, daily, weekly)}
        {--check= : Run a single check by class name}';

    protected $description = 'Run Guardian health checks and send notifications';

    public function handle(CheckRegistry $registry, Deduplicator $deduplicator): int
    {
        $environment = config('guardian.environment', config('app.env'));

        if (! in_array($environment, config('guardian.enabled_environments', ['production']))) {
            $this->warn("Guardian is not enabled for environment: {$environment}");
            return 0;
        }

        $webhookUrl = config('guardian.discord_webhook_url');
        $projectName = config('guardian.project_name', config('app.name'));

        $notifier = new DiscordNotifier($webhookUrl ?? '');
        $messageBuilder = new DiscordMessageBuilder($projectName, $environment);

        // Single check mode
        if ($checkClass = $this->option('check')) {
            $check = $this->findCheck($registry, $checkClass);
            if (! $check) {
                $this->error("Check not found: {$checkClass}");
                return 1;
            }

            $result = $check->run();
            $this->outputResult($check->name(), $result);
            $this->handleNotification($check, $result, $deduplicator, $notifier, $messageBuilder);
            return 0;
        }

        // Schedule group mode
        $scheduleName = $this->argument('schedule');
        if (! $scheduleName) {
            $this->error('Please specify a schedule: every_5_min, hourly, daily, weekly');
            return 1;
        }

        $schedule = Schedule::tryFrom($scheduleName);
        if (! $schedule) {
            $this->error("Invalid schedule: {$scheduleName}");
            return 1;
        }

        $checks = $registry->forSchedule($schedule);
        $this->info("Running {$scheduleName} checks (" . count($checks) . " checks)...");

        $results = [];
        foreach ($checks as $check) {
            if ($this->isDisabled($check)) {
                $this->line("  [SKIP] {$check->name()}");
                continue;
            }

            $result = $check->run();
            $results[$check->name()] = $result;
            $this->outputResult($check->name(), $result);
            $this->handleNotification($check, $result, $deduplicator, $notifier, $messageBuilder);
        }

        // Send summary for daily/weekly
        if (in_array($schedule, [Schedule::Daily, Schedule::Weekly])) {
            $title = $schedule === Schedule::Weekly ? 'Weekly Full Report' : 'Daily Health Summary';
            $payload = $messageBuilder->buildSummary($title, $results);
            $notifier->send($payload);
            $this->info("Summary sent to Discord.");
        }

        // Prune old results
        $pruned = $deduplicator->prune();
        if ($pruned > 0) {
            $this->line("Pruned {$pruned} old results.");
        }

        return 0;
    }

    private function findCheck(CheckRegistry $registry, string $className): ?HealthCheck
    {
        foreach ($registry->all() as $check) {
            $shortName = class_basename($check);
            if ($shortName === $className || $check::class === $className) {
                return $check;
            }
        }

        return null;
    }

    private function isDisabled(HealthCheck $check): bool
    {
        return in_array($check::class, config('guardian.disabled_checks', []));
    }

    private function outputResult(string $name, CheckResult $result): void
    {
        $icon = match ($result->status->value) {
            'ok' => '<info>[OK]</info>',
            'warning' => '<comment>[WARN]</comment>',
            'critical' => '<error>[CRIT]</error>',
            'error' => '<error>[ERR]</error>',
        };

        $this->line("  {$icon} {$name} — {$result->message}");
    }

    private function handleNotification(
        HealthCheck $check,
        CheckResult $result,
        Deduplicator $deduplicator,
        DiscordNotifier $notifier,
        DiscordMessageBuilder $messageBuilder,
    ): void {
        $shouldNotify = $deduplicator->shouldNotify(class_basename($check), $result);

        if ($shouldNotify) {
            $payload = $messageBuilder->buildAlert($check->name(), $result);
            $notifier->send($payload);
        }

        $deduplicator->record(class_basename($check), $result, $shouldNotify);
    }
}
```

**Step 2: Implement StatusCommand**

```php
<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Models\GuardianResult;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'guardian:status';
    protected $description = 'Show the latest Guardian check results locally';

    public function handle(): int
    {
        $latestResults = GuardianResult::query()
            ->selectRaw('check_class, status, message, MAX(created_at) as last_run')
            ->groupBy('check_class', 'status', 'message')
            ->orderBy('last_run', 'desc')
            ->get();

        if ($latestResults->isEmpty()) {
            $this->warn('No check results found. Run guardian:run first.');
            return 0;
        }

        $rows = $latestResults->map(fn ($r) => [
            $r->check_class,
            strtoupper($r->status),
            \Illuminate\Support\Str::limit($r->message, 60),
            $r->last_run,
        ])->toArray();

        $this->table(['Check', 'Status', 'Message', 'Last Run'], $rows);

        return 0;
    }
}
```

**Step 3: Update GuardianServiceProvider to register commands, checks, and schedule**

```php
<?php

namespace Brigada\Guardian;

use Brigada\Guardian\Checks;
use Brigada\Guardian\Commands\RunChecksCommand;
use Brigada\Guardian\Commands\StatusCommand;
use Brigada\Guardian\Support\CheckRegistry;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/guardian.php', 'guardian');

        $this->app->singleton(CheckRegistry::class, function () {
            $registry = new CheckRegistry();

            $checks = [
                // Every 5 min
                Checks\FailedJobsSpikeCheck::class,
                Checks\StaleJobsCheck::class,
                Checks\SchedulerHeartbeatCheck::class,
                // Hourly
                Checks\DiskSpaceCheck::class,
                Checks\MemoryCheck::class,
                Checks\DatabaseCheck::class,
                Checks\RedisCheck::class,
                Checks\LogErrorSpikeCheck::class,
                Checks\QueueSizeCheck::class,
                Checks\HorizonStatusCheck::class,
                Checks\StorageSizeCheck::class,
                // Daily
                Checks\ComposerAuditCheck::class,
                Checks\NpmAuditCheck::class,
                Checks\SslCertificateCheck::class,
                Checks\EnvSafetyCheck::class,
                Checks\FilePermissionsCheck::class,
                Checks\PendingMigrationsCheck::class,
                Checks\PhpVersionCheck::class,
                Checks\OsVersionCheck::class,
                Checks\ConfigCacheStalenessCheck::class,
                Checks\InsecurePackagesCheck::class,
                Checks\CsrfCorsCheck::class,
                // Weekly
                Checks\FullReportCheck::class,
            ];

            foreach ($checks as $checkClass) {
                $registry->register(new $checkClass());
            }

            return $registry;
        });

        $this->app->singleton(Deduplicator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunChecksCommand::class,
                StatusCommand::class,
            ]);
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('guardian:run every_5_min')->everyFiveMinutes();
            $schedule->command('guardian:run hourly')->hourly();
            $schedule->command('guardian:run daily')->dailyAt(config('guardian.notifications.daily_summary_time', '06:00'));
            $schedule->command('guardian:run weekly')->weeklyOn(1, '07:00');
        });
    }
}
```

**Step 4: Run full test suite**

Run: `./vendor/bin/phpunit tests/ -v`
Expected: All pass.

**Step 5: Commit**

```bash
git add src/Commands/ src/GuardianServiceProvider.php
git commit -m "feat: add guardian:run and guardian:status commands, wire up service provider with all checks and scheduler"
```

---

### Task 10: PHPUnit Configuration and Final Tests

**Files:**
- Create: `phpunit.xml`
- Create: `tests/TestCase.php`
- Create: `tests/Feature/RunChecksCommandTest.php`

**Step 1: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         testdox="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**Step 2: Create base TestCase**

```php
<?php

namespace Brigada\Guardian\Tests;

use Brigada\Guardian\GuardianServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [GuardianServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('guardian.discord_webhook_url', 'https://discord.com/api/webhooks/test/test');
        $app['config']->set('guardian.project_name', 'Test Project');
        $app['config']->set('guardian.enabled_environments', ['testing']);
        $app['config']->set('guardian.environment', 'testing');
    }
}
```

**Step 3: Write feature test for RunChecksCommand**

```php
<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RunChecksCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_runs_hourly_checks(): void
    {
        $this->artisan('guardian:run', ['schedule' => 'hourly'])
            ->assertSuccessful();
    }

    public function test_it_rejects_invalid_schedule(): void
    {
        $this->artisan('guardian:run', ['schedule' => 'invalid'])
            ->assertFailed();
    }

    public function test_it_requires_schedule_argument(): void
    {
        $this->artisan('guardian:run')
            ->assertFailed();
    }

    public function test_status_command_works_with_no_results(): void
    {
        $this->artisan('guardian:status')
            ->assertSuccessful();
    }
}
```

**Step 4: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass.

**Step 5: Commit**

```bash
git add phpunit.xml tests/
git commit -m "feat: add PHPUnit configuration and feature tests for commands"
```

---

### Task 11: README and Package Polish

**Files:**
- Create: `README.md`
- Create: `.gitignore`

**Step 1: Create .gitignore**

```
/vendor/
/node_modules/
.phpunit.result.cache
.phpunit.cache/
composer.lock
```

**Step 2: Create README.md**

```markdown
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
```

**Step 3: Commit**

```bash
git add .gitignore README.md
git commit -m "docs: add README and .gitignore"
```

---

Plan complete and saved to `docs/plans/2026-03-19-guardian-implementation.md`. Two execution options:

**1. Subagent-Driven (this session)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Parallel Session (separate)** — Open a new session with executing-plans, batch execution with checkpoints.

Which approach?
