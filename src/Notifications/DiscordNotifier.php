<?php

namespace Brigada\Guardian\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {}

    public function send(array $payload): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Guardian: Discord webhook URL not configured');

            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Guardian: Failed to send Discord notification', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
