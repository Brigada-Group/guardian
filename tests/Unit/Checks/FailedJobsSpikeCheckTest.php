<?php

namespace Brigada\Guardian\Tests\Unit\Checks;

use Brigada\Guardian\Checks\FailedJobsSpikeCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class FailedJobsSpikeCheckTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('guardian.thresholds.failed_jobs_spike', ['warning' => 5, 'critical' => 20]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function test_it_returns_ok_when_no_failed_jobs(): void
    {
        $check = new FailedJobsSpikeCheck();
        $result = $check->run();

        $this->assertSame(Status::Ok, $result->status);
    }

    public function test_it_returns_warning_when_threshold_exceeded(): void
    {
        for ($i = 0; $i < 6; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Error',
                'failed_at' => now()->subMinutes(10),
            ]);
        }

        $check = new FailedJobsSpikeCheck();
        $result = $check->run();

        $this->assertSame(Status::Warning, $result->status);
    }

    public function test_schedule_is_every_five_minutes(): void
    {
        $check = new FailedJobsSpikeCheck();
        $this->assertSame(Schedule::EveryFiveMinutes, $check->schedule());
    }
}
