<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class InsecurePackagesCheck implements HealthCheck
{
    public function name(): string { return 'Insecure Packages'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        try {
            $lockFile = base_path('composer.lock');
            if (! file_exists($lockFile)) {
                return new CheckResult(Status::Error, 'No composer.lock found');
            }
            $lock = json_decode(file_get_contents($lockFile), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
            $outdated = [];
            foreach ($packages as $package) {
                $name = $package['name'];
                $version = $package['version'] ?? 'unknown';
                if (! empty($package['abandoned'])) {
                    $replacement = is_string($package['abandoned']) ? " (use {$package['abandoned']})" : '';
                    $outdated[] = "{$name} {$version} is ABANDONED{$replacement}";
                }
            }
            if (! empty($outdated)) {
                return new CheckResult(Status::Warning, count($outdated) . " package issue(s) found:\n" . implode("\n", $outdated), ['issues' => $outdated]);
            }
            return new CheckResult(Status::Ok, 'No known insecure or abandoned packages');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Package check failed: {$e->getMessage()}");
        }
    }
}
