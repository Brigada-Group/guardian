<?php

namespace Brigada\Guardian\Tests\Unit\Listeners;

use Brigada\Guardian\Listeners\QueryListener;
use Brigada\Guardian\Models\QueryLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QueryListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_slow_queries(): void
    {
        config()->set('guardian.monitoring.queries.slow_threshold_ms', 100);

        $listener = new QueryListener();

        $event = new QueryExecuted(
            'SELECT * FROM users WHERE id = 1',
            [],
            150.0,
            $this->app['db']->connection(),
        );

        $listener->handle($event);

        $this->assertDatabaseHas('guardian_query_logs', [
            'is_slow' => true,
            'connection' => 'testing',
        ]);
    }

    public function test_it_skips_fast_queries(): void
    {
        config()->set('guardian.monitoring.queries.slow_threshold_ms', 500);

        $listener = new QueryListener();

        $event = new QueryExecuted(
            'SELECT 1',
            [],
            1.0,
            $this->app['db']->connection(),
        );

        $listener->handle($event);

        $this->assertDatabaseCount('guardian_query_logs', 0);
    }

    public function test_it_detects_n_plus_one(): void
    {
        config()->set('guardian.monitoring.queries.n_plus_one_threshold', 3);
        config()->set('guardian.monitoring.queries.slow_threshold_ms', 99999);

        $listener = new QueryListener();

        // Simulate N+1: same query pattern repeated
        for ($i = 0; $i < 3; $i++) {
            $event = new QueryExecuted(
                "SELECT * FROM posts WHERE user_id = {$i}",
                [],
                1.0,
                $this->app['db']->connection(),
            );
            $listener->handle($event);
        }

        $this->assertDatabaseHas('guardian_query_logs', [
            'is_n_plus_one' => true,
        ]);
    }
}
