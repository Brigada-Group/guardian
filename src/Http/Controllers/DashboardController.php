<?php

namespace Brigada\Guardian\Http\Controllers;

use Brigada\Guardian\Models\CacheLog;
use Brigada\Guardian\Models\CommandLog;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Models\MailLog;
use Brigada\Guardian\Models\NotificationLog;
use Brigada\Guardian\Models\OutgoingHttpLog;
use Brigada\Guardian\Models\QueryLog;
use Brigada\Guardian\Models\RequestLog;
use Brigada\Guardian\Models\ScheduledTaskLog;
use Brigada\Guardian\Support\CheckRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    // --- Page methods (each returns a view) ---

    public function overview()
    {
        return view('guardian::guardian.overview', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function requests()
    {
        return view('guardian::guardian.requests', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function queries()
    {
        return view('guardian::guardian.queries', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function outgoingHttp()
    {
        return view('guardian::guardian.outgoing-http', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function jobs()
    {
        return view('guardian::guardian.jobs', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function mail()
    {
        return view('guardian::guardian.mail', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function notifications()
    {
        return view('guardian::guardian.notifications', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function cache()
    {
        return view('guardian::guardian.cache', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function exceptions()
    {
        return view('guardian::guardian.exceptions', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function alerts()
    {
        return view('guardian::guardian.alerts', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    public function health()
    {
        return view('guardian::guardian.health', [
            'pollInterval' => config('guardian.dashboard.poll_interval', 30),
        ]);
    }

    // --- API methods ---

    public function apiOverview(): JsonResponse
    {
        try {
            $since24h = now()->subHours(24);
            $perPage = config('guardian.dashboard.per_page', 50);

            $totalRequests = RequestLog::where('created_at', '>=', $since24h)->count();
            $errorCount = RequestLog::where('created_at', '>=', $since24h)->where('status_code', '>=', 500)->count();
            $errorRate = $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0;
            $avgResponseTime = round((float) (RequestLog::where('created_at', '>=', $since24h)->avg('duration_ms') ?? 0), 2);

            $latestCache = CacheLog::latest('created_at')->first();
            $cacheHitRate = round((float) ($latestCache?->hit_rate ?? 0), 2);

            $failedCommands = CommandLog::where('created_at', '>=', $since24h)->where('exit_code', '!=', 0)->count();
            $exceptionCount = GuardianResult::where('created_at', '>=', $since24h)->where('check_class', 'like', 'exception:%')->count();

            // Hourly response time chart
            $h = $this->hourGroupExpr();
            $responseTimeChart = RequestLog::where('created_at', '>=', $since24h)
                ->selectRaw("{$h['select']}, AVG(duration_ms) as avg_ms, COUNT(*) as count")
                ->groupByRaw($h['group'])
                ->orderBy('hour')
                ->take(24)
                ->get();

            // Hourly error chart
            $errorChart = RequestLog::where('created_at', '>=', $since24h)
                ->where('status_code', '>=', 500)
                ->selectRaw("{$h['select']}, COUNT(*) as count")
                ->groupByRaw($h['group'])
                ->orderBy('hour')
                ->take(24)
                ->get();

            // Recent alerts
            $recentAlerts = GuardianResult::whereNotNull('notified_at')
                ->latest('notified_at')
                ->take(10)
                ->get(['check_class', 'status', 'message', 'notified_at']);

            $thresholds = [
                'slow_request_ms' => config('guardian.monitoring.requests.slow_threshold_ms', 5000),
                'error_rate_threshold' => config('guardian.monitoring.requests.error_rate_threshold', 50),
                'slow_query_ms' => config('guardian.monitoring.queries.slow_threshold_ms', 500),
                'n_plus_one_threshold' => config('guardian.monitoring.queries.n_plus_one_threshold', 10),
                'slow_http_ms' => config('guardian.monitoring.outgoing_http.slow_threshold_ms', 10000),
                'slow_command_ms' => config('guardian.monitoring.commands.slow_threshold_ms', 60000),
                'slow_task_ms' => config('guardian.monitoring.scheduled_tasks.slow_threshold_ms', 300000),
                'low_cache_hit_rate' => config('guardian.monitoring.cache.low_hit_rate_threshold', 50),
            ];

            return $this->apiResponse([
                'metrics' => compact('totalRequests', 'errorRate', 'avgResponseTime', 'cacheHitRate', 'failedCommands', 'exceptionCount'),
                'thresholds' => $thresholds,
                'response_time_chart' => $responseTimeChart,
                'error_chart' => $errorChart,
                'recent_alerts' => $recentAlerts,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load overview data.');
        }
    }

    public function apiRequests(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $query = RequestLog::query();

            if ($method = $request->query('method')) {
                $query->where('method', $method);
            }
            if ($statusMin = $request->query('status_min')) {
                $query->where('status_code', '>=', (int) $statusMin);
            }
            if ($statusMax = $request->query('status_max')) {
                $query->where('status_code', '<=', (int) $statusMax);
            }
            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }
            if ($request->boolean('slow_only')) {
                $threshold = config('guardian.monitoring.requests.slow_threshold_ms', 5000);
                $query->where('duration_ms', '>=', $threshold);
            }

            $logs = (clone $query)->latest('created_at')->paginate($perPage);

            // Slowest endpoints
            $slowest = (clone $query)->selectRaw('route_name, AVG(duration_ms) as avg_ms, COUNT(*) as count')
                ->whereNotNull('route_name')
                ->groupBy('route_name')
                ->orderByDesc('avg_ms')
                ->take(20)
                ->get();

            // Histogram buckets
            $histogram = [
                '0-100ms' => (clone $query)->where('duration_ms', '<', 100)->count(),
                '100-500ms' => (clone $query)->whereBetween('duration_ms', [100, 500])->count(),
                '500ms-1s' => (clone $query)->whereBetween('duration_ms', [500, 1000])->count(),
                '1-5s' => (clone $query)->whereBetween('duration_ms', [1000, 5000])->count(),
                '5s+' => (clone $query)->where('duration_ms', '>=', 5000)->count(),
            ];

            return $this->apiResponse([
                'logs' => $logs,
                'slowest' => $slowest,
                'histogram' => $histogram,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load request data.');
        }
    }

    public function apiQueries(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $tab = $request->query('tab', 'all');
            $query = QueryLog::query();

            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }

            if ($tab === 'slow') {
                $query->where('is_slow', true);
            } elseif ($tab === 'n_plus_one') {
                $query->where('is_n_plus_one', true);
            }

            $logs = (clone $query)->latest('created_at')->paginate($perPage);

            // Slow query trend (hourly)
            $since24h = now()->subHours(24);
            $h = $this->hourGroupExpr();
            $trend = QueryLog::where('created_at', '>=', $since24h)
                ->where('is_slow', true)
                ->selectRaw("{$h['select']}, COUNT(*) as count")
                ->groupByRaw($h['group'])
                ->orderBy('hour')
                ->take(24)
                ->get();

            return $this->apiResponse([
                'logs' => $logs,
                'trend' => $trend,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load query data.');
        }
    }

    public function apiOutgoingHttp(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $query = OutgoingHttpLog::query();

            if ($host = $request->query('host')) {
                $query->where('host', $host);
            }
            if ($request->boolean('failed_only')) {
                $query->where('failed', true);
            }
            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }

            $logs = (clone $query)->latest('created_at')->paginate($perPage);

            // Performance by host
            $byHost = OutgoingHttpLog::selectRaw('host, AVG(duration_ms) as avg_ms, COUNT(*) as count, SUM(CASE WHEN failed = 1 THEN 1 ELSE 0 END) as failures')
                ->groupBy('host')
                ->orderByDesc('count')
                ->take(10)
                ->get();

            return $this->apiResponse([
                'logs' => $logs,
                'by_host' => $byHost,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load outgoing HTTP data.');
        }
    }

    public function apiJobs(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $tab = $request->query('tab', 'commands');

            if ($tab === 'scheduled') {
                $logs = ScheduledTaskLog::latest('created_at')->paginate($perPage);
                $statusBreakdown = ScheduledTaskLog::selectRaw("status, COUNT(*) as count")->groupBy('status')->get();

                return $this->apiResponse(['logs' => $logs, 'status_breakdown' => $statusBreakdown]);
            }

            $logs = CommandLog::latest('created_at')->paginate($perPage);
            $exitCodes = CommandLog::selectRaw("exit_code, COUNT(*) as count")->groupBy('exit_code')->get();

            return $this->apiResponse(['logs' => $logs, 'exit_codes' => $exitCodes]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load jobs data.');
        }
    }

    public function apiMail(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $query = MailLog::query();

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }
            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }

            $logs = (clone $query)->latest('created_at')->paginate($perPage);

            // Daily chart
            $d = $this->dayGroupExpr();
            $dailyChart = MailLog::selectRaw("{$d['select']}, status, COUNT(*) as count")
                ->where('created_at', '>=', now()->subDays(30))
                ->groupByRaw("{$d['group']}, status")
                ->orderBy('day')
                ->take(60)
                ->get();

            return $this->apiResponse(['logs' => $logs, 'daily_chart' => $dailyChart]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load mail data.');
        }
    }

    public function apiNotifications(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $query = NotificationLog::query();

            if ($channel = $request->query('channel')) {
                $query->where('channel', $channel);
            }
            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            $logs = (clone $query)->latest('created_at')->paginate($perPage);

            $byChannel = NotificationLog::selectRaw("channel, COUNT(*) as count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failures")
                ->groupBy('channel')
                ->get();

            return $this->apiResponse(['logs' => $logs, 'by_channel' => $byChannel]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load notification data.');
        }
    }

    public function apiCache(Request $request): JsonResponse
    {
        try {
            $query = CacheLog::query();
            if ($store = $request->query('store')) {
                $query->where('store', $store);
            }

            $logs = (clone $query)->latest('created_at')->take(200)->get();

            // Current hit rate
            $latest = CacheLog::latest('created_at')->first();

            // Per-store breakdown
            $byStore = CacheLog::selectRaw("store, AVG(hit_rate) as avg_hit_rate, SUM(hits) as total_hits, SUM(misses) as total_misses, SUM(writes) as total_writes")
                ->where('created_at', '>=', now()->subHours(24))
                ->groupBy('store')
                ->get();

            return $this->apiResponse([
                'logs' => $logs,
                'current_hit_rate' => $latest?->hit_rate ?? 0,
                'by_store' => $byStore,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load cache data.');
        }
    }

    public function apiExceptions(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);

            $query = GuardianResult::where('check_class', 'like', 'exception:%');
            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }

            // Grouped exceptions
            $grouped = (clone $query)
                ->selectRaw("check_class, COUNT(*) as occurrence_count, MAX(created_at) as last_seen, MAX(message) as message")
                ->groupBy('check_class')
                ->orderByDesc('last_seen')
                ->paginate($perPage);

            // Trend (hourly, last 48h)
            $h = $this->hourGroupExpr();
            $trend = GuardianResult::where('check_class', 'like', 'exception:%')
                ->where('created_at', '>=', now()->subHours(48))
                ->selectRaw("{$h['select']}, COUNT(*) as count")
                ->groupByRaw($h['group'])
                ->orderBy('hour')
                ->take(48)
                ->get();

            return $this->apiResponse(['grouped' => $grouped, 'trend' => $trend]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load exception data.');
        }
    }

    public function apiAlerts(Request $request): JsonResponse
    {
        try {
            $perPage = config('guardian.dashboard.per_page', 50);
            $query = GuardianResult::whereNotNull('notified_at');

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }
            if ($dateFrom = $request->query('date_from')) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo = $request->query('date_to')) {
                $query->where('created_at', '<=', $dateTo);
            }
            if ($type = $request->query('type')) {
                if ($type === 'exception') {
                    $query->where('check_class', 'like', 'exception:%');
                } elseif ($type === 'monitor') {
                    $query->where('check_class', 'like', 'monitor:%');
                } else {
                    $query->where('check_class', 'not like', 'exception:%')
                          ->where('check_class', 'not like', 'monitor:%');
                }
            }

            $alerts = $query->latest('notified_at')->paginate($perPage);

            // Summary counts
            $since24h = now()->subHours(24);
            $summary = [
                'total_24h' => GuardianResult::whereNotNull('notified_at')->where('notified_at', '>=', $since24h)->count(),
                'critical_24h' => GuardianResult::whereNotNull('notified_at')->where('notified_at', '>=', $since24h)->where('status', 'critical')->count(),
                'warning_24h' => GuardianResult::whereNotNull('notified_at')->where('notified_at', '>=', $since24h)->where('status', 'warning')->count(),
                'error_24h' => GuardianResult::whereNotNull('notified_at')->where('notified_at', '>=', $since24h)->where('status', 'error')->count(),
            ];

            return $this->apiResponse([
                'alerts' => $alerts,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load alerts data.');
        }
    }

    public function apiHealth(): JsonResponse
    {
        try {
            $registry = app(CheckRegistry::class);

            // Single query: get latest result per check_class
            $checkClasses = collect($registry->all())->map(fn ($c) => get_class($c))->all();
            $latestResults = GuardianResult::whereIn('check_class', $checkClasses)
                ->selectRaw('check_class, status, message, MAX(created_at) as created_at')
                ->groupBy('check_class', 'status', 'message')
                ->get()
                ->keyBy('check_class');

            $checks = [];
            foreach ($registry->all() as $check) {
                $latest = $latestResults->get(get_class($check));
                $checks[] = [
                    'name' => $check->name(),
                    'class' => get_class($check),
                    'schedule' => $check->schedule()->value,
                    'status' => $latest?->status ?? 'unknown',
                    'message' => $latest?->message ?? 'Never run',
                    'last_run' => $latest?->created_at,
                ];
            }

            return $this->apiResponse(['checks' => $checks]);
        } catch (\Throwable $e) {
            return $this->apiError('Failed to load health data.');
        }
    }

    public function apiHealthRun(string $check): JsonResponse
    {
        try {
            $registry = app(CheckRegistry::class);
            $found = null;
            foreach ($registry->all() as $registered) {
                if (class_basename(get_class($registered)) === $check) {
                    $found = $registered;
                    break;
                }
            }
            if (! $found) {
                return response()->json(['error' => "Check '{$check}' not found."], 404)->header('Cache-Control', 'no-store');
            }
            $result = $found->run();

            return $this->apiResponse([
                'name' => $found->name(),
                'status' => $result->status->value,
                'message' => $result->message,
                'metadata' => $result->metadata,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError("Failed to run check: {$e->getMessage()}");
        }
    }

    // --- Helpers ---

    private function hourGroupExpr(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver", 'mysql');

        if ($connection === 'sqlite') {
            $expr = "strftime('%Y-%m-%d %H:00', created_at)";
        } else {
            $expr = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
        }

        return ['select' => $expr . ' as hour', 'group' => $expr];
    }

    private function dayGroupExpr(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver", 'mysql');

        if ($connection === 'sqlite') {
            $expr = "strftime('%Y-%m-%d', created_at)";
        } else {
            $expr = "DATE_FORMAT(created_at, '%Y-%m-%d')";
        }

        return ['select' => $expr . ' as day', 'group' => $expr];
    }

    private function apiResponse(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'next_poll' => config('guardian.dashboard.poll_interval', 30),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function apiError(string $message): JsonResponse
    {
        return response()->json([
            'error' => $message,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'next_poll' => config('guardian.dashboard.poll_interval', 30),
            ],
        ], 500)->header('Cache-Control', 'no-store');
    }
}
