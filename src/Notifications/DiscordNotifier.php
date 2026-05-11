<?php

namespace Brigada\Guardian\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    private static ?float $rateLimitResetAt = null;

    private readonly string $webhookUrl;

    public function __construct(?string $webhookUrl = null)
    {
        $this->webhookUrl = (string) ($webhookUrl ?? '');
    }

    public function send(array $payload): bool
    {
        if (! $this->isValidWebhookUrl($this->webhookUrl)) {
            return false;
        }

        // Respect rate limits
        if (self::$rateLimitResetAt && microtime(true) < self::$rateLimitResetAt) {
            $waitMs = round((self::$rateLimitResetAt - microtime(true)) * 1000);
            Log::debug("Guardian: Discord rate limited, waiting {$waitMs}ms");
            usleep((int) ($waitMs * 1000));
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            // Track rate limit headers
            $remaining = $response->header('X-RateLimit-Remaining');
            $resetAfter = $response->header('X-RateLimit-Reset-After');

            if ($remaining !== null && (int) $remaining === 0 && $resetAfter) {
                self::$rateLimitResetAt = microtime(true) + (float) $resetAfter;
            }

            // Handle 429 Too Many Requests
            if ($response->status() === 429) {
                $retryAfter = $response->json('retry_after', 1);
                Log::warning("Guardian: Discord rate limited, retry after {$retryAfter}s");
                self::$rateLimitResetAt = microtime(true) + $retryAfter;

                // One retry after waiting
                usleep((int) ($retryAfter * 1_000_000));
                $retryResponse = Http::timeout(10)->post($this->webhookUrl, $payload);

                return $retryResponse->successful();
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Guardian: Failed to send Discord notification', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function isValidWebhookUrl(string $url): bool
    {
        if (empty($url)) {
            Log::warning('Guardian: Discord webhook URL not configured');

            return false;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        if (! in_array($host, ['discord.com', 'discordapp.com'])) {
            Log::warning("Guardian: Discord webhook URL host '{$host}' does not match expected Discord domains. Proceeding anyway (may be a proxy).");
        }

        return true;
    }
}
