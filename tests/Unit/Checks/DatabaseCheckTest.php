<?php

namespace Brigada\Guardian\Tests\Unit\Checks;

use Brigada\Guardian\Checks\DatabaseCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Orchestra\Testbench\TestCase;

class DatabaseCheckTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    public function test_it_reports_database_connected(): void
    {
        $check = new DatabaseCheck();
        $result = $check->run();

        $this->assertSame(Status::Ok, $result->status);
        $this->assertStringContainsString('Connected', $result->message);
        $this->assertArrayHasKey('ping_ms', $result->metadata);
    }

    public function test_schedule_is_hourly(): void
    {
        $check = new DatabaseCheck();
        $this->assertSame(Schedule::Hourly, $check->schedule());
    }
}
