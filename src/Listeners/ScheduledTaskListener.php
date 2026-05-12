<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\ScheduledTaskLog;
use Brigada\Guardian\Support\TraceContext;
use Brigada\Guardian\Dispatcher\SendsToNightwatch;
use Brigada\Guardian\Transport\NightwatchClient;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;

class ScheduledTaskListener
{
    use SendsDiscordAlerts;

    /** @var array<string, float> */
    private static array $startTimes = [];

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $key = $this->taskKey($event->task);
        self::$startTimes[$key] = microtime(true);
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;
        $key = $this->taskKey($task);
        $durationMs = $this->getDuration($key);

        $taskName = $task->command ?? $task->description ?? 'unknown';

        try {
            $data = [
                'task' => $taskName,
                'description' => $task->description,
                'expression' => $task->expression,
                'status' => 'completed',
                'duration_ms' => $durationMs,
                'output' => $this->getOutput($task),
                'created_at' => now(),
            ];

            ScheduledTaskLog::create($data);

            $payload = $data + ['trace_id' => TraceContext::current()];

            if (config('guardian.hub.async', true)) {
                app(SendsToNightwatch::class)->sendIngest('scheduled-tasks', $payload);
            } else {
                app(NightwatchClient::class)->send('scheduled-tasks', $payload);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        // Alert on slow scheduled tasks
        $slowThreshold = config('guardian.monitoring.scheduled_tasks.slow_threshold_ms', 300000);
        if ($durationMs && $durationMs >= $slowThreshold) {
            $this->sendAlert(
                'Slow Scheduled Task',
                "Task [{$taskName}] took {$durationMs}ms (threshold: {$slowThreshold}ms)",
                Status::Warning,
                ['task' => $taskName, 'duration_ms' => $durationMs],
            );
        }
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $task = $event->task;
        $key = $this->taskKey($task);
        $durationMs = $this->getDuration($key);

        $taskName = $task->command ?? $task->description ?? 'unknown';
        $errorMessage = $event->exception->getMessage();

        try {
            $data = [
                'task' => $taskName,
                'description' => $task->description,
                'expression' => $task->expression,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'output' => mb_substr($errorMessage, 0, 5000),
                'created_at' => now(),
            ];

            ScheduledTaskLog::create($data);

            $payload = $data + ['trace_id' => TraceContext::current()];

            if (config('guardian.hub.async', true)) {
                app(SendsToNightwatch::class)->sendIngest('scheduled-tasks', $payload);
            } else {
                app(NightwatchClient::class)->send('scheduled-tasks', $payload);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $this->sendAlert(
            'Scheduled Task Failed',
            "Task [{$taskName}] failed: " . mb_substr($errorMessage, 0, 200),
            Status::Critical,
            ['task' => $taskName, 'error' => mb_substr($errorMessage, 0, 500)],
        );
    }

    public function handleSkipped(ScheduledTaskSkipped $event): void
    {
        $task = $event->task;
        $taskName = $task->command ?? $task->description ?? 'unknown';

        try {
            $data = [
                'task' => $taskName,
                'description' => $task->description,
                'expression' => $task->expression,
                'status' => 'skipped',
                'created_at' => now(),
            ];

            ScheduledTaskLog::create($data);

            $payload = $data + ['trace_id' => TraceContext::current()];

            if (config('guardian.hub.async', true)) {
                app(SendsToNightwatch::class)->sendIngest('scheduled-tasks', $payload);
            } else {
                app(NightwatchClient::class)->send('scheduled-tasks', $payload);
            }
        } catch (\Throwable) {
            // Don't break the app
        }
    }

    private function taskKey($task): string
    {
        return md5(($task->command ?? '') . $task->expression);
    }

    private function getDuration(string $key): ?float
    {
        if (! isset(self::$startTimes[$key])) {
            return null;
        }

        $duration = round((microtime(true) - self::$startTimes[$key]) * 1000, 2);
        unset(self::$startTimes[$key]);

        return $duration;
    }

    private function getOutput($task): ?string
    {
        if (method_exists($task, 'getOutput') && $task->output) {
            try {
                $output = file_get_contents($task->output);

                return $output ? mb_substr(trim($output), 0, 5000) : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
