<?php

namespace Brigada\Guardian\Contracts;

use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Results\CheckResult;

interface HealthCheck
{
    public function name(): string;

    public function schedule(): Schedule;

    public function run(): CheckResult;
}
