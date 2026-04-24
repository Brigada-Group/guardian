<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\OutgoingHttpLog;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;

class OutgoingHttpListener
{
    use SendsDiscordAlerts;

    public function handleResponse(ResponseReceived $event): void
    {
        $request = $event->request;
        $response = $event->response;

        $url = $request->url();

        if ($this->isGuardianHubUrl($url)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        $statusCode = $response->status();
        $durationMs = $this->extractDuration($response);

        try {
            $data = [
                'method' => $request->method(),
                'url' => mb_substr($url, 0, 2048),
                'host' => $host,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'failed' => $statusCode >= 500,
                'created_at' => now(),
            ];

            OutgoingHttpLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('outgoing-http', $data);
            } else {
                app(NightwatchClient::class)->send('outgoing-http', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $slowThreshold = config('guardian.monitoring.outgoing_http.slow_threshold_ms', 10000);
        if ($durationMs && $durationMs >= $slowThreshold) {
            $this->sendAlert(
                'Slow Outgoing HTTP',
                "{$request->method()} {$host} took {$durationMs}ms (threshold: {$slowThreshold}ms)",
                Status::Warning,
                ['url' => mb_substr($url, 0, 500), 'duration_ms' => $durationMs],
            );
        }

        if ($statusCode >= 500) {
            $this->sendAlert(
                'Outgoing HTTP Error',
                "{$request->method()} {$host} returned {$statusCode}",
                Status::Warning,
                ['url' => mb_substr($url, 0, 500), 'status_code' => $statusCode],
            );
        }
    }

    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $request = $event->request;
        $url = $request->url();

        if ($this->isGuardianHubUrl($url)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';

        try {
            $data = [
                'method' => $request->method(),
                'url' => mb_substr($url, 0, 2048),
                'host' => $host,
                'failed' => true,
                'error_message' => 'Connection failed',
                'created_at' => now(),
            ];

            OutgoingHttpLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('outgoing-http', $data);
            } else {
                app(NightwatchClient::class)->send('outgoing-http', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $this->sendAlert(
            'Outgoing HTTP Connection Failed',
            "{$request->method()} {$host} — connection failed",
            Status::Critical,
            ['url' => mb_substr($url, 0, 500)],
        );
    }

    private function extractDuration($response): ?float
    {
        $transferTime = $response->transferStats?->getTransferTime();

        return $transferTime ? round($transferTime * 1000, 2) : null;
    }

    
    private function isGuardianHubUrl(string $url): bool
    {
        $base = config('guardian.hub.url');
        if (! is_string($base) || $base === '') {
            return false;
        }

        $base = rtrim($base, '/');
        $urlTrim = rtrim($url, '/');

        return $base !== '' && str_starts_with($urlTrim, $base);
    }
}
