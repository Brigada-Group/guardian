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

            $details = collect($sizes)->map(fn ($size, $queue) => "{$queue}: {$size}")->implode(' | ');

            return new CheckResult($status, $details, ['queues' => $sizes, 'total' => $totalSize]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Queue check failed: {$e->getMessage()}");
        }
    }
}
