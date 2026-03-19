<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class FilePermissionsCheck implements HealthCheck
{
    public function name(): string { return 'File Permissions'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        $issues = [];
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $perms = fileperms($envFile) & 0777;
            if ($perms & 0004) { $issues[] = '.env is world-readable (' . sprintf('%o', $perms) . ')'; }
        }
        $storagePath = storage_path();
        if (is_dir($storagePath)) {
            $perms = fileperms($storagePath) & 0777;
            if ($perms & 0002) { $issues[] = 'storage/ is world-writable (' . sprintf('%o', $perms) . ')'; }
        }
        if (! empty($issues)) {
            return new CheckResult(Status::Warning, implode('; ', $issues), ['issues' => $issues]);
        }
        return new CheckResult(Status::Ok, 'File permissions OK');
    }
}
