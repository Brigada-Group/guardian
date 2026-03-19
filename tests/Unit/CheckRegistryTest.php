<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Support\CheckRegistry;
use PHPUnit\Framework\TestCase;

class CheckRegistryTest extends TestCase
{
    public function test_it_registers_and_retrieves_checks(): void
    {
        $registry = new CheckRegistry();
        $check = $this->createFakeCheck('Disk Space', Schedule::Hourly);

        $registry->register($check);

        $hourlyChecks = $registry->forSchedule(Schedule::Hourly);
        $this->assertCount(1, $hourlyChecks);
        $this->assertSame('Disk Space', $hourlyChecks[0]->name());
    }

    public function test_it_returns_empty_for_schedule_with_no_checks(): void
    {
        $registry = new CheckRegistry();

        $this->assertSame([], $registry->forSchedule(Schedule::Daily));
    }

    public function test_it_returns_all_checks(): void
    {
        $registry = new CheckRegistry();
        $registry->register($this->createFakeCheck('A', Schedule::Hourly));
        $registry->register($this->createFakeCheck('B', Schedule::Daily));

        $this->assertCount(2, $registry->all());
    }

    private function createFakeCheck(string $name, Schedule $schedule): HealthCheck
    {
        return new class($name, $schedule) implements HealthCheck {
            public function __construct(private string $n, private Schedule $s) {}
            public function name(): string { return $this->n; }
            public function schedule(): Schedule { return $this->s; }
            public function run(): CheckResult { return new CheckResult(Status::Ok, 'ok'); }
        };
    }
}
