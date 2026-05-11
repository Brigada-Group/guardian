<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Notifications\DiscordNotifier;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Enums\Status;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'guardian:test';

    protected $description = 'Send a test notification to Discord to verify webhook configuration';

    public function handle(): int
    {
        $webhookUrl = (string) (config('guardian.discord_webhook_url') ?? '');

        if ($webhookUrl === '') {
            $this->error('GUARDIAN_DISCORD_WEBHOOK is not set. Add it to your .env file.');
            return 1;
        }

        $this->info('Sending test notification to Discord...');

        $projectName = config('guardian.project_name', config('app.name'));
        $environment = config('guardian.environment', config('app.env'));

        $notifier = new DiscordNotifier($webhookUrl);
        $builder = new DiscordMessageBuilder($projectName, $environment);

        $result = new CheckResult(Status::Ok, 'Guardian is connected and working! This is a test notification.');

        $payload = $builder->buildAlert('Connection Test', $result);

        // Override color to blue for test messages
        $payload['embeds'][0]['color'] = 0x3498DB;
        $payload['embeds'][0]['title'] = "[{$projectName}] \xF0\x9F\x94\x94 Guardian Test";

        $success = $notifier->send($payload);

        if ($success) {
            $this->info('Test notification sent successfully! Check your Discord channel.');
            return 0;
        }

        $this->error('Failed to send test notification. Check your webhook URL and network connectivity.');
        return 1;
    }
}
