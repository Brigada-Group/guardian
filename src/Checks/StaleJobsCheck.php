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
