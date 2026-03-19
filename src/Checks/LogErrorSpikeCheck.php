<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class LogErrorSpikeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Log Error Spike';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (! file_exists($logFile)) {
                return new CheckResult(Status::Ok, 'No log file found', ['count' => 0]);
            }

            $oneHourAgo = now()->subHour();
            $errorCount = 0;
            $criticalCount = 0;

            $handle = fopen($logFile, 'r');

            if (! $handle) {
                return new CheckResult(Status::Error, 'Could not open log file');
            }

            fseek($handle, max(0, filesize($logFile) - 1024 * 1024));

            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    $timestamp = strtotime($matches[1]);

                    if ($timestamp && $timestamp >= $oneHourAgo->timestamp) {
                        if (str_contains($line, '.ERROR:')) {
                            $errorCount++;
                        }
                        if (str_contains($line, '.CRITICAL:')) {
                            $criticalCount++;
                        }
                    }
                }
            }

            fclose($handle);

            $totalErrors = $errorCount + $criticalCount;
            $thresholds = config('guardian.thresholds.log_errors_per_hour');

            $status = match (true) {
                $totalErrors >= $thresholds['critical'] => Status::Critical,
                $totalErrors >= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };

            return new CheckResult($status, "{$totalErrors} errors in the last hour ({$errorCount} ERROR, {$criticalCount} CRITICAL)", [
                'error_count' => $errorCount,
                'critical_count' => $criticalCount,
            ]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Log check failed: {$e->getMessage()}");
        }
    }
}
