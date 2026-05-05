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
        $payload = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
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

    public function sendNow(): bool
    {
        if (! $this->client->isConfigured()) {
            Log::warning('Guardian: heartbeat skipped — client not configured', [
                'guardian_internal' => true,
            ]);

            return false;
        }

        $payload = $this->buildPayload();

        Log::info('Guardian: sending heartbeat', [
            'has_verification_token' => isset($payload['verification_token']),
            'guardian_internal' => true,
        ]);

        $ok = $this->client->send('heartbeat', $payload);

        if ($ok) {
            Log::info('Guardian: heartbeat delivered', [
                'guardian_internal' => true,
            ]);
        } else {
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
