<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class TestCommandTest extends TestCase
{
    public function test_it_sends_test_notification(): void
    {
        Http::fake([
            'discord.com/*' => Http::response(null, 204),
        ]);

        $this->artisan('guardian:test')
            ->expectsOutputToContain('Test notification sent successfully')
            ->assertExitCode(0);
    }

    public function test_it_fails_without_webhook(): void
    {
        config()->set('guardian.discord_webhook_url', null);

        $this->artisan('guardian:test')
            ->expectsOutputToContain('GUARDIAN_DISCORD_WEBHOOK is not set')
            ->assertExitCode(1);
    }
}
