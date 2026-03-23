<?php

namespace Brigada\Guardian\Notifications;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class DiscordMessageBuilder
{
    private const COLORS = [
        'critical' => 0xFF0000,
        'warning' => 0xFFA500,
        'ok' => 0x00FF00,
        'error' => 0xFF0000,
    ];

    private const STATUS_ICONS = [
        'ok' => "\xF0\x9F\x9F\xA2",
        'warning' => "\xF0\x9F\x9F\xA0",
        'critical' => "\xF0\x9F\x94\xB4",
        'error' => "\xF0\x9F\x94\xB4",
    ];

    public function __construct(
        private readonly string $projectName,
        private readonly string $environment,
    ) {}

    public function buildAlert(string $checkName, CheckResult $result): array
    {
        $statusLabel = strtoupper($result->status->value);
        $color = self::COLORS[$result->status->value] ?? self::COLORS['error'];

        return [
            'embeds' => [
                [
                    'title' => "[{$this->projectName}] {$statusLabel} — {$checkName}",
                    'description' => $result->message,
                    'color' => $color,
                    'fields' => [
                        ['name' => 'Environment', 'value' => $this->environment, 'inline' => true],
                        ['name' => 'Server', 'value' => gethostname() ?: 'unknown', 'inline' => true],
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];
    }

    /** @param array<string, CheckResult> $results */
    public function buildSummary(string $title, array $results): array
    {
        $fields = [];
        $worstStatus = Status::Ok;

        foreach ($results as $checkName => $result) {
            $icon = self::STATUS_ICONS[$result->status->value] ?? self::STATUS_ICONS['error'];
            $fields[] = [
                'name' => "{$icon} {$checkName}",
                'value' => $result->message,
                'inline' => false,
            ];

            if ($this->severityRank($result->status) > $this->severityRank($worstStatus)) {
                $worstStatus = $result->status;
            }
        }

        $color = self::COLORS[$worstStatus->value] ?? self::COLORS['ok'];

        $issueCount = count(array_filter($results, fn (CheckResult $r) => ! $r->isOk()));
        $footer = $issueCount > 0
            ? "{$issueCount} issue(s) need attention"
            : 'All systems operational';

        return [
            'embeds' => [
                [
                    'title' => "[{$this->projectName}] {$title}",
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => [
                        'text' => "{$footer} | {$this->environment} | " . (gethostname() ?: 'unknown'),
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];
    }

    public function buildException(
        string $exceptionClass,
        string $message,
        string $url,
        int $statusCode,
        string $user,
        string $ip,
        string $headers,
        string $stackTrace,
    ): array {
        $shortClass = class_basename($exceptionClass) ?: $exceptionClass;

        return [
            'embeds' => [
                [
                    'title' => "[{$this->projectName}] \xF0\x9F\x92\xA5 {$shortClass}",
                    'color' => self::COLORS['critical'],
                    'fields' => [
                        ['name' => 'Message', 'value' => mb_substr($message, 0, 1024) ?: 'No message', 'inline' => false],
                        ['name' => 'URL', 'value' => mb_substr($url, 0, 1024), 'inline' => true],
                        ['name' => 'Status Code', 'value' => (string) $statusCode, 'inline' => true],
                        ['name' => 'User', 'value' => $user ?: 'Guest', 'inline' => true],
                        ['name' => 'IP Address', 'value' => $ip ?: 'N/A', 'inline' => true],
                        ['name' => 'Headers', 'value' => mb_substr($headers, 0, 1024) ?: 'N/A', 'inline' => false],
                        ['name' => 'Stack Trace', 'value' => mb_substr($stackTrace, 0, 1024) ?: 'N/A', 'inline' => false],
                    ],
                    'footer' => [
                        'text' => "{$this->environment} | " . (gethostname() ?: 'unknown'),
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];
    }

    private function severityRank(Status $status): int
    {
        return match ($status) {
            Status::Ok => 0,
            Status::Warning => 1,
            Status::Critical => 2,
            Status::Error => 3,
        };
    }
}
