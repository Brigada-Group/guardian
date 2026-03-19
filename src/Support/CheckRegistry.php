<?php

namespace Brigada\Guardian\Support;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;

class CheckRegistry
{
    /** @var HealthCheck[] */
    private array $checks = [];

    public function register(HealthCheck $check): void
    {
        $this->checks[] = $check;
    }

    /** @return HealthCheck[] */
    public function forSchedule(Schedule $schedule): array
    {
        return array_values(
            array_filter($this->checks, fn (HealthCheck $c) => $c->schedule() === $schedule)
        );
    }

    /** @return HealthCheck[] */
    public function all(): array
    {
        return $this->checks;
    }
}
