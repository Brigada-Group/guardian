<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class JobListener
{
    use SendsDiscordAlerts;

    /** @var array<string, float> */
    private static array $startTimes = [];

    public function handleProcessing(JobProcessing $event): void
    {
        self::$startTimes[$event->job->getJobId()] = microtime(true);
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $durationMs = $this->getDuration($event->job->getJobId());
        $jobClass = $this->resolveJobClass($event->job);

        try {
            $data = [
                'job_class' => $jobClass,
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'status' => 'completed',
                'duration_ms' => $durationMs,
                'attempt' => $event->job->attempts(),
                'created_at' => now(),
            ];

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('jobs', $data);
            } else {
                app(NightwatchClient::class)->send('jobs', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        // Alert on slow jobs
        $slowThreshold = config('guardian.monitoring.jobs.slow_threshold_ms', 30000);
        if ($durationMs && $durationMs >= $slowThreshold) {
            $this->sendAlert(
                'Slow Job',
                "Job {$jobClass} took {$durationMs}ms on queue [{$event->job->getQueue()}] (threshold: {$slowThreshold}ms)",
                Status::Warning,
                ['job_class' => $jobClass, 'duration_ms' => $durationMs, 'queue' => $event->job->getQueue()],
            );
        }
    }

    public function handleFailed(JobFailed $event): void
    {
        $durationMs = $this->getDuration($event->job->getJobId());
        $jobClass = $this->resolveJobClass($event->job);
        $errorMessage = $event->exception?->getMessage() ?? 'Unknown error';

        try {
            $data = [
                'job_class' => $jobClass,
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'attempt' => $event->job->attempts(),
                'error_message' => mb_substr($errorMessage, 0, 2000),
                'metadata' => [
                    'exception_class' => $event->exception ? get_class($event->exception) : null,
                    'file' => $event->exception?->getFile(),
                    'line' => $event->exception?->getLine(),
                ],
                'created_at' => now(),
            ];

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('jobs', $data);
            } else {
                app(NightwatchClient::class)->send('jobs', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $this->sendAlert(
            'Job Failed',
            "Job {$jobClass} failed on queue [{$event->job->getQueue()}]: " . mb_substr($errorMessage, 0, 200),
            Status::Critical,
            ['job_class' => $jobClass, 'queue' => $event->job->getQueue(), 'attempt' => $event->job->attempts()],
        );
    }

    private function getDuration(string $jobId): ?float
    {
        if (! isset(self::$startTimes[$jobId])) {
            return null;
        }

        $duration = round((microtime(true) - self::$startTimes[$jobId]) * 1000, 2);
        unset(self::$startTimes[$jobId]);

        return $duration;
    }

    private function resolveJobClass($job): string
    {
        $payload = $job->payload();

        return $payload['displayName'] ?? $payload['job'] ?? get_class($job);
    }
}
