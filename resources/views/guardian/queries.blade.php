@extends('guardian::guardian.layout')

@section('page-title', 'Queries')

@section('content')
<div x-data="queriesPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
        </div>
    </div>

    <!-- Tabs -->
    <div class="gd-tabs">
        <div class="gd-tab" :class="{ 'active': tab === 'all' }" @click="tab = 'all'; fetchData()">All</div>
        <div class="gd-tab" :class="{ 'active': tab === 'slow' }" @click="tab = 'slow'; fetchData()">Slow Queries</div>
        <div class="gd-tab" :class="{ 'active': tab === 'n_plus_one' }" @click="tab = 'n_plus_one'; fetchData()">N+1 Queries</div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading query data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Trend chart -->
            <div class="gd-card">
                <div class="gd-card__header">Slow Query Trend (24h)</div>
                <div class="gd-card__body">
                    <div class="gd-chart-container">
                        <canvas x-ref="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Query table -->
            <div class="gd-card">
                <div class="gd-card__header">Query Log</div>
                <div class="gd-card__body" style="padding:0">
                    <div class="gd-table-wrapper">
                        <table class="gd-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Connection</th>
                                    <th>SQL</th>
                                    <th>Flags</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs.data || [])" :key="log.id">
                                    <tr class="gd-expandable" @click="expanded === log.id ? expanded = null : expanded = log.id">
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td x-text="formatMs(log.duration_ms)"></td>
                                        <td x-text="log.connection || '-'"></td>
                                        <td class="gd-mono gd-truncate" x-text="(log.sql || '').substring(0, 80) + (log.sql && log.sql.length > 80 ? '...' : '')"></td>
                                        <td>
                                            <span x-show="log.is_slow" class="gd-badge gd-badge--warning">Slow</span>
                                            <span x-show="log.is_n_plus_one" class="gd-badge gd-badge--danger">N+1</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <!-- Expanded SQL -->
                    <template x-if="expanded">
                        <div class="gd-expandable__content" x-text="getExpandedSql()"></div>
                    </template>
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
    </template>
</div>
@endsection

@section('scripts')
<script>
function queriesPage() {
    return {
        loading: true,
        data: null,
        tab: 'all',
        dateRange: '24h',
        expanded: null,
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
                const params = { ...dateRangeToParams(this.dateRange), tab: this.tab, page: this.page };
                const res = await guardianFetch('{{ route("guardian.api.queries") }}', params);
                this.data = res.data;
                this.$nextTick(() => this.renderCharts());
            } catch (e) { console.error('Queries fetch failed', e); }
            this.loading = false;
        },

        getExpandedSql() {
            if (!this.expanded || !this.data) return '';
            const log = (this.data.logs.data || []).find(l => l.id === this.expanded);
            return log ? log.sql : '';
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
            if (this.charts.trend) this.charts.trend.destroy();
            const ctx = this.$refs.trendChart;
            if (ctx && this.data.trend) {
                this.charts.trend = SafeChart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.data.trend.map(d => formatDateShort(d.hour)),
                        datasets: [{
                            label: 'Slow Queries',
                            data: this.data.trend.map(d => d.count),
                            borderColor: colors.yellow,
                            backgroundColor: colors.yellow + '20',
                            fill: true, tension: 0.3, pointRadius: 2,
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
