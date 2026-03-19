<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class HorizonStatusCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Horizon';
    }

    public function schedule(): Schedule
    {
        return Schedule::Hourly;
    }

    public function run(): CheckResult
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return new CheckResult(Status::Ok, 'Horizon not installed', ['installed' => false]);
        }

        try {
            $status = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

            if (empty($status)) {
                return new CheckResult(Status::Critical, 'Horizon is not running');
            }

            foreach ($status as $supervisor) {
                if ($supervisor->status === 'paused') {
                    return new CheckResult(Status::Warning, 'Horizon is paused');
                }
            }

            return new CheckResult(Status::Ok, 'Horizon is running');
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "Horizon check failed: {$e->getMessage()}");
        }
    }
}
