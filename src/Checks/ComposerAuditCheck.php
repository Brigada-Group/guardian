<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class ComposerAuditCheck implements HealthCheck
{
    public function name(): string { return 'Composer Audit'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        try {
            $output = [];
            $exitCode = 0;
            exec('composer audit --format=json --no-interaction 2>/dev/null', $output, $exitCode);
            $json = json_decode(implode("\n", $output), true);
            if ($json === null) {
                return new CheckResult(Status::Error, 'Could not parse composer audit output');
            }
            $advisories = $json['advisories'] ?? [];
            $totalVulnerabilities = 0;
            $details = [];
            foreach ($advisories as $package => $issues) {
                foreach ($issues as $issue) {
                    $totalVulnerabilities++;
                    $cve = $issue['cve'] ?? 'N/A';
                    $title = $issue['title'] ?? 'Unknown';
                    $details[] = "{$package} ({$cve}) — {$title}";
                }
            }
            if ($totalVulnerabilities > 0) {
                $message = "{$totalVulnerabilities} vulnerabilities found\n" . implode("\n", $details);
                return new CheckResult(Status::Critical, $message, ['count' => $totalVulnerabilities, 'details' => $details]);
            }
            return new CheckResult(Status::Ok, 'No vulnerabilities found');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Composer audit failed: {$e->getMessage()}");
        }
    }
}
