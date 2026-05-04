<?php

namespace Brigada\Guardian\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

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
        $client->send($this->endpoint, $this->data);
    }
}