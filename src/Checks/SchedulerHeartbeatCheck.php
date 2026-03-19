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
