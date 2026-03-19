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

            return new CheckResult($status, "Connected ({$pingMs}ms, {$memory})", [
                'ping_ms' => $pingMs,
                'memory' => $memory,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Critical, "Redis unreachable: {$e->getMessage()}");
        }
    }
}
