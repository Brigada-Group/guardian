<?php

namespace Brigada\Guardian\Audits;

use Illuminate\Support\Facades\Process;

class ComposerAuditReporter
{
    public function build(): ?array
    {
        if (! file_exists(base_path('composer.json'))) {
            return null;
        }

        $result = Process::path(base_path())
            ->timeout(120)
            ->run(['composer', 'audit', '--format=json', '--no-interaction']);

        $json = json_decode($result->output(), true);

        if (! is_array($json)) {
            return null;
        }

        $advisories = is_array($json['advisories'] ?? null) ? $json['advisories'] : [];
        $abandoned = is_array($json['abandoned'] ?? null) ? $json['abandoned'] : [];

        $advisoriesCount = 0;
        foreach ($advisories as $issues) {
            if (is_array($issues)) {
                $advisoriesCount += count($issues);
            }
        }

        return [
            'advisories_count' => $advisoriesCount,
            'abandoned_count' => count($abandoned),
            'advisories' => $advisories,
            'abandoned' => $abandoned,
        ];
    }
}
