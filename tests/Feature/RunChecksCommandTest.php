<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RunChecksCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_it_runs_hourly_checks(): void
    {
        $this->artisan('guardian:run', ['schedule' => 'hourly'])
            ->assertSuccessful();
    }

    public function test_it_rejects_invalid_schedule(): void
    {
        $this->artisan('guardian:run', ['schedule' => 'invalid'])
            ->assertFailed();
    }

    public function test_it_requires_schedule_argument(): void
    {
        $this->artisan('guardian:run')
            ->assertFailed();
    }

    public function test_status_command_works_with_no_results(): void
    {
        $this->artisan('guardian:status')
            ->assertSuccessful();
    }
}
