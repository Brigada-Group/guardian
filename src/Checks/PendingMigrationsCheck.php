<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Artisan;

class PendingMigrationsCheck implements HealthCheck
{
    public function name(): string { return 'Pending Migrations'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        try {
            Artisan::call('migrate:status', ['--no-interaction' => true]);
            $output = Artisan::output();
            $pendingCount = substr_count($output, 'Pending');
            if ($pendingCount > 0) {
                return new CheckResult(Status::Warning, "{$pendingCount} pending migration(s)", ['pending_count' => $pendingCount]);
            }
            return new CheckResult(Status::Ok, 'No pending migrations');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Migration check failed: {$e->getMessage()}");
        }
    }
}
