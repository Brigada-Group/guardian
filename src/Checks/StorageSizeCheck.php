<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class StorageSizeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Storage Size';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $paths = [
                'storage/app' => storage_path('app'),
                'storage/logs' => storage_path('logs'),
            ];

            $totalBytes = 0;
            $breakdown = [];

            foreach ($paths as $label => $path) {
                if (is_dir($path)) {
                    $size = $this->directorySize($path);
                    $breakdown[$label] = round($size / 1024 / 1024 / 1024, 2);
                    $totalBytes += $size;
                }
            }

            $totalGb = round($totalBytes / 1024 / 1024 / 1024, 2);
            $thresholds = config('guardian.thresholds.storage_size_gb');

            $status = match (true) {
                $totalGb >= $thresholds['critical'] => Status::Critical,
                $totalGb >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult($status, "{$totalGb}GB total storage used", [
                'total_gb' => $totalGb,
                'breakdown' => $breakdown,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Storage check failed: {$e->getMessage()}");
        }
    }

    private function directorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
