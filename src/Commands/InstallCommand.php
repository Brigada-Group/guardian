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

        $this->info('Publishing client error reporter asset...');
        $this->call('vendor:publish', [
            '--tag' => 'guardian-assets',
        ]);

        // Run migrations
        $this->info('Running migrations...');
        $this->call('migrate', [
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('Guardian installed successfully!');
        $this->newLine();
        $this->line('Browser errors: add @include(\'guardian::partials.client-errors-scripts\') early (right after <body> or CSRF meta), not at the very end — csrf meta required.');
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

        $this->newLine();
        $this->line('Nightwatch Hub (centralized dashboard):');
        $this->line('  GUARDIAN_HUB_URL=https://your-hub-domain.com');
        $this->line('  GUARDIAN_HUB_PROJECT_ID=your-project-uuid');
        $this->line('  GUARDIAN_HUB_API_TOKEN=your-api-token');

        return 0;
    }
}
