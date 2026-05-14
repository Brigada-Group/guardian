<?php

namespace Brigada\Guardian;

use Brigada\Guardian\Checks;
use Brigada\Guardian\Commands\InstallCommand;
use Brigada\Guardian\Commands\PruneCommand;
use Brigada\Guardian\Commands\PurgeGuardianQueueJobsCommand;
use Brigada\Guardian\Commands\RunChecksCommand;
use Brigada\Guardian\Commands\SendAuditsCommand;
use Brigada\Guardian\Commands\SyncLogSnapshotsCommand;
use Brigada\Guardian\Commands\StatusCommand;
use Brigada\Guardian\Commands\VerifyCommand;
use Brigada\Guardian\Exceptions\ExceptionNotifier;
use Brigada\Guardian\Transport\HeartbeatSender;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Http\Middleware\InjectGuardianClient;
use Brigada\Guardian\Http\Middleware\RequestMonitor;
use Brigada\Guardian\Http\Middleware\StartTrace;
use Brigada\Guardian\Support\TraceContext;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Http;
use Brigada\Guardian\Dispatcher\SendsToNightwatch;
use Brigada\Guardian\Listeners\CacheListener;
use Brigada\Guardian\Listeners\CommandListener;
use Brigada\Guardian\Listeners\JobListener;
use Brigada\Guardian\Listeners\LogListener;
use Brigada\Guardian\Listeners\MailListener;
use Brigada\Guardian\Listeners\NotificationListener;
use Brigada\Guardian\Listeners\OutgoingHttpListener;
use Brigada\Guardian\Listeners\QueryListener;
use Brigada\Guardian\Listeners\ScheduledTaskListener;
use Brigada\Guardian\Support\CheckRegistry;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        $this->app->singleton(ExceptionNotifier::class);

        $this->app->singleton(NightwatchClient::class);

        $this->app->singleton(SendsToNightwatch::class);

        $this->app->singleton(CacheListener::class);


    }

    private function normalizeGuardianDispatchConfig(): void
    {
        $raw = strtolower(trim((string) config('guardian.dispatch_mode', 'worker')));

        if (! in_array($raw, ['worker', 'sync'], true)) {
            Log::warning('Guardian: invalid guardian.dispatch_mode "' . config('guardian.dispatch_mode') . "\" — expected worker or sync; using worker.", [
                'guardian_internal' => true,
            ]);
            $raw = 'worker';
            config(['guardian.dispatch_mode' => 'worker']);
        } else {
            config(['guardian.dispatch_mode' => $raw]);
        }
    }

    private function registerTracing(): void 
    {
        
        if (! config('guardian.tracing.enabled', true)) {
            return;
        }

        if (! $this->app->runningInConsole()) {
            $kernel = $this->app->make(HttpKernel::class);

            if (method_exists($kernel, 'prependMiddleware')) {
                $kernel->prependMiddleware(StartTrace::class);
            }
        }

        if (config('guardian.tracing.propagate_outbound', false)) {
            Http::globalRequestMiddleware(function ($request) {
                $traceId = TraceContext::current();

                if (! $traceId || $request->hasHeader('traceparent')) {
                    return $request;
                }

                $parentId = bin2hex(random_bytes(8));

                return $request->withHeader(
                    'traceparent',
                    "00-{$traceId}-{$parentId}-01"
                );
            });
        }

    }

    public function boot(): void
    {
        $this->normalizeGuardianDispatchConfig();

        $this->registerTracing();

        $this->publishes([
            __DIR__ . '/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->publishes([
            __DIR__ . '/../resources/js/guardian-client.js' => public_path('vendor/guardian/guardian-client.js'),
        ], 'guardian-assets');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'guardian');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (config('guardian.client_errors.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/guardian-client-errors.php');

            if (config('guardian.client_errors.auto_inject', true)) {
                // bootstrap/app.php's `withMiddleware` callback resolves the kernel
                // and calls setMiddlewareGroups() *before* providers boot, so by
                // the time we get here the groups are already in place. Append
                // directly — using afterResolving would register a callback that
                // never fires, because the kernel singleton is already cached and
                // no further make() will happen.
                $kernel = $this->app->make(HttpKernel::class);
                if (method_exists($kernel, 'appendMiddlewareToGroup')) {
                    $kernel->appendMiddlewareToGroup('web', InjectGuardianClient::class);
                }
            }
        }

        $this->registerRequestMonitoringMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunChecksCommand::class,
                StatusCommand::class,
                InstallCommand::class,
                PruneCommand::class,
                PurgeGuardianQueueJobsCommand::class,
                SendAuditsCommand::class,
                SyncLogSnapshotsCommand::class,
                VerifyCommand::class,
            ]);
        }

        $this->registerSchedule();
        $this->registerExceptionHandler();
        $this->registerEventListeners();
        $this->registerCacheAggregatorTerminatingFlush();
    }

    private function registerRequestMonitoringMiddleware(): void
    {
        if (! config('guardian.monitoring.requests.enabled', true)) {
            return;
        }

        if (! config('guardian.monitoring.requests.register_middleware', true)) {
            return;
        }

        $groups = config('guardian.monitoring.requests.middleware_groups', ['web']);
        if (! is_array($groups)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (! method_exists($kernel, 'appendMiddlewareToGroup')) {
            return;
        }

        foreach ($groups as $group) {
            if (! is_string($group) || $group === '') {
                continue;
            }

            $kernel->appendMiddlewareToGroup($group, RequestMonitor::class);
        }
    }

    private function registerSchedule(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $dailyTime = config('guardian.notifications.daily_summary_time', '06:00');
            $weeklyDay = $this->parseWeeklyDay(config('guardian.notifications.weekly_summary_day', 'monday'));

            $scheduledHeartbeat = config('guardian.hub.scheduled_heartbeat', []);
            if (is_array($scheduledHeartbeat)
                && filter_var($scheduledHeartbeat['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $minutes = isset($scheduledHeartbeat['interval_minutes'])
                    ? (int) $scheduledHeartbeat['interval_minutes']
                    : 5;
                $minutes = max(1, min(60, $minutes));
                $cron = $minutes === 1 ? '* * * * *' : "*/{$minutes} * * * *";

                $schedule->call(function () {
                    try {
                        app(HeartbeatSender::class)->sendNow(true);
                    } catch (\Throwable) {
                        // Scheduled heartbeats must not break Laravel's scheduler
                    }
                })->cron($cron)->name('guardian:nightwatch-hub-heartbeat');
            }
            
            $schedule->command('guardian:run every_5_min')->everyFiveMinutes();
            $schedule->command('guardian:run hourly')->hourly();
            $schedule->command('guardian:run daily')->dailyAt($dailyTime);
            $schedule->command('guardian:run weekly')->weeklyOn($weeklyDay, '07:00');
            $schedule->command('guardian:audits')->dailyAt(config('guardian.audits.time', '03:00'));

            if (config('guardian.log_file_snapshots.enabled', false)) {
                $schedule->command('guardian:sync-log-snapshots')
                    ->dailyAt(config('guardian.log_file_snapshots.schedule_at', '04:05'));
            }
        });
    }

    private function registerExceptionHandler(): void
    {
        $this->app->afterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    if (in_array(config('guardian.environment', 'production'), config('guardian.enabled_environments', ['production']))) {
                        $this->app->make(ExceptionNotifier::class)->handle($e);
                    }
                })->stop(false);
            }
        });
    }

    private function registerEventListeners(): void
    {
        // Task 10: Outgoing HTTP Monitoring
        if (config('guardian.monitoring.outgoing_http.enabled', true)) {
            Event::listen(ResponseReceived::class, [OutgoingHttpListener::class, 'handleResponse']);
            Event::listen(ConnectionFailed::class, [OutgoingHttpListener::class, 'handleConnectionFailed']);
        }

        // Task 11: Database Query Monitoring
        if (config('guardian.monitoring.queries.enabled', true)) {
            Event::listen(QueryExecuted::class, [QueryListener::class, 'handle']);
        }

        // Task 12: Mail Monitoring
        if (config('guardian.monitoring.mail.enabled', true)) {
            Event::listen(MessageSent::class, [MailListener::class, 'handleSent']);
        }

        // Task 13: Notification Monitoring
        if (config('guardian.monitoring.notifications.enabled', true)) {
            Event::listen(NotificationSent::class, [NotificationListener::class, 'handleSent']);
            Event::listen(NotificationFailed::class, [NotificationListener::class, 'handleFailed']);
        }

        // Task 14: Cache Monitoring
        if (config('guardian.monitoring.cache.enabled', true)) {
            Event::listen(CacheHit::class, [CacheListener::class, 'handleHit']);
            Event::listen(CacheMissed::class, [CacheListener::class, 'handleMiss']);
            Event::listen(KeyWritten::class, [CacheListener::class, 'handleWrite']);
            Event::listen(KeyForgotten::class, [CacheListener::class, 'handleForget']);
        }

        // Task 15: Command Monitoring
        if (config('guardian.monitoring.commands.enabled', true)) {
            Event::listen(CommandStarting::class, [CommandListener::class, 'handleStarting']);
            Event::listen(CommandFinished::class, [CommandListener::class, 'handleFinished']);
        }

        // Task 16: Scheduled Task Monitoring
        if (config('guardian.monitoring.scheduled_tasks.enabled', true)) {
            Event::listen(ScheduledTaskStarting::class, [ScheduledTaskListener::class, 'handleStarting']);
            Event::listen(ScheduledTaskFinished::class, [ScheduledTaskListener::class, 'handleFinished']);
            Event::listen(ScheduledTaskFailed::class, [ScheduledTaskListener::class, 'handleFailed']);
            Event::listen(ScheduledTaskSkipped::class, [ScheduledTaskListener::class, 'handleSkipped']);
        }

        // Job/Queue Monitoring
        if (config('guardian.monitoring.jobs.enabled', true)) {
            Event::listen(JobProcessing::class, [JobListener::class, 'handleProcessing']);
            Event::listen(JobProcessed::class, [JobListener::class, 'handleProcessed']);
            Event::listen(JobFailed::class, [JobListener::class, 'handleFailed']);
        }

        // Log Monitoring
        if (config('guardian.monitoring.logs.enabled', true)) {
            Event::listen(MessageLogged::class, [LogListener::class, 'handle']);
        }
    }

    
    private function registerCacheAggregatorTerminatingFlush(): void
    {
        if (! config('guardian.monitoring.cache.enabled', true)) {
            return;
        }

        $app = $this->app;

        $app->terminating(static function () use ($app): void {
            try {
                if (! $app->isBooted()) {
                    return;
                }

                $app->make(CacheListener::class)->flush();
            } catch (\Throwable) {
                // PHPUnit teardown, graceful shutdown with a partial container, etc.
            }
        });
    }


    private function parseWeeklyDay(string $day): int
    {
        return match (strtolower($day)) {
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            default => 1,
        };
    }
}
