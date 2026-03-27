<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_it_runs_installation(): void
    {
        $this->artisan('guardian:install')
            ->expectsOutputToContain('Guardian installed successfully')
            ->assertExitCode(0);
    }
}
