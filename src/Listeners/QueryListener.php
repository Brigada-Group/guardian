<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\QueryLog;
use Brigada\Guardian\Security\QuerySanitizer;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Database\Events\QueryExecuted;

class QueryListener
{
    use SendsDiscordAlerts;

    /** @var array<string, int> Track repeated queries for N+1 detection */
    private static array $queryCounts = [];

    /** @var string|null Current request fingerprint to reset between requests */
    private static ?string $requestId = null;

    public function handle(QueryExecuted $event): void
    {
        $durationMs = $event->time;
        $slowThreshold = config('guardian.monitoring.queries.slow_threshold_ms', 500);
        $isSlow = $durationMs >= $slowThreshold;

        // N+1 detection: track normalized query patterns
        $normalized = $this->normalizeQuery($event->sql);
        $isNPlusOne = $this->detectNPlusOne($normalized);

        // Only log slow queries or N+1 patterns (skip normal queries to avoid volume)
        if (! $isSlow && ! $isNPlusOne) {
            return;
        }

        try {
            $metadata = null;
            
            if ($isNPlusOne) {
                $metadata = ['repeat_count' => self::$queryCounts[$normalized] ?? 0];
            }

            if ($isSlow) {
                $metadata = array_merge($metadata ?? [], [
                    'threshold_ms' => $slowThreshold,
                    'severity' => $durationMs >= ($slowThreshold * 3) ? 'critical' : 'warning',
                ]);
            }

            $data = [
                'sql' => mb_substr(QuerySanitizer::sanitize($event->sql), 0, 5000),
                'duration_ms' => $durationMs,
                'connection' => $event->connectionName,
                'file' => $caller['file'] ?? null,
                'line' => $caller['line'] ?? null,
                'is_slow' => $isSlow,
                'is_n_plus_one' => $isNPlusOne,
                'metadata' => $metadata,
                'created_at' => now(),
            ];

            QueryLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('queries', $data);
            } else {
                app(NightwatchClient::class)->send('queries', $data);
            }
        }  catch (\Throwable) {
            // Don't break the app
        }

        if ($isSlow) {
            $this->sendAlert(
                'Slow Query',
                "Query took {$durationMs}ms on [{$event->connectionName}]: " . mb_substr($event->sql, 0, 200),
                $durationMs >= ($slowThreshold * 3) ? Status::Critical : Status::Warning,
                ['duration_ms' => $durationMs, 'connection' => $event->connectionName, 'file' => $caller['file'] ?? null, 'line' => $caller['line'] ?? null],
            );
        }

        if ($isNPlusOne) {
            $count = self::$queryCounts[$normalized] ?? 0;
            $this->sendAlert(
                'N+1 Query Detected',
                "Query repeated {$count} times: " . mb_substr($event->sql, 0, 200),
                Status::Warning,
                ['repeat_count' => $count, 'file' => $caller['file'] ?? null, 'line' => $caller['line'] ?? null],
            );
        }
    }

    private function normalizeQuery(string $sql): string
    {
        // Replace values with placeholders for pattern matching
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        $sql = preg_replace("/'[^']*'/", '?', $sql);
        $sql = preg_replace('/"[^"]*"/', '?', $sql);

        return trim($sql);
    }

    private function detectNPlusOne(string $normalized): bool
    {
        // Reset between requests
        try {
            $currentId = request()?->fingerprint() ?? 'cli-' . getmypid();
        } catch (\Throwable) {
            $currentId = 'cli-' . getmypid();
        }
        if (self::$requestId !== $currentId) {
            self::$queryCounts = [];
            self::$requestId = $currentId;
        }

        self::$queryCounts[$normalized] = (self::$queryCounts[$normalized] ?? 0) + 1;

        $threshold = config('guardian.monitoring.queries.n_plus_one_threshold', 10);

        // Only flag at the exact threshold to avoid repeated alerts
        return self::$queryCounts[$normalized] === $threshold;
    }

    private function findCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';

            if (str_contains($file, '/vendor/') || str_contains($file, '\\vendor\\')) {
                continue;
            }

            if (str_contains($file, 'Guardian')) {
                continue;
            }

            return ['file' => $file, 'line' => $frame['line'] ?? null];
        }

        return [];
    }
}
