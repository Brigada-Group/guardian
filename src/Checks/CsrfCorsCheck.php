<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class CsrfCorsCheck implements HealthCheck
{
    public function name(): string { return 'CSRF/CORS'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        $issues = [];
        $corsConfig = config('cors');
        if ($corsConfig) {
            $allowedOrigins = $corsConfig['allowed_origins'] ?? [];
            if (in_array('*', $allowedOrigins)) { $issues[] = 'CORS allows all origins (wildcard *)'; }
            $allowedMethods = $corsConfig['allowed_methods'] ?? [];
            if (in_array('*', $allowedMethods)) { $issues[] = 'CORS allows all HTTP methods (wildcard *)'; }
        }
        try {
            $csrfMiddleware = app(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
            $reflection = new \ReflectionClass($csrfMiddleware);
            if ($reflection->hasProperty('except')) {
                $prop = $reflection->getProperty('except');
                $prop->setAccessible(true);
                $except = $prop->getValue($csrfMiddleware);
                if (in_array('*', $except)) { $issues[] = 'CSRF protection is disabled for all routes'; }
                elseif (count($except) > 10) { $issues[] = 'CSRF protection excluded for ' . count($except) . ' routes'; }
            }
        } catch (\Throwable $e) { /* Middleware may not be resolvable */ }
        if (! empty($issues)) {
            return new CheckResult(Status::Warning, implode('; ', $issues), ['issues' => $issues]);
        }
        return new CheckResult(Status::Ok, 'CSRF/CORS configuration looks secure');
    }
}
