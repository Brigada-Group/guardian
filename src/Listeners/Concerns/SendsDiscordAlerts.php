<?php

namespace Brigada\Guardian\Listeners\Concerns;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Notifications\DiscordNotifier;
use Brigada\Guardian\Results\CheckResult;

trait SendsDiscordAlerts
{
    protected function shouldAlert(): bool
    {
        $environment = config('guardian.environment', config('app.env'));

        return in_array($environment, config('guardian.enabled_environments', ['production']));
    }

    protected function sendAlert(string $category, string $message, Status $status = Status::Warning, array $metadata = []): void
    {
        if (! $this->shouldAlert()) {
            return;
        }

        $dedupKey = "monitor:{$category}:" . md5($message);
        $dedupMinutes = config("guardian.monitoring.{$category}.dedup_minutes", config('guardian.notifications.dedup_minutes', 60));

        $lastNotified = GuardianResult::where('check_class', $dedupKey)
            ->whereNotNull('notified_at')
            ->where('notified_at', '>=', now()->subMinutes($dedupMinutes))
            ->exists();

        if ($lastNotified) {
            return;
        }

        $webhookUrl = (string) (config('guardian.discord_webhook_url') ?? '');
        $projectName = config('guardian.project_name', 'Laravel');
        $environment = config('guardian.environment', 'production');

        $notifier = new DiscordNotifier($webhookUrl);
        $builder = new DiscordMessageBuilder($projectName, $environment);

        $result = new CheckResult($status, $message, $metadata);
        $payload = $builder->buildAlert($category, $result);
        $notifier->send($payload);

        GuardianResult::create([
            'check_class' => $dedupKey,
            'status' => $status->value,
            'message' => mb_substr($message, 0, 1000),
            'metadata' => $metadata,
            'notified_at' => now(),
            'created_at' => now(),
        ]);
    }
}
