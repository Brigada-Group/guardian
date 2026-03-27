<?php

namespace Brigada\Guardian\Tests\Feature;

use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Models\RequestLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_old_records(): void
    {
        // Create old and new records
        GuardianResult::create([
            'check_class' => 'OldCheck',
            'status' => 'ok',
            'message' => 'Old result',
            'created_at' => now()->subDays(60),
        ]);

        GuardianResult::create([
            'check_class' => 'NewCheck',
            'status' => 'ok',
            'message' => 'Recent result',
            'created_at' => now(),
        ]);

        $this->artisan('guardian:prune')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('guardian_results', ['check_class' => 'OldCheck']);
        $this->assertDatabaseHas('guardian_results', ['check_class' => 'NewCheck']);
    }

    public function test_dry_run_does_not_delete(): void
    {
        GuardianResult::create([
            'check_class' => 'OldCheck',
            'status' => 'ok',
            'message' => 'Old result',
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('guardian:prune', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('guardian_results', ['check_class' => 'OldCheck']);
    }

    public function test_custom_days_override(): void
    {
        GuardianResult::create([
            'check_class' => 'RecentCheck',
            'status' => 'ok',
            'message' => 'Recent result',
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('guardian:prune', ['--days' => 3])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('guardian_results', ['check_class' => 'RecentCheck']);
    }
}
