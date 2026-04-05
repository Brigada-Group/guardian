@extends('guardian::guardian.layout')

@section('page-title', 'Requests')

@section('content')
<div x-data="requestsPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <select class="gd-btn" x-model="filters.method" @change="fetchData()">
                <option value="">All Methods</option>
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="PATCH">PATCH</option>
                <option value="DELETE">DELETE</option>
            </select>
            <label style="display:flex; align-items:center; gap:6px; font-size:13px;">
                <input type="checkbox" x-model="filters.slow_only" @change="fetchData()"> Slow only
            </label>
        </div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading request data...</div>
    </template>

    <div x-show="data" x-cloak>
        <div>
            <!-- Histogram -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Response Time Distribution</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="histogramChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Slowest Endpoints (avg)</div>
                    <div class="gd-card__body" style="padding:0; max-height:280px; overflow-y:auto;">
                        <table class="gd-table">
                            <thead><tr><th>Route</th><th>Avg Time</th><th>Count</th></tr></thead>
                            <tbody>
                                <template x-for="ep in data.slowest" :key="ep.route_name">
                                    <tr>
                                        <td class="gd-mono gd-truncate" x-text="ep.route_name"></td>
                                        <td x-text="formatMs(ep.avg_ms)"></td>
                                        <td x-text="ep.count"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Log table -->
            <div class="gd-card">
                <div class="gd-card__header">Request Log</div>
                <div class="gd-card__body" style="padding:0">
                    <div class="gd-table-wrapper">
                        <table class="gd-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Method</th>
                                    <th>URL</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Route</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs.data || [])" :key="log.id">
                                    <tr>
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td><span class="gd-badge gd-badge--info" x-text="log.method"></span></td>
                                        <td class="gd-mono gd-truncate" x-text="log.url"></td>
                                        <td>
                                            <span class="gd-badge" :class="log.status_code >= 500 ? 'gd-badge--danger' : log.status_code >= 400 ? 'gd-badge--warning' : 'gd-badge--ok'" x-text="log.status_code"></span>
                                        </td>
                                        <td x-text="formatMs(log.duration_ms)"></td>
                                        <td class="gd-mono gd-truncate" x-text="log.route_name || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <template x-if="data.logs.last_page > 1">
                        <div class="gd-pagination">
                            <span x-text="`Showing ${data.logs.from}-${data.logs.to} of ${data.logs.total}`"></span>
                            <div class="gd-pagination__links">
                                <button class="gd-pagination__link" :disabled="!data.logs.prev_page_url" @click="goToPage(data.logs.current_page - 1)">Prev</button>
                                <button class="gd-pagination__link" :disabled="!data.logs.next_page_url" @click="goToPage(data.logs.current_page + 1)">Next</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function requestsPage() {
    return {
        loading: true,
        data: null,
        dateRange: '24h',
        filters: { method: '', slow_only: false },
        page: 1,
        pollInterval: {{ $pollInterval }} * 1000,
        charts: {},

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        setDateRange(range) {
            this.dateRange = range;
            this.page = 1;
            this.fetchData();
        },

        async fetchData() {
            this.loading = true;
            try {
                const params = { ...dateRangeToParams(this.dateRange), page: this.page };
                if (this.filters.method) params.method = this.filters.method;
                if (this.filters.slow_only) params.slow_only = 1;
                const res = await guardianFetch('{{ route("guardian.api.requests") }}', params);
                this.data = res.data;
                this.$nextTick(() => this.renderCharts());
            } catch (e) { console.error('Requests fetch failed', e); }
            this.loading = false;
        },

        goToPage(p) { this.page = p; this.fetchData(); },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        renderCharts() {
            const isDark = document.documentElement.classList.contains('gd-dark');
            const colors = getChartColors(isDark);
            if (this.charts.histogram) this.charts.histogram.destroy();
            const ctx = this.$refs.histogramChart;
            if (ctx && this.data.histogram) {
                const h = this.data.histogram;
                this.charts.histogram = SafeChart(ctx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(h),
                        datasets: [{
                            data: Object.values(h),
                            backgroundColor: [colors.green + '80', colors.blue + '80', colors.yellow + '80', colors.red + '60', colors.red + 'cc'],
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        scales: {
                            x: { grid: { display: false }, ticks: { color: colors.text } },
                            y: { grid: { color: colors.grid }, ticks: { color: colors.text }, beginAtZero: true }
                        }
                    }
                });
            }
        },
    };
}
</script>
@endsection
