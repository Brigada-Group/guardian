<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\CommandLog;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;

class CommandListener
{
    use SendsDiscordAlerts;

    /** @var array<string, float> */
    private static array $startTimes = [];

    /** @var string[] Commands to ignore */
    private const IGNORED_COMMANDS = [
        'schedule:run',
        'schedule:finish',
        'queue:work',
        'queue:listen',
        'horizon',
        'horizon:work',
        'guardian:run',
        'guardian:status',
        'guardian:test',
        'package:discover',
    ];

    public function handleStarting(CommandStarting $event): void
    {
        if ($event->command) {
            self::$startTimes[$event->command] = microtime(true);
        }
    }

    public function handleFinished(CommandFinished $event): void
    {
        $command = $event->command;

        if (! $command || $this->isIgnored($command)) {
            return;
        }

        $durationMs = null;
        if (isset(self::$startTimes[$command])) {
            $durationMs = round((microtime(true) - self::$startTimes[$command]) * 1000, 2);
            unset(self::$startTimes[$command]);
        }

        try {
            $data = [
                'command' => $command,
                'exit_code' => $event->exitCode,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ];

            CommandLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('commands', $data);
            } else {
                app(NightwatchClient::class)->send('commands', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        // Alert on failed commands
        if ($event->exitCode !== 0) {
            $this->sendAlert(
                'Command Failed',
                "artisan {$command} exited with code {$event->exitCode}" . ($durationMs ? " ({$durationMs}ms)" : ''),
                Status::Warning,
                ['command' => $command, 'exit_code' => $event->exitCode, 'duration_ms' => $durationMs],
            );
        }

        // Alert on slow commands
        $slowThreshold = config('guardian.monitoring.commands.slow_threshold_ms', 60000);
        if ($durationMs && $durationMs >= $slowThreshold) {
            $this->sendAlert(
                'Slow Command',
                "artisan {$command} took {$durationMs}ms (threshold: {$slowThreshold}ms)",
                Status::Warning,
                ['command' => $command, 'duration_ms' => $durationMs],
            );
        }
    }

    private function isIgnored(string $command): bool
    {
        $ignored = array_merge(
            self::IGNORED_COMMANDS,
            config('guardian.monitoring.commands.ignored', []),
        );

        return in_array($command, $ignored, true);
    }
}
