<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;
use Brigada\Guardian\Support\Deduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class DeduplicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [\Brigada\Guardian\GuardianServiceProvider::class];
    }

    public function test_it_allows_notification_when_no_prior_result(): void
    {
        $dedup = new Deduplicator();
        $result = new CheckResult(Status::Critical, 'Disk full');

        $this->assertTrue($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_blocks_duplicate_notification_within_window(): void
    {
        $dedup = new Deduplicator();

        GuardianResult::create([
            'check_class' => 'DiskSpaceCheck',
            'status' => 'critical',
            'message' => 'Disk full',
            'notified_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
        ]);

        $result = new CheckResult(Status::Critical, 'Disk full');
        $this->assertFalse($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_allows_notification_after_window_expires(): void
    {
        $dedup = new Deduplicator();

        GuardianResult::create([
            'check_class' => 'DiskSpaceCheck',
            'status' => 'critical',
            'message' => 'Disk full',
            'notified_at' => now()->subMinutes(120),
            'created_at' => now()->subMinutes(120),
        ]);

        $result = new CheckResult(Status::Critical, 'Disk full');
        $this->assertTrue($dedup->shouldNotify('DiskSpaceCheck', $result));
    }

    public function test_it_always_allows_ok_status(): void
    {
        $dedup = new Deduplicator();
        $result = new CheckResult(Status::Ok, 'All good');

        $this->assertFalse($dedup->shouldNotify('DiskSpaceCheck', $result));
    }
}
