<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class PhpVersionCheck implements HealthCheck
{
    private const EOL_DATES = [
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
    ];

    public function name(): string { return 'PHP Version'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $fullVersion = PHP_VERSION;
        $eolDate = self::EOL_DATES[$version] ?? null;
        if ($eolDate === null) {
            return new CheckResult(Status::Warning, "PHP {$fullVersion} — unknown EOL date", ['version' => $fullVersion]);
        }
        $daysUntilEol = (int) ((strtotime($eolDate) - time()) / 86400);
        if ($daysUntilEol <= 0) {
            return new CheckResult(Status::Critical, "PHP {$fullVersion} is END OF LIFE (EOL: {$eolDate})", ['version' => $fullVersion, 'eol_date' => $eolDate]);
        }
        if ($daysUntilEol <= 180) {
            return new CheckResult(Status::Warning, "PHP {$fullVersion} EOL in {$daysUntilEol} days ({$eolDate})", ['version' => $fullVersion, 'eol_date' => $eolDate, 'days_until_eol' => $daysUntilEol]);
        }
        return new CheckResult(Status::Ok, "PHP {$fullVersion} (supported until {$eolDate})", ['version' => $fullVersion, 'eol_date' => $eolDate, 'days_until_eol' => $daysUntilEol]);
    }
}
