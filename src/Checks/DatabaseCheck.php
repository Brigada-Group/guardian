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
