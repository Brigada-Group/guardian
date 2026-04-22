<?php

namespace Brigada\Guardian\Audits;

use Illuminate\Support\Facades\Process;

class NpmAuditReporter
{
    public function build(): ?array
    {
        if (! file_exists(base_path('package-lock.json'))) {
            return null;
        }

        $result = Process::path(base_path())
            ->timeout(120)
            ->run(['npm', 'audit', '--json']);

        $json = json_decode($result->output(), true);

        if (! is_array($json)) {
            return null;
        }

        $vulnerabilities = is_array($json['vulnerabilities'] ?? null) ? $json['vulnerabilities'] : [];
        $metadata = is_array($json['metadata'] ?? null) ? $json['metadata'] : [];
        $severityCounts = is_array($metadata['vulnerabilities'] ?? null) ? $metadata['vulnerabilities'] : [];
        $dependencies = is_array($metadata['dependencies'] ?? null) ? $metadata['dependencies'] : [];

        $info = (int) ($severityCounts['info'] ?? 0);
        $low = (int) ($severityCounts['low'] ?? 0);
        $moderate = (int) ($severityCounts['moderate'] ?? 0);
        $high = (int) ($severityCounts['high'] ?? 0);
        $critical = (int) ($severityCounts['critical'] ?? 0);
        $total = (int) ($severityCounts['total'] ?? ($info + $low + $moderate + $high + $critical));

        return [
            'total_vulnerabilities' => $total,
            'info_count' => $info,
            'low_count' => $low,
            'moderate_count' => $moderate,
            'high_count' => $high,
            'critical_count' => $critical,
            'vulnerabilities' => $vulnerabilities,
            'audit_metadata' => [
                'dependencies' => (int) ($dependencies['prod'] ?? $metadata['dependencies'] ?? 0),
                'devDependencies' => (int) ($dependencies['dev'] ?? $metadata['devDependencies'] ?? 0),
                'totalDependencies' => (int) ($dependencies['total'] ?? $metadata['totalDependencies'] ?? 0),
            ],
        ];
    }
}
