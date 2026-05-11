<?php

namespace Brigada\Guardian\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendToNightwatchClient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $backoff = 5;

    /** @var string Ingest path segment, e.g. "exceptions", "requests". */
    public string $endpoint;

    /** @var array<string, mixed> Payload merged server-side with project_id, environment, etc. */
    public array $data;

    public function __construct(string $endpoint, array $data)
    {
        $this->endpoint = $endpoint;
        $this->data = $data;
        $this->onQueue(config('guardian.hub.queue', 'default'));
    }

    public function handle(NightwatchClient $client): void
    {
        Log::info('Guardian: SendToNightwatchClient job running', [
            'endpoint' => $this->endpoint,
            'attempt' => $this->attempts(),
            'data_keys' => array_keys($this->data),
            'guardian_internal' => true,
        ]);

        if (! $client->isConfigured()) {
            Log::debug('Guardian: ingest job skipped — hub URL / project ID / token not set', [
                'endpoint' => $this->endpoint,
                'guardian_internal' => true,
            ]);

            return;
        }

        if (! $client->send($this->endpoint, $this->data)) {
            throw new \RuntimeException(
                "Guardian: Nightwatch hub rejected {$this->endpoint} delivery (see laravel.log for status code and body)"
            );
        }
    }
}