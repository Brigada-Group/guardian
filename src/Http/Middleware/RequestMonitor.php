<?php

namespace Brigada\Guardian\Http\Middleware;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequestMonitor
{
    use SendsDiscordAlerts;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $statusCode = $response->getStatusCode();

        $this->logRequest($request, $durationMs, $statusCode);
        $this->checkThresholds($request, $durationMs, $statusCode);

        return $response;
    }

    private function logRequest(Request $request, float $durationMs, int $statusCode): void
    {
        try {
            RequestLog::create([
                'method' => $request->method(),
                'uri' => mb_substr($request->path(), 0, 2048),
                'route_name' => $request->route()?->getName(),
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Don't let monitoring break the app
        }
    }

    private function checkThresholds(Request $request, float $durationMs, int $statusCode): void
    {
        $slowThreshold = config('guardian.monitoring.requests.slow_threshold_ms', 5000);
        $errorRateThreshold = config('guardian.monitoring.requests.error_rate_threshold', 50);
        $errorRateWindow = config('guardian.monitoring.requests.error_rate_window_minutes', 5);

        // Alert on slow requests
        if ($durationMs >= $slowThreshold) {
            $this->sendAlert(
                'Slow Request',
                "{$request->method()} {$request->path()} took {$durationMs}ms (threshold: {$slowThreshold}ms)",
                Status::Warning,
                ['duration_ms' => $durationMs, 'uri' => $request->path(), 'method' => $request->method()],
            );
        }

        // Alert on server errors
        if ($statusCode >= 500) {
            $recentErrors = RequestLog::where('status_code', '>=', 500)
                ->where('created_at', '>=', now()->subMinutes($errorRateWindow))
                ->count();

            if ($recentErrors >= $errorRateThreshold) {
                $this->sendAlert(
                    'High Error Rate',
                    "{$recentErrors} server errors in the last {$errorRateWindow} minutes",
                    Status::Critical,
                    ['error_count' => $recentErrors, 'window_minutes' => $errorRateWindow],
                );
            }
        }
    }
}
