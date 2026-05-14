<?php

namespace Brigada\Guardian\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class NightwatchClient
{
    private string $baseUrl;
    private string $projectId;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('guardian.hub.url', ''), '/');
        $this->projectId = (string) config('guardian.hub.project_id', '');
        $this->token = (string) config('guardian.hub.api_token', '');
    }

    public function isConfigured(): bool 
    {
        return !empty($this->baseUrl)
            && !empty($this->projectId)
            && !empty($this->token);
    }

    /**
     * @param  bool  $quiet  When true, omit routine success/info logs and throttle failure logs (for scheduled heartbeats).
     */
    public function send(string $endpoint, array $data, bool $quiet = false): bool
    {
        if (! $this->isConfigured()) {
            if (! $quiet) {
                Log::warning('Guardian: send dropped — client not configured', [
                    'endpoint' => $endpoint,
                    'has_base_url' => $this->baseUrl !== '',
                    'has_project_id' => $this->projectId !== '',
                    'has_token' => $this->token !== '',
                    'guardian_internal' => true,
                ]);
            }

            return false;
        }

        $payload = array_merge($data, [
            'project_id' => $this->projectId,
            'environment' => config('guardian.environment', 'production'),
            'server' => gethostname() ?: 'unknown',
            'sent_at' => now()->toIso8601String(),
        ]);

        $url = "{$this->baseUrl}/api/ingest/{$endpoint}";

        if (! $quiet) {
            Log::info('Guardian: sending to Nightwatch hub', [
                'endpoint' => $endpoint,
                'url' => $url,
                'project_id' => $this->projectId,
                'payload_keys' => array_keys($payload),
                'payload_size_bytes' => strlen(json_encode($payload) ?: ''),
                'guardian_internal' => true,
            ]);
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(config('guardian.hub.timeout', 5))
                ->retry(config('guardian.hub.retry', 1), 100)
                ->post($url, $payload);

            if (! $response->successful()) {
                $this->maybeLogRejected($endpoint, $url, $response->status(), mb_substr($response->body(), 0, 500), $quiet);

                return false;
            }

            if (! $quiet) {
                Log::info('Guardian: Nightwatch hub accepted delivery', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'guardian_internal' => true,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            $this->maybeLogConnectionError($endpoint, $url, $e, $quiet);

            return false;
        }
    }

    private function maybeLogRejected(string $endpoint, string $url, int $status, string $bodySnippet, bool $quiet): void
    {
        $logFn = fn () => Log::warning('Guardian: Nightwatch hub rejected delivery', [
            'endpoint' => $endpoint,
            'url' => $url,
            'status' => $status,
            'body' => $bodySnippet,
            'guardian_internal' => true,
        ]);

        if (! $quiet) {
            $logFn();

            return;
        }

        $minutes = (int) config('guardian.hub.scheduled_heartbeat.failure_log_throttle_minutes', 15);
        $minutes = max(1, $minutes);
        if (Cache::add('guardian:hub:rejected:' . $endpoint, 1, now()->addMinutes($minutes))) {
            $logFn();
        }
    }

    private function maybeLogConnectionError(string $endpoint, string $url, \Throwable $e, bool $quiet): void
    {
        $logFn = fn () => Log::error('Guardian: connection error sending to Nightwatch hub', [
            'endpoint' => $endpoint,
            'url' => $url,
            'error_class' => get_class($e),
            'error' => $e->getMessage(),
            'guardian_internal' => true,
        ]);

        if (! $quiet) {
            $logFn();

            return;
        }

        $minutes = (int) config('guardian.hub.scheduled_heartbeat.failure_log_throttle_minutes', 15);
        $minutes = max(1, $minutes);
        if (Cache::add('guardian:hub:conn_err:' . $endpoint, 1, now()->addMinutes($minutes))) {
            $logFn();
        }
    }
}