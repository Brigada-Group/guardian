@extends('guardian::guardian.layout')

@section('page-title', 'Outgoing HTTP')

@section('content')
<div x-data="outgoingHttpPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <label style="display:flex; align-items:center; gap:6px; font-size:13px;">
                <input type="checkbox" x-model="failedOnly" @change="fetchData()"> Failed only
            </label>
        </div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading outgoing HTTP data...</div>
    </template>

    <div x-show="data" x-cloak>
        <div>
            <!-- By host chart + table -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Performance by Host</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="hostChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Host Breakdown</div>
                    <div class="gd-card__body" style="padding:0; max-height:280px; overflow-y:auto;">
                        <table class="gd-table">
                            <thead><tr><th>Host</th><th>Avg Time</th><th>Requests</th><th>Failures</th></tr></thead>
                            <tbody>
                                <template x-for="h in (data.by_host || [])" :key="h.host">
                                    <tr>
                                        <td class="gd-mono" x-text="h.host"></td>
                                        <td x-text="formatMs(h.avg_ms)"></td>
                                        <td x-text="h.count"></td>
                                        <td>
                                            <span class="gd-badge" :class="h.failures > 0 ? 'gd-badge--danger' : 'gd-badge--ok'" x-text="h.failures"></span>
                                        </td>
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
                                    <th>Host</th>
                                    <th>URL</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs.data || [])" :key="log.id">
                                    <tr>
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td><span class="gd-badge gd-badge--info" x-text="log.method"></span></td>
                                        <td class="gd-mono" x-text="log.host"></td>
                                        <td class="gd-mono gd-truncate" x-text="log.url"></td>
                                        <td>
                                            <span class="gd-badge" :class="log.failed ? 'gd-badge--danger' : 'gd-badge--ok'" x-text="log.failed ? 'Failed' : (log.status_code || 'OK')"></span>
                                        </td>
                                        <td x-text="formatMs(log.duration_ms)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
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
function outgoingHttpPage() {
    return {
        loading: true,
        data: null,
        dateRange: '24h',
        failedOnly: false,
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
                if (this.failedOnly) params.failed_only = 1;
                const res = await guardianFetch('{{ route("guardian.api.outgoing-http") }}', params);
                this.data = res.data;
                this.$nextTick(() => this.renderCharts());
            } catch (e) { console.error('Outgoing HTTP fetch failed', e); }
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
            if (this.charts.host) this.charts.host.destroy();
            const ctx = this.$refs.hostChart;
            if (ctx && this.data.by_host) {
                this.charts.host = SafeChart(ctx, {
                    type: 'bar',
                    data: {
                        labels: this.data.by_host.map(h => h.host),
                        datasets: [
                            { label: 'Avg Time (ms)', data: this.data.by_host.map(h => Math.round(h.avg_ms)), backgroundColor: colors.blue + '80', borderRadius: 4 },
                            { label: 'Failures', data: this.data.by_host.map(h => h.failures), backgroundColor: colors.red + '80', borderRadius: 4 },
                        ]
                    },
                    options: {
                        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
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
