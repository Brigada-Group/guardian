<?php

namespace Brigada\Guardian;

use Brigada\Guardian\Checks;
use Brigada\Guardian\Commands\RunChecksCommand;
use Brigada\Guardian\Commands\StatusCommand;
use Brigada\Guardian\Support\CheckRegistry;
use Brigada\Guardian\Exceptions\ExceptionNotifier;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
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
}
