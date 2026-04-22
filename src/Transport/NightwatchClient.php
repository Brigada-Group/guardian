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

    public function send(string $endpoint,array $data):bool 
    {
        if (!$this->isConfigured())
        {
            return false;
        }

        $payload = array_merge($data,[
            'project_id' => $this->projectId,
            'environment' => config('guardian.environment','production'),
            'server' => gethostname() ?: 'unknown',
            'sent_at' => now()->toIso8601String(),
        ]);

        try {
            $response = Http::withToken($this->token)
                ->timeout(config('guardian.hub.timeout', 5))
                ->retry(config('guardian.hub.retry', 1), 100)
                ->post("{$this->baseUrl}/api/ingest/{$endpoint}", $payload);

            
            return $response->successful();

        } catch (\Throwable $e)
        {
            Log::debug("Guardian: Failed to send data to NightwatchClient",[
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'guardian_internal' => true
            ]);

            return false;
        }
    }
}