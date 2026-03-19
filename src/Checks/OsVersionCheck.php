<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class OsVersionCheck implements HealthCheck
{
    private const UBUNTU_EOL = [
        '20.04' => '2025-04-30',
        '22.04' => '2027-04-30',
        '24.04' => '2029-04-30',
    ];

    public function name(): string { return 'OS Version'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return new CheckResult(Status::Ok, 'OS check only available on Linux', ['available' => false]);
        }
        try {
            $releaseFile = '/etc/os-release';
            if (! file_exists($releaseFile)) {
                return new CheckResult(Status::Ok, 'Could not determine OS version');
            }
            $content = file_get_contents($releaseFile);
            preg_match('/^NAME="?(.+?)"?$/m', $content, $nameMatch);
            preg_match('/^VERSION_ID="?(.+?)"?$/m', $content, $versionMatch);
            $name = $nameMatch[1] ?? 'Unknown';
            $version = $versionMatch[1] ?? 'Unknown';
            $eolDate = self::UBUNTU_EOL[$version] ?? null;
            if ($eolDate) {
                $daysUntilEol = (int) ((strtotime($eolDate) - time()) / 86400);
                if ($daysUntilEol <= 0) {
                    return new CheckResult(Status::Critical, "{$name} {$version} is END OF LIFE", ['os' => $name, 'version' => $version, 'eol_date' => $eolDate]);
                }
                if ($daysUntilEol <= 365) {
                    return new CheckResult(Status::Warning, "{$name} {$version} EOL in {$daysUntilEol} days ({$eolDate})", ['os' => $name, 'version' => $version, 'eol_date' => $eolDate]);
                }
                return new CheckResult(Status::Ok, "{$name} {$version} (supported until {$eolDate})", ['os' => $name, 'version' => $version, 'eol_date' => $eolDate]);
            }
            return new CheckResult(Status::Ok, "{$name} {$version}", ['os' => $name, 'version' => $version]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "OS check failed: {$e->getMessage()}");
        }
    }
}
