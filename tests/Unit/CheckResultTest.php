<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;
use PHPUnit\Framework\TestCase;

class CheckResultTest extends TestCase
{
    public function test_it_creates_a_check_result(): void
    {
        $result = new CheckResult(
            status: Status::Ok,
            message: 'All good',
            metadata: ['disk_percent' => 42.5],
        );

        $this->assertSame(Status::Ok, $result->status);
        $this->assertSame('All good', $result->message);
        $this->assertSame(['disk_percent' => 42.5], $result->metadata);
    }

    public function test_it_identifies_critical_status(): void
    {
        $result = new CheckResult(Status::Critical, 'Disk full');

        $this->assertTrue($result->isCritical());
        $this->assertFalse($result->isOk());
    }

    public function test_it_identifies_ok_status(): void
    {
        $result = new CheckResult(Status::Ok, 'Fine');

        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isCritical());
        $this->assertFalse($result->isWarning());
    }
}
