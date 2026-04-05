<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use Illuminate\Support\Facades\Process;

class NpmAuditCheck implements HealthCheck
{
    public function name(): string { return 'NPM Audit'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        try {
            if (! file_exists(base_path('package-lock.json'))) {
                return new CheckResult(Status::Ok, 'No package-lock.json found — skipping', ['skipped' => true]);
            }
            $result = Process::path(base_path())->run(['npm', 'audit', '--json']);
            $json = json_decode($result->output(), true);
            if ($json === null) {
                return new CheckResult(Status::Error, 'Could not parse npm audit output');
            }
            $vulnerabilities = $json['metadata']['vulnerabilities'] ?? [];
            $total = ($vulnerabilities['high'] ?? 0) + ($vulnerabilities['critical'] ?? 0);
            $moderate = $vulnerabilities['moderate'] ?? 0;
            if ($total > 0) {
                return new CheckResult(Status::Critical, "{$total} high/critical vulnerabilities found", ['vulnerabilities' => $vulnerabilities]);
            }
            if ($moderate > 0) {
                return new CheckResult(Status::Warning, "{$moderate} moderate vulnerabilities found", ['vulnerabilities' => $vulnerabilities]);
            }
            return new CheckResult(Status::Ok, 'No vulnerabilities found');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "NPM audit failed: {$e->getMessage()}");
        }
    }
}
