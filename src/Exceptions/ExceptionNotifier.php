<?php

namespace Brigada\Guardian\Exceptions;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Notifications\DiscordNotifier;
use Brigada\Guardian\Security\HeaderFilter;
use Brigada\Guardian\Security\StackTraceSanitizer;
use Brigada\Guardian\Dispatcher\SendsToNightwatch;
use Brigada\Guardian\Support\TraceContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Brigada\Guardian\Transport\NightwatchClient;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionNotifier
{
    private DiscordNotifier $notifier;

    private DiscordMessageBuilder $builder;

    public function __construct()
    {
        $this->notifier = new DiscordNotifier((string) (config('guardian.discord_webhook_url') ?? ''));
        $this->builder = new DiscordMessageBuilder(
            config('guardian.project_name', 'Laravel'),
            config('guardian.environment', 'production'),
        );
    }

    public function handle(\Throwable $e): void
    {
        Log::info('Guardian: ExceptionNotifier received exception', [
            'exception' => get_class($e),
            'message' => mb_substr($e->getMessage(), 0, 200),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'guardian_internal' => true,
        ]);

        if (! config('guardian.exceptions.enabled', true)) {
            Log::debug('Guardian: exception reporting disabled in config', [
                'guardian_internal' => true,
            ]);

            return;
        }

        $ignoredExceptions = config('guardian.exceptions.ignored_exceptions', []);

        foreach ($ignoredExceptions as $ignoredException) {
            if ($e instanceof $ignoredException) {
                Log::debug('Guardian: exception ignored by config rule', [
                    'exception' => get_class($e),
                    'matched_rule' => $ignoredException,
                    'guardian_internal' => true,
                ]);

                return;
            }
        }

        $dedupKey = $this->dedupKey($e);

        if (! $this->shouldNotify($dedupKey)) {
            Log::debug('Guardian: exception suppressed by dedup window', [
                'dedup_key' => $dedupKey,
                'guardian_internal' => true,
            ]);
            $this->record($dedupKey, $e, false);

            return;
        }

        $context = $this->extractContext($e);

        $payload = $this->builder->buildException(
            exceptionClass: get_class($e),
            message: StackTraceSanitizer::sanitize($e->getMessage()),
            url: $context['url'],
            statusCode: $context['status_code'],
            user: $context['user'],
            ip: $context['ip'],
            headers: $context['headers'],
            stackTrace: "```\n" . StackTraceSanitizer::sanitize($this->formatStackTrace($e)) . "\n```",
        );

        $this->notifier->send($payload);
        $this->record($dedupKey, $e, true, $context);

        $this->forwardToNightwatch($e,$context);
    }

    private function dedupKey(\Throwable $e): string
    {
        return 'exception:' . get_class($e) . ':' . $e->getFile() . ':' . $e->getLine();
    }

    private function shouldNotify(string $dedupKey): bool
    {
        $dedupMinutes = config('guardian.exceptions.dedup_minutes', 5);

        $lastNotified = GuardianResult::where('check_class', $dedupKey)
            ->whereNotNull('notified_at')
            ->latest('notified_at')
            ->first();

        if (! $lastNotified) {
            return true;
        }

        return $lastNotified->notified_at->diffInMinutes(now()) >= $dedupMinutes;
    }

    private function record(string $dedupKey, \Throwable $e, bool $notified, ?array $context = null): void
    {
        if (! $context) {
            $context = $this->extractContext($e);
        }

        GuardianResult::create([
            'check_class' => $dedupKey,
            'status' => Status::Error->value,
            'message' => mb_substr(StackTraceSanitizer::sanitize($e->getMessage()), 0, 1000),
            'metadata' => [
                'exception_class' => get_class($e),
                'file' => StackTraceSanitizer::sanitize($e->getFile()),
                'line' => $e->getLine(),
                'url' => $context['url'] ?? 'N/A',
                'status_code' => $context['status_code'] ?? 500,
                'user' => $context['user'] ?? 'N/A',
                'ip' => $context['ip'] ?? 'N/A',
                'headers' => $context['headers'] ?? 'N/A',
                'stack_trace' => StackTraceSanitizer::sanitize($this->formatStackTrace($e)),
            ],
            'notified_at' => $notified ? now() : null,
            'created_at' => now(),
        ]);
    }

    private function extractContext(\Throwable $e): array
    {
        if (app()->runningInConsole()) {
            return [
                'url' => 'CLI: ' . implode(' ', $_SERVER['argv'] ?? ['unknown']),
                'status_code' => 1,
                'user' => 'N/A',
                'ip' => 'N/A',
                'headers' => 'N/A',
            ];
        }

        $request = Request::instance();
        $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $user = 'Guest';
        if (Auth::check()) {
            $authUser = Auth::user();
            $user = 'ID:' . $authUser->getAuthIdentifier() . ' ' . ($authUser->email ?? '');
        }

        $allHeaders = collect($request->headers->all())
            ->map(fn ($values) => implode(', ', $values))
            ->toArray();
        $safeHeaders = HeaderFilter::filter($allHeaders);
        $headerLines = collect($safeHeaders)
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode("\n");

        return [
            'url' => $request->method() . ' ' . $request->fullUrl(),
            'status_code' => $statusCode,
            'user' => $user,
            'ip' => $request->ip() ?? 'N/A',
            'headers' => $headerLines ?: 'N/A',
        ];
    }

    private function formatStackTrace(\Throwable $e): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        $lines = array_slice($lines, 0, 10);

        return implode("\n", $lines);
    }

    private function forwardToNightwatch(\Throwable $e, array $context): void 
    {
        $data = [
            'exception_class' => get_class($e),
            'message' => mb_substr(StackTraceSanitizer::sanitize($e->getMessage()), 0, 1000),
            'file' => StackTraceSanitizer::sanitize($e->getFile()),
            'line' => $e->getLine(),
            'url' => $context['url'] ?? 'N/A',
            'status_code' => $context['status_code'] ?? 500,
            'user' => $context['user'] ?? 'N/A',
            'ip' => $context['ip'] ?? 'N/A',
            'headers' => $context['headers'] ?? 'N/A',
            'stack_trace' => StackTraceSanitizer::sanitize($this->formatStackTrace($e)),
            'severity' => 'error',
        ];

        $payload = $data + ['trace_id' => TraceContext::current()];

        $async = config('guardian.hub.async', true);

        Log::info('Guardian: forwarding exception to Nightwatch hub', [
            'exception' => get_class($e),
            'mode' => $async ? 'async (queue)' : 'sync',
            'guardian_internal' => true,
        ]);

        try {
            if ($async) {
                app(SendsToNightwatch::class)->sendIngest('exceptions', $payload);
            } else {
                app(NightwatchClient::class)->send('exceptions', $payload);
            }
        } catch (\Throwable $forwardError) {
            Log::error('Guardian: failed to forward exception to hub', [
                'exception' => get_class($e),
                'forward_error_class' => get_class($forwardError),
                'forward_error' => $forwardError->getMessage(),
                'guardian_internal' => true,
            ]);
        }
    }
}
