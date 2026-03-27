<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\CacheLog;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

class CacheListener
{
    use SendsDiscordAlerts;

    /** @var array<string, array{hits: int, misses: int, writes: int, forgets: int}> */
    private static array $counters = [];

    private static ?string $periodKey = null;

    public function handleHit(CacheHit $event): void
    {
        $this->increment($event->storeName ?? 'default', 'hits');
    }

    public function handleMiss(CacheMissed $event): void
    {
        $this->increment($event->storeName ?? 'default', 'misses');
    }

    public function handleWrite(KeyWritten $event): void
    {
        $this->increment($event->storeName ?? 'default', 'writes');
    }

    public function handleForget(KeyForgotten $event): void
    {
        $this->increment($event->storeName ?? 'default', 'forgets');
    }

    private function increment(string $store, string $type): void
    {
        // Aggregate per-minute periods
        $currentPeriod = now()->format('Y-m-d H:i');

        if (self::$periodKey !== $currentPeriod) {
            $this->flush();
            self::$periodKey = $currentPeriod;
            self::$counters = [];
        }

        if (! isset(self::$counters[$store])) {
            self::$counters[$store] = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'forgets' => 0];
        }

        self::$counters[$store][$type]++;
    }

    public function flush(): void
    {
        foreach (self::$counters as $store => $counts) {
            $total = $counts['hits'] + $counts['misses'];
            $hitRate = $total > 0 ? round(($counts['hits'] / $total) * 100, 2) : null;

            try {
                CacheLog::create([
                    'store' => $store,
                    'hits' => $counts['hits'],
                    'misses' => $counts['misses'],
                    'writes' => $counts['writes'],
                    'forgets' => $counts['forgets'],
                    'hit_rate' => $hitRate,
                    'period_start' => now()->startOfMinute(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Don't break the app
            }

            // Alert on low hit rate
            $threshold = config('guardian.monitoring.cache.low_hit_rate_threshold', 50);
            if ($hitRate !== null && $hitRate < $threshold && $total >= 100) {
                $this->sendAlert(
                    'Low Cache Hit Rate',
                    "Cache store [{$store}] hit rate is {$hitRate}% (threshold: {$threshold}%, sample: {$total})",
                    Status::Warning,
                    ['store' => $store, 'hit_rate' => $hitRate, 'total' => $total],
                );
            }
        }

        self::$counters = [];
    }

    public function __destruct()
    {
        $this->flush();
    }
}
