<?php

namespace Brigada\Guardian\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'guardian:install';

    protected $description = 'Install Guardian — publish config and run migrations';

    public function handle(): int
    {
        $this->info('Installing Guardian...');
        $this->newLine();

        // Publish config
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'guardian-config',
        ]);

        // Run migrations
        $this->info('Running migrations...');
        $this->call('migrate', [
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('Guardian installed successfully!');
        $this->newLine();

        // Check for webhook
        if (empty(config('guardian.discord_webhook_url'))) {
            $this->warn('Next step: Add GUARDIAN_DISCORD_WEBHOOK to your .env file.');
            $this->line('  GUARDIAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/...');
            $this->newLine();
            $this->line('Then verify with: php artisan guardian:test');
        } else {
            $this->line('Discord webhook is configured. Verify with: php artisan guardian:test');
        }

        $this->newLine();
        $this->line('Optional .env variables:');
        $this->line('  GUARDIAN_PROJECT_NAME=' . config('app.name'));
        $this->line('  GUARDIAN_ENVIRONMENT=' . config('app.env'));

        return 0;
    }
}
