@extends('guardian::guardian.layout')

@section('page-title', 'Mail')

@section('content')
<div x-data="mailPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <select class="gd-btn" x-model="filters.status" @change="fetchData()">
                <option value="">All Statuses</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
            </select>
        </div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-skeleton-grid"><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--chart"></div></div>
    </template>

    <template x-if="data">
        <div>
            <!-- Daily chart -->
            <div class="gd-card">
                <div class="gd-card__header">Mail Volume (30 days)</div>
                <div class="gd-card__body">
                    <div class="gd-chart-container">
                        <canvas x-ref="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Log table -->
            <div class="gd-card">
                <div class="gd-card__header">Mail Log</div>
                <div class="gd-card__body" style="padding:0">
                    <div class="gd-table-wrapper">
                        <table class="gd-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Mailable</th>
                                    <th>To</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs.data || [])" :key="log.id">
                                    <tr>
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td class="gd-mono gd-truncate" x-text="log.mailable || '-'"></td>
                                        <td class="gd-truncate" x-text="log.to || '-'"></td>
                                        <td class="gd-truncate" x-text="log.subject || '-'"></td>
                                        <td><span class="gd-badge" :class="log.status === 'sent' ? 'gd-badge--ok' : 'gd-badge--danger'" x-text="log.status"></span></td>
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
    </template>
</div>
@endsection

@section('scripts')
<script>
function mailPage() {
    return {
        loading: true,
        data: null,
        dateRange: '24h',
        filters: { status: '' },
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
                if (this.filters.status) params.status = this.filters.status;
                const res = await guardianFetch('{{ route("guardian.api.mail") }}', params);
                destroyAllCharts(this.charts);
                this.data = res.data;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Mail fetch failed', e); }
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
            const ctx = this.$refs.dailyChart;
            if (!ctx || !this.data.daily_chart) return;

            // Group by day and status
            const days = [...new Set(this.data.daily_chart.map(d => d.day))].sort();
            const sentData = days.map(day => {
                const item = this.data.daily_chart.find(d => d.day === day && d.status === 'sent');
                return item ? item.count : 0;
            });
            const failedData = days.map(day => {
                const item = this.data.daily_chart.find(d => d.day === day && d.status === 'failed');
                return item ? item.count : 0;
            });

            this.charts.daily = SafeChart(ctx, {
                type: 'bar',
                data: {
                    labels: days,
                    datasets: [
                        { label: 'Sent', data: sentData, backgroundColor: colors.green + '80', borderRadius: 3 },
                        { label: 'Failed', data: failedData, backgroundColor: colors.red + '80', borderRadius: 3 },
                    ]
                },
                options: {
                    plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12 } } },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { color: colors.text } },
                        y: { stacked: true, grid: { color: colors.grid }, ticks: { color: colors.text }, beginAtZero: true }
                    }
                }
            });
        },
    };
}
</script>
@endsection
