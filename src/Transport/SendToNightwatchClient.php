<?php

namespace Brigada\Guardian\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendToNightwatchClient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(
        private readonly string $endpoint,
        private readonly array $data,
    ) {
        $this->onQueue(config('guardian.hub.queue', 'default'));
    }

    public function handle(NightwatchClient $client): void
    {
        $client->send($this->endpoint, $this->data);
    }
}