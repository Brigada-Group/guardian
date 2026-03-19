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

            return new CheckResult($status, "{$percentUsed}% used ({$freeMb}MB free of {$totalMb}MB)", [
                'percent_used' => $percentUsed,
                'free_mb' => $freeMb,
                'total_mb' => $totalMb,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Memory check failed: {$e->getMessage()}");
        }
    }
}
