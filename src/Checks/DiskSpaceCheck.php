<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class DiskSpaceCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Disk Space';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $totalBytes = disk_total_space('/');
            $freeBytes = disk_free_space('/');

            if ($totalBytes === false || $freeBytes === false) {
                return new CheckResult(Status::Error, 'Could not read disk space');
            }

            $usedBytes = $totalBytes - $freeBytes;
            $percentUsed = round(($usedBytes / $totalBytes) * 100, 1);
            $freeGb = round($freeBytes / 1024 / 1024 / 1024, 1);
            $totalGb = round($totalBytes / 1024 / 1024 / 1024, 1);

            $thresholds = config('guardian.thresholds.disk_percent');

            $status = match (true) {
                $percentUsed >= $thresholds['critical'] => Status::Critical,
                $percentUsed >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult($status, "{$percentUsed}% used ({$freeGb}GB free of {$totalGb}GB)", [
                'percent_used' => $percentUsed,
                'free_gb' => $freeGb,
                'total_gb' => $totalGb,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Disk check failed: {$e->getMessage()}");
        }
    }
}
