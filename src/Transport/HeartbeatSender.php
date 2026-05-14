<?php

namespace Brigada\Guardian\Transport;

use Brigada\Guardian\Support\TraceContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HeartbeatSender
{
    public const VERIFY_TOKEN_CACHE_KEY = 'guardian.verify_token';

    public function __construct(private NightwatchClient $client)
    {
    }

    public function buildPayload(): array
    {
        $maxLen = (int) config('guardian.hub.scheduled_heartbeat.max_version_length', 50);
        $maxLen = max(1, min(255, $maxLen));

        $payload = [
            'php_version' => mb_substr((string) PHP_VERSION, 0, $maxLen),
            'laravel_version' => mb_substr((string) app()->version(), 0, $maxLen),
            'trace_id' => TraceContext::current(),
        ];

        // Setup-verification ceremony: guardian:verify stashes a 6-digit token
        // here; the next heartbeat carries it through to the hub, which marks
        // the project verified. The regex is defense-in-depth — the cache
        // shouldn't ever hold a malformed token, but if it does we drop it
        // rather than feeding the hub a 422.
        $verifyToken = Cache::get(self::VERIFY_TOKEN_CACHE_KEY);
        if (is_string($verifyToken) && preg_match('/^[0-9]{6}$/', $verifyToken)) {
            $payload['verification_token'] = $verifyToken;
        }

        return $payload;
    }

    /**
     * @param  bool  $silent  Scheduled ticks: quieter logging via {@see NightwatchClient::send()} $quiet flag.
     */
    public function sendNow(bool $silent = false): bool
    {
        if (! $this->client->isConfigured()) {
            if (! $silent) {
                Log::warning('Guardian: heartbeat skipped — client not configured', [
                    'guardian_internal' => true,
                ]);
            }

            return false;
        }

        $payload = $this->buildPayload();

        if (! $silent) {
            Log::info('Guardian: sending heartbeat', [
                'has_verification_token' => isset($payload['verification_token']),
                'guardian_internal' => true,
            ]);
        }

        $ok = $this->client->send('heartbeat', $payload, $silent);

        if ($ok && ! $silent) {
            Log::info('Guardian: heartbeat delivered', [
                'guardian_internal' => true,
            ]);
        } elseif ($ok && $silent) {
            Log::debug('Guardian: scheduled heartbeat delivered', [
                'guardian_internal' => true,
            ]);
        } elseif (! $ok && ! $silent) {
            Log::warning('Guardian: heartbeat delivery failed', [
                'guardian_internal' => true,
            ]);
        }

        // Only consume the cached token after a successful POST so a transient
        // hub outage doesn't burn the user's one-shot verification — the next
        // scheduled heartbeat will redeliver it (within the 5-minute TTL).
        if ($ok && isset($payload['verification_token'])) {
            Cache::forget(self::VERIFY_TOKEN_CACHE_KEY);
        }

        return $ok;
    }
}
