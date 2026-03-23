<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Exceptions\ExceptionNotifier;
use Brigada\Guardian\Models\GuardianResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class ExceptionNotifierTest extends TestCase
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
        $app['config']->set('guardian.exceptions.dedup_minutes', 5);
        $app['config']->set('guardian.exceptions.ignored_exceptions', []);
    }

    public function test_it_sends_notification_for_exception(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $notifier = $this->app->make(ExceptionNotifier::class);
        $notifier->handle(new \RuntimeException('Something broke'));

        Http::assertSentCount(1);
    }

    public function test_it_skips_ignored_exceptions(): void
    {
        Http::fake();

        $this->app['config']->set('guardian.exceptions.ignored_exceptions', [
            \InvalidArgumentException::class,
        ]);

        $notifier = $this->app->make(ExceptionNotifier::class);
        $notifier->handle(new \InvalidArgumentException('Bad input'));

        Http::assertNothingSent();
    }

    public function test_it_skips_when_disabled(): void
    {
        Http::fake();

        $this->app['config']->set('guardian.exceptions.enabled', false);

        $notifier = $this->app->make(ExceptionNotifier::class);
        $notifier->handle(new \RuntimeException('Something broke'));

        Http::assertNothingSent();
    }

    public function test_it_throttles_duplicate_exceptions(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $notifier = $this->app->make(ExceptionNotifier::class);
        $exception = new \RuntimeException('Same error');

        $notifier->handle($exception);
        $notifier->handle($exception);
        $notifier->handle($exception);

        Http::assertSentCount(1);
    }

    public function test_it_records_exception_in_database(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $notifier = $this->app->make(ExceptionNotifier::class);
        $notifier->handle(new \RuntimeException('DB error'));

        $this->assertDatabaseHas('guardian_results', [
            'status' => 'error',
        ]);
    }
}
