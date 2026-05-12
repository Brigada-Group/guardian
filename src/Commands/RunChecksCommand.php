<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Notifications\DiscordNotifier;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Support\CheckRegistry;
use Brigada\Guardian\Support\Deduplicator;
use Brigada\Guardian\Support\TraceContext;
use Brigada\Guardian\Dispatcher\SendsToNightwatch;
use Brigada\Guardian\Transport\NightwatchClient;
use Illuminate\Console\Command;

class RunChecksCommand extends Command
{
    protected $signature = 'guardian:run
        {schedule? : Schedule group to run (every_5_min, hourly, daily, weekly)}
        {--check= : Run a single check by class name}';

    protected $description = 'Run Guardian health checks and send notifications';

    public function handle(CheckRegistry $registry, Deduplicator $deduplicator): int
    {
        $environment = config('guardian.environment', config('app.env'));

        if (! in_array($environment, config('guardian.enabled_environments', ['production']))) {
            $this->warn("Guardian is not enabled for environment: {$environment}");
            return 0;
        }

        $webhookUrl = (string) (config('guardian.discord_webhook_url') ?? '');
        $projectName = config('guardian.project_name', config('app.name'));

        $notifier = new DiscordNotifier($webhookUrl);
        $messageBuilder = new DiscordMessageBuilder($projectName, $environment);

        if ($checkClass = $this->option('check')) {
            $check = $this->findCheck($registry, $checkClass);
            if (! $check) {
                $this->error("Check not found: {$checkClass}");
                return 1;
            }
            $result = $check->run();
            $this->outputResult($check->name(), $result);
            $this->handleNotification($check, $result, $deduplicator, $notifier, $messageBuilder);
            return 0;
        }

        $scheduleName = $this->argument('schedule');
        if (! $scheduleName) {
            $this->error('Please specify a schedule: every_5_min, hourly, daily, weekly');
            return 1;
        }

        $schedule = Schedule::tryFrom($scheduleName);
        if (! $schedule) {
            $this->error("Invalid schedule: {$scheduleName}");
            return 1;
        }

        $checks = $registry->forSchedule($schedule);
        $this->info("Running {$scheduleName} checks (" . count($checks) . " checks)...");

        $results = [];
        foreach ($checks as $check) {
            if ($this->isDisabled($check)) {
                $this->line("  [SKIP] {$check->name()}");
                continue;
            }
            $result = $check->run();
            $results[$check->name()] = $result;
            $this->outputResult($check->name(), $result);
            $this->handleNotification($check, $result, $deduplicator, $notifier, $messageBuilder);
        }

        $this->forwardHealthToHub($results);

        if (in_array($schedule, [Schedule::Daily, Schedule::Weekly])) {
            $title = $schedule === Schedule::Weekly ? 'Weekly Full Report' : 'Daily Health Summary';
            $payload = $messageBuilder->buildSummary($title, $results);
            $notifier->send($payload);
            $this->info("Summary sent to Discord.");
        }

        $pruned = $deduplicator->prune();
        if ($pruned > 0) {
            $this->line("Pruned {$pruned} old results.");
        }

        return 0;
    }

    private function findCheck(CheckRegistry $registry, string $className): ?HealthCheck
    {
        foreach ($registry->all() as $check) {
            $shortName = class_basename($check);
            if ($shortName === $className || $check::class === $className) {
                return $check;
            }
        }
        return null;
    }

    private function isDisabled(HealthCheck $check): bool
    {
        return in_array($check::class, config('guardian.disabled_checks', []));
    }

    private function outputResult(string $name, CheckResult $result): void
    {
        $icon = match ($result->status->value) {
            'ok' => '<info>[OK]</info>',
            'warning' => '<comment>[WARN]</comment>',
            'critical' => '<error>[CRIT]</error>',
            'error' => '<error>[ERR]</error>',
        };
        $this->line("  {$icon} {$name} — {$result->message}");
    }

    private function handleNotification(
        HealthCheck $check,
        CheckResult $result,
        Deduplicator $deduplicator,
        DiscordNotifier $notifier,
        DiscordMessageBuilder $messageBuilder,
    ): void {
        $shouldNotify = $deduplicator->shouldNotify(class_basename($check), $result);
        if ($shouldNotify) {
            $payload = $messageBuilder->buildAlert($check->name(), $result);
            $notifier->send($payload);
        }
        $deduplicator->record(class_basename($check), $result, $shouldNotify);
    }

    private function forwardHealthToHub(array $results): void
    {
        if (empty($results)) {
            return;
        }

        $data = [
            'checks' => collect($results)->map(fn (CheckResult $r, string $name) => [
                'name' => $name,
                'status' => $r->status->value,
                'message' => $r->message,
                'metadata' => $r->metadata,
            ])->values()->all(),
        ];

        $payload = $data + ['trace_id' => TraceContext::current()];

        try {
            if (config('guardian.hub.async', true)) {
                app(SendsToNightwatch::class)->sendIngest('health', $payload);
            } else {
                app(NightwatchClient::class)->send('health', $payload);
            }
        } catch (\Throwable) {
        }
    }
}
