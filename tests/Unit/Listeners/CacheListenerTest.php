<?php

namespace Brigada\Guardian\Tests\Unit\Listeners;

use Brigada\Guardian\Listeners\CacheListener;
use Brigada\Guardian\Models\CacheLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CacheListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_cache_events(): void
    {
        $listener = $this->app->make(CacheListener::class);

        // Simulate cache events
        $listener->handleHit(new CacheHit('default', 'key1', 'value'));
        $listener->handleHit(new CacheHit('default', 'key2', 'value'));
        $listener->handleMiss(new CacheMissed('default', 'key3'));
        $listener->handleWrite(new KeyWritten('default', 'key3', 'value', 60));

        // Flush aggregated data
        $listener->flush();

        $log = CacheLog::first();

        $this->assertNotNull($log);
        $this->assertEquals(2, $log->hits);
        $this->assertEquals(1, $log->misses);
        $this->assertEquals(1, $log->writes);
        $this->assertEqualsWithDelta(66.67, $log->hit_rate, 0.01);
    }
}
