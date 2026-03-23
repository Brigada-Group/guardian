<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Exceptions\ExceptionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class ExceptionNotifierIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('guardian.discord_webhook_url', 'https://discord.com/api/webhooks/test/test');
        $app['config']->set('guardian.exceptions.enabled', true);
        $app['config']->set('guardian.enabled_environments', ['testing']);
    }

    public function test_exception_notifier_is_registered_as_singleton(): void
    {
        $a = $this->app->make(ExceptionNotifier::class);
        $b = $this->app->make(ExceptionNotifier::class);

        $this->assertSame($a, $b);
    }

    public function test_exception_triggers_discord_notification(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        try {
            throw new \RuntimeException('Integration test error');
        } catch (\Throwable $e) {
            $notifier = $this->app->make(ExceptionNotifier::class);
            $notifier->handle($e);
        }

        Http::assertSentCount(1);
    }
}
