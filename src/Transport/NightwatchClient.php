<?php 

namespace Brigada\Guardian\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class NightwatchClient 
{
    private string $baseUrl;
    private string $projectId;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('guardian.hub.url',''),'/');
        $this->projectId = config('guardian.hub.project_id','');
        $this->token = config('guardian.hub.api_token','');
    }

    public function isConfigured(): bool 
    {
        return !empty($this->baseUrl)
            && !empty($this->projectId)
            && !empty($this->token);
    }

    public function send(string $endpoint, array $data): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Guardian: send dropped — client not configured', [
                'endpoint' => $endpoint,
                'has_base_url' => $this->baseUrl !== '',
                'has_project_id' => $this->projectId !== '',
                'has_token' => $this->token !== '',
                'guardian_internal' => true,
            ]);

            return false;
        }

        $payload = array_merge($data, [
            'project_id' => $this->projectId,
            'environment' => config('guardian.environment', 'production'),
            'server' => gethostname() ?: 'unknown',
            'sent_at' => now()->toIso8601String(),
        ]);

        $url = "{$this->baseUrl}/api/ingest/{$endpoint}";

        Log::info('Guardian: sending to Nightwatch hub', [
            'endpoint' => $endpoint,
            'url' => $url,
            'project_id' => $this->projectId,
            'payload_keys' => array_keys($payload),
            'payload_size_bytes' => strlen(json_encode($payload) ?: ''),
            'guardian_internal' => true,
        ]);

        try {
            $response = Http::withToken($this->token)
                ->timeout(config('guardian.hub.timeout', 5))
                ->retry(config('guardian.hub.retry', 1), 100)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('Guardian: Nightwatch hub rejected delivery', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'guardian_internal' => true,
                ]);

                return false;
            }

            Log::info('Guardian: Nightwatch hub accepted delivery', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'guardian_internal' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Guardian: connection error sending to Nightwatch hub', [
                'endpoint' => $endpoint,
                'url' => $url,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
                'guardian_internal' => true,
            ]);

            return false;
        }
    }
}