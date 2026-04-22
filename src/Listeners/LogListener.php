<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Security\StackTraceSanitizer;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Log\Events\MessageLogged;

class LogListener
{
    use SendsDiscordAlerts;

    /** @var string[] Log levels to capture */
    private const CAPTURED_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning'];

    public function handle(MessageLogged $event): void
    {
        if (! in_array($event->level, self::CAPTURED_LEVELS, true)) {
            return;
        }

        // Prevent infinite loop — don't log Guardian's own log entries
        $context = $event->context ?? [];
        if (! empty($context['guardian_internal'])) {
            return;
        }

        $message = StackTraceSanitizer::sanitize(mb_substr($event->message, 0, 2000));

        // Sanitize context — remove exception objects (not serializable), keep scalar values
        $safeContext = $this->sanitizeContext($context);

        try {
            $data = [
                'level' => $event->level,
                'message' => $message,
                'channel' => $context['__channel'] ?? config('logging.default', 'stack'),
                'context' => $safeContext ?: null,
                'created_at' => now(),
            ];

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('logs', $data);
            } else {
                app(NightwatchClient::class)->send('logs', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        // Alert on critical/emergency
        if (in_array($event->level, ['emergency', 'alert', 'critical'], true)) {
            $this->sendAlert(
                'Critical Log Entry',
                "[{$event->level}] {$message}",
                Status::Critical,
                ['level' => $event->level],
            );
        }
    }

    private function sanitizeContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if ($key === 'exception' || $key === '__channel') {
                continue;
            }

            if (is_scalar($value) || is_null($value)) {
                $safe[$key] = $value;
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitizeContext($value);
            } else {
                $safe[$key] = '[object]';
            }
        }

        return $safe;
    }
}
