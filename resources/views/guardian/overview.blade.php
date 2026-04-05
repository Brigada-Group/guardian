@extends('guardian::guardian.layout')

@section('page-title', 'Overview')

@section('content')
<div x-data="overviewPage()" x-init="init()">
    <!-- Loading -->
    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading overview data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Metric cards -->
            <div class="gd-metrics">
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Total Requests (24h)</div>
                    <div class="gd-metric-card__value" x-text="Number(data.metrics.totalRequests).toLocaleString()"></div>
                </div>
                <div class="gd-metric-card" :class="{ 'gd-metric-card--alert': data.metrics.errorRate > 5 }">
                    <div class="gd-metric-card__label">Error Rate</div>
                    <div class="gd-metric-card__value" x-text="data.metrics.errorRate + '%'"></div>
                </div>
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Avg Response Time</div>
                    <div class="gd-metric-card__value" x-text="formatMs(data.metrics.avgResponseTime)"></div>
                </div>
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Cache Hit Rate</div>
                    <div class="gd-metric-card__value" x-text="data.metrics.cacheHitRate + '%'"></div>
                </div>
                <div class="gd-metric-card" :class="{ 'gd-metric-card--alert': data.metrics.failedCommands > 0 }">
                    <div class="gd-metric-card__label">Failed Commands (24h)</div>
                    <div class="gd-metric-card__value" x-text="data.metrics.failedCommands"></div>
                </div>
                <div class="gd-metric-card" :class="{ 'gd-metric-card--alert': data.metrics.exceptionCount > 0 }">
                    <div class="gd-metric-card__label">Exceptions (24h)</div>
                    <div class="gd-metric-card__value" x-text="data.metrics.exceptionCount"></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Response Time (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="responseTimeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Errors (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="errorChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent alerts -->
            <div class="gd-card">
                <div class="gd-card__header">Recent Alerts</div>
                <div class="gd-card__body" style="padding:0">
                    <template x-if="data.recent_alerts.length === 0">
                        <div class="gd-empty">
                            <div class="gd-empty__text">No recent alerts</div>
                        </div>
                    </template>
                    <template x-if="data.recent_alerts.length > 0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Check</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Notified At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="alert in data.recent_alerts" :key="alert.notified_at">
                                        <tr>
                                            <td class="gd-mono" x-text="alert.check_class"></td>
                                            <td><span class="gd-badge" :class="statusBadgeClass(alert.status)" x-text="alert.status"></span></td>
                                            <td x-text="alert.message"></td>
                                            <td x-text="formatDate(alert.notified_at)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
            <!-- Configured Thresholds -->
            <template x-if="data.thresholds">
                <div class="gd-card">
                    <div class="gd-card__header">Configured Thresholds</div>
                    <div class="gd-card__body" style="padding:0">
                        <table class="gd-table">
                            <thead><tr><th>Setting</th><th>Value</th></tr></thead>
                            <tbody>
                                <tr><td>Slow Request</td><td x-text="data.thresholds.slow_request_ms + 'ms'"></td></tr>
                                <tr><td>Error Rate Alert</td><td x-text="data.thresholds.error_rate_threshold + ' errors'"></td></tr>
                                <tr><td>Slow Query</td><td x-text="data.thresholds.slow_query_ms + 'ms'"></td></tr>
                                <tr><td>N+1 Detection</td><td x-text="data.thresholds.n_plus_one_threshold + ' repeats'"></td></tr>
                                <tr><td>Slow HTTP Call</td><td x-text="data.thresholds.slow_http_ms + 'ms'"></td></tr>
                                <tr><td>Slow Command</td><td x-text="(data.thresholds.slow_command_ms / 1000) + 's'"></td></tr>
                                <tr><td>Slow Scheduled Task</td><td x-text="(data.thresholds.slow_task_ms / 1000) + 's'"></td></tr>
                                <tr><td>Low Cache Hit Rate</td><td x-text="data.thresholds.low_cache_hit_rate + '%'"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
@endsection

@section('scripts')
<script>
function overviewPage() {
    return {
        loading: true,
        data: null,
        pollInterval: {{ $pollInterval }} * 1000,
        pollTimer: null,
        charts: {},

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        async fetchData() {
            this.loading = true;
            try {
                const res = await guardianFetch('{{ route("guardian.api.overview") }}');
                destroyAllCharts(this.charts);
                this.data = res.data;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Overview fetch failed', e); }
            this.loading = false;
        },

        startPolling() {
            this.pollTimer = setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) {
                    this.fetchData();
                }
            }, this.pollInterval);
        },

        renderCharts() {
            const isDark = document.documentElement.classList.contains('gd-dark');
            const colors = getChartColors(isDark);

            // Response time chart
            const rtCtx = this.$refs.responseTimeChart;
            if (rtCtx) {
                this.charts.responseTime = SafeChart(rtCtx, {
                    type: 'line',
                    data: {
                        labels: (this.data.response_time_chart || []).map(d => formatDateShort(d.hour)),
                        datasets: [{
                            label: 'Avg Response Time (ms)',
                            data: (this.data.response_time_chart || []).map(d => Math.round(d.avg_ms)),
                            borderColor: colors.blue,
                            backgroundColor: colors.blue + '20',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                        }]
                    },
                    options: {
                        scales: {
                            x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                            y: { grid: { color: colors.grid }, ticks: { color: colors.text, callback: v => v + 'ms' }, beginAtZero: true }
                        }
                    }
                });
            }

            // Error chart
            const errCtx = this.$refs.errorChart;
            if (errCtx) {
                this.charts.error = SafeChart(errCtx, {
                    type: 'bar',
                    data: {
                        labels: (this.data.error_chart || []).map(d => formatDateShort(d.hour)),
                        datasets: [{
                            label: 'Errors',
                            data: (this.data.error_chart || []).map(d => d.count),
                            backgroundColor: colors.red + '80',
                            borderColor: colors.red,
                            borderWidth: 1,
                            borderRadius: 3,
                        }]
                    },
                    options: {
                        scales: {
                            x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                            y: { grid: { color: colors.grid }, ticks: { color: colors.text, stepSize: 1 }, beginAtZero: true }
                        }
                    }
                });
            }
        },
    };
}
</script>
@endsection
