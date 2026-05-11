<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Models\LogFileCursor;
use Brigada\Guardian\Support\LaravelLogFilePaths;
use Brigada\Guardian\Support\SafeLogsDirectory;
use Brigada\Guardian\Support\TraceContext;
use Brigada\Guardian\Transport\NightwatchClient;
use Illuminate\Console\Command;

class SyncLogSnapshotsCommand extends Command
{
    protected $signature = 'guardian:sync-log-snapshots';

    protected $description = 'Tail Laravel log files under storage/logs (from configured channels) and push deltas to Guardian hub ingest';

    private const ENDPOINT = 'log-file-snapshot';

    public function handle(NightwatchClient $hub): int
    {
        if (! config('guardian.log_file_snapshots.enabled', false)) {
            $this->warn('guardian.log_file_snapshots.enabled is false — nothing to do.');

            return self::SUCCESS;
        }

        $environment = config('guardian.environment', config('app.env'));
        if (! in_array($environment, config('guardian.enabled_environments', ['production']), true)) {
            $this->warn("Guardian is not enabled for environment: {$environment}");

            return self::SUCCESS;
        }

        if (! $hub->isConfigured()) {
            $this->warn('Guardian hub is not configured; skipping log file snapshots.');

            return self::SUCCESS;
        }

        $channels = config('guardian.log_file_snapshots.channels', ['stack']);
        if (! is_array($channels) || $channels === []) {
            $this->warn('guardian.log_file_snapshots.channels is empty.');

            return self::SUCCESS;
        }

        $maxBytes = max(4096, (int) config('guardian.log_file_snapshots.max_bytes', 524_288));
        $initialTailOnly = (bool) config('guardian.log_file_snapshots.initial_tail_only', true);

        $logicalPaths = LaravelLogFilePaths::resolve(array_values(array_filter($channels, static fn ($c) => is_string($c) && $c !== '')));

        foreach ($logicalPaths as $logicalPath) {
            $real = SafeLogsDirectory::sanitizeExistingFile($logicalPath);
            if ($real === null) {
                continue;
            }

            $this->dispatchOneFile($hub, $real, $maxBytes, $initialTailOnly);
        }

        return self::SUCCESS;
    }

    /**
     * Always uses synchronous {@see NightwatchClient::send()} so the byte cursor advances only
     * after the hub acknowledges the POST. Queued ingest would ACK before delivery and risks
     * skipping chunks on hub failure or worker loss.
     */
    private function dispatchOneFile(NightwatchClient $hub, string $realPath, int $maxBytes, bool $initialTailOnly): void
    {
        clearstatcache(true, $realPath);

        $size = filesize($realPath);

        if ($size === false) {
            return;
        }

        $relativeName = basename($realPath);

        $cursor = LogFileCursor::query()->firstOrNew(['resolved_path' => $realPath]);
        $tracked = $cursor->exists;

        $offset = (int) $cursor->byte_offset;

        if (! $tracked) {
            $offset = $initialTailOnly ? max(0, $size - $maxBytes) : 0;
        }

        if ($offset > $size) {
            $offset = max(0, $size - $maxBytes);
        }

        $readable = max(0, $size - $offset);

        if ($readable === 0) {
            return;
        }

        $truncate = false;

        if ($readable > $maxBytes) {
            $truncate = true;
            $offset = $size - $maxBytes;
            $readable = $maxBytes;
        }

        $handle = fopen($realPath, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                return;
            }

            $raw = fread($handle, $readable) ?: '';
        } finally {
            fclose($handle);
        }

        if ($raw === '') {
            return;
        }

        $newOffset = $offset + strlen($raw);

        $payload = array_merge([
            'filename' => $relativeName,
            'path' => $realPath,
            'encoding' => 'utf-8',
            'content' => $raw,
            'truncated' => $truncate,
            'byte_range' => "{$offset}-{$newOffset}",
            'file_size_bytes' => $size,
            'snapshot_at' => now()->toIso8601String(),
            'created_at' => now(),
        ], ['trace_id' => TraceContext::current()]);

        try {
            if ($hub->send(self::ENDPOINT, $payload)) {
                $cursor->byte_offset = $newOffset;
                $cursor->updated_at = now();
                $cursor->save();
            }
        } catch (\Throwable) {
            // Cursor unchanged — next scheduler run retries the same slice
        }
    }
}
