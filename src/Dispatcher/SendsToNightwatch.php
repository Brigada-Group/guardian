<?php

namespace Brigada\Guardian\Dispatcher;

use Brigada\Guardian\Transport\SendToNightwatchClient;

/**
 * Single entry point for dispatching hub ingest queue jobs ({@see SendToNightwatchClient}).
 *
 * Applies {@see config('guardian.dispatch_mode')} (worker vs sync queue connection),
 * optional {@see config('guardian.queue_connection')}, and optional
 * {@see config('guardian.dispatch_after_response')} when using sync dispatch.
 */
final class SendsToNightwatch
{
    /**
     * @return mixed Laravel {@see \Illuminate\Foundation\Bus\PendingDispatch} or queue push result
     */
    public function sendIngest(string $endpoint, array $data): mixed
    {
        return $this->sendIngestJob(new SendToNightwatchClient($endpoint, $data));
    }

    /**
     * @return mixed Laravel {@see \Illuminate\Foundation\Bus\PendingDispatch} or queue push result
     */
    public function sendIngestJob(SendToNightwatchClient $job): mixed
    {
        $mode = (string) config('guardian.dispatch_mode', 'worker');

        if ($mode === 'sync') {
            $pending = dispatch($job)->onConnection('sync');
        } else {
            $pending = dispatch($job);

            $conn = config('guardian.queue_connection');
            if (is_string($conn) && $conn !== '') {
                $pending->onConnection($conn);
            }
        }

        if (config('guardian.dispatch_after_response', false) && $mode === 'sync') {
            $pending->afterResponse();
        }

        return $pending;
    }
}
