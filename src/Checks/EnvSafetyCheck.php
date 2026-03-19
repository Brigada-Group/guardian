<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class EnvSafetyCheck implements HealthCheck
{
    public function name(): string { return '.env Safety'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        $issues = [];
        if (config('app.debug') === true) { $issues[] = 'APP_DEBUG is enabled'; }
        if (config('app.env') !== 'production') { $issues[] = 'APP_ENV is not "production" (is "' . config('app.env') . '")'; }
        if (config('app.key') === '' || config('app.key') === null) { $issues[] = 'APP_KEY is not set'; }
        if (config('session.driver') === 'array') { $issues[] = 'Session driver is "array" (non-persistent)'; }
        if (! empty($issues)) {
            return new CheckResult(Status::Critical, implode('; ', $issues), ['issues' => $issues]);
        }
        return new CheckResult(Status::Ok, 'APP_DEBUG=false, APP_ENV=production');
    }
}
