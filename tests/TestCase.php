<?php

namespace Brigada\Guardian\Tests;

use Brigada\Guardian\GuardianServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [GuardianServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('guardian.discord_webhook_url', 'https://discord.com/api/webhooks/test/test');
        $app['config']->set('guardian.project_name', 'Test Project');
        $app['config']->set('guardian.enabled_environments', ['testing']);
        $app['config']->set('guardian.environment', 'testing');
    }
}
