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
