@extends('guardian::guardian.layout')

@section('page-title', 'Overview')

@section('content')
<div x-data="overviewPage()" x-init="init()">
    <!-- Loading skeleton -->
    <div x-show="loading && !loaded" class="gd-loading gd-fade-in">
        <div class="gd-skeleton-grid">
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
        </div>
        <div class="gd-grid gd-grid--2">
            <div class="gd-skeleton gd-skeleton--chart"></div>
            <div class="gd-skeleton gd-skeleton--chart"></div>
        </div>
    </div>

    <div x-show="loaded" x-cloak class="gd-fade-in">
            <!-- Metric cards -->
            <div class="gd-metrics">
                <div class="gd-stat-card">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-accent)"></span>
                        <span class="gd-stat-card__label">Total Requests (24h)</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="Number(data?.metrics?.totalRequests).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data?.metrics?.errorRate > 5 }">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-danger)"></span>
                        <span class="gd-stat-card__label">Error Rate</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="data?.metrics?.errorRate + '%'"></div>
                </div>
                <div class="gd-stat-card">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-info)"></span>
                        <span class="gd-stat-card__label">Avg Response Time</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="formatMs(data?.metrics?.avgResponseTime)"></div>
                </div>
                <div class="gd-stat-card">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-success)"></span>
                        <span class="gd-stat-card__label">Cache Hit Rate</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="data?.metrics?.cacheHitRate + '%'"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data?.metrics?.failedCommands > 0 }">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-warning)"></span>
                        <span class="gd-stat-card__label">Failed Commands (24h)</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="data?.metrics?.failedCommands"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data?.metrics?.exceptionCount > 0 }">
                    <div class="gd-stat-card__header">
                        <span class="gd-stat-card__dot" style="background: var(--gd-danger)"></span>
                        <span class="gd-stat-card__label">Exceptions (24h)</span>
                    </div>
                    <div class="gd-stat-card__value" x-text="data?.metrics?.exceptionCount"></div>
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
                    <template x-if="data?.recent_alerts.length === 0">
                        <div class="gd-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <div class="gd-empty__text">No recent alerts</div>
                            <div class="gd-empty__hint">Alerts will appear here when thresholds are exceeded</div>
                        </div>
                    </template>
                    <template x-if="data?.recent_alerts.length > 0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="alert in data?.recent_alerts" :key="alert.notified_at">
                                        <tr>
                                            <td class="gd-mono" x-text="alert.check_class"></td>
                                            <td>
                                                <span class="gd-status-dot" :class="'gd-status-dot--' + alert.status"></span>
                                            </td>
                                            <td x-text="alert.message"></td>
                                            <td style="white-space:nowrap; color: var(--gd-text-secondary); font-size: 12px;" x-text="formatDate(alert.notified_at)"></td>
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
                    <div class="gd-card__body">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 32px;">
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Slow Request</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.slow_request_ms + 'ms'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Error Rate Alert</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.error_rate_threshold + ' errors'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Slow Query</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.slow_query_ms + 'ms'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">N+1 Detection</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.n_plus_one_threshold + ' repeats'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Slow HTTP Call</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.slow_http_ms + 'ms'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Slow Command</span>
                                <span style="font-size:13px; font-weight:600" x-text="(data?.thresholds?.slow_command_ms / 1000) + 's'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Slow Scheduled Task</span>
                                <span style="font-size:13px; font-weight:600" x-text="(data?.thresholds?.slow_task_ms / 1000) + 's'"></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--gd-border);">
                                <span style="font-size:13px; color:var(--gd-text-secondary)">Low Cache Hit Rate</span>
                                <span style="font-size:13px; font-weight:600" x-text="data?.thresholds?.low_cache_hit_rate + '%'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function overviewPage() {
    return {
        loading: true,
        loaded: false,
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
                this.data = res.data;
                this.loaded = true;
                this.$nextTick(() => this.renderCharts());
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
            const rtLabels = (this.data?.response_time_chart || []).map(d => formatDateShort(d.hour));
            const rtData = (this.data?.response_time_chart || []).map(d => Math.round(d.avg_ms));
            const errLabels = (this.data?.error_chart || []).map(d => formatDateShort(d.hour));
            const errData = (this.data?.error_chart || []).map(d => d.count);

            // Update existing charts if they exist (no destroy/recreate)
            if (this.charts.responseTime) {
                this.charts.responseTime.data.labels = rtLabels;
                this.charts.responseTime.data.datasets[0].data = rtData;
                this.charts.responseTime.update('none');
            }
            if (this.charts.error) {
                this.charts.error.data.labels = errLabels;
                this.charts.error.data.datasets[0].data = errData;
                this.charts.error.update('none');
            }
            if (this.charts.responseTime && this.charts.error) return;

            // First render only — create charts
            const rtCtx = this.$refs.responseTimeChart;
            if (rtCtx && !this.charts.responseTime) {
                const rtGradient = createChartGradient(rtCtx, colors.blue, 260);
                this.charts.responseTime = SafeChart(rtCtx, {
                    type: 'line',
                    data: {
                        labels: rtLabels,
                        datasets: [{
                            label: 'Avg Response Time (ms)',
                            data: rtData,
                            borderColor: colors.blue,
                            backgroundColor: rtGradient,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            pointHoverBackgroundColor: colors.blue,
                            pointHoverBorderColor: '#fff',
                            pointHoverBorderWidth: 2,
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            x: {
                                grid: { color: colors.grid, drawBorder: false, borderDash: [3, 3] },
                                ticks: { color: colors.text, font: { size: 10 } }
                            },
                            y: {
                                grid: { color: colors.grid, drawBorder: false, borderDash: [3, 3] },
                                ticks: { color: colors.text, callback: v => v + 'ms', font: { size: 10 } },
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: true, position: 'top', align: 'start',
                                labels: { color: colors.text, usePointStyle: true, pointStyle: 'circle', boxWidth: 6, font: { size: 11 } }
                            }
                        }
                    }
                });
            }

            // Error chart — bar with rounded corners
            const errCtx = this.$refs.errorChart;
            if (errCtx && !this.charts.error) {
                this.charts.error = SafeChart(errCtx, {
                    type: 'bar',
                    data: {
                        labels: errLabels,
                        datasets: [{
                            label: 'Errors',
                            data: errData,
                            backgroundColor: colors.red + '60',
                            hoverBackgroundColor: colors.red + '90',
                            borderColor: colors.red,
                            borderWidth: 0,
                            borderRadius: 4,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: colors.text, font: { size: 10 } }
                            },
                            y: {
                                grid: { color: colors.grid, drawBorder: false, borderDash: [3, 3] },
                                ticks: { color: colors.text, stepSize: 1, font: { size: 10 } },
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: true, position: 'top', align: 'start',
                                labels: { color: colors.text, usePointStyle: true, pointStyle: 'circle', boxWidth: 6, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        },
    };
}
</script>
@endsection
