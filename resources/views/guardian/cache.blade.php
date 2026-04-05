@extends('guardian::guardian.layout')

@section('page-title', 'Cache')

@section('content')
<div x-data="cachePage()" x-init="init()">
    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading cache data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Top metrics -->
            <div class="gd-metrics">
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Current Hit Rate</div>
                    <div class="gd-metric-card__value" x-text="Number(data.current_hit_rate).toFixed(1) + '%'"></div>
                </div>
                <template x-for="store in (data.by_store || [])" :key="store.store">
                    <div class="gd-metric-card">
                        <div class="gd-metric-card__label" x-text="store.store + ' Hit Rate'"></div>
                        <div class="gd-metric-card__value" x-text="Number(store.avg_hit_rate || 0).toFixed(1) + '%'"></div>
                        <div class="gd-metric-card__subtitle" x-text="'Hits: ' + Number(store.total_hits || 0).toLocaleString() + ' / Misses: ' + Number(store.total_misses || 0).toLocaleString()"></div>
                    </div>
                </template>
            </div>

            <!-- Charts -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Hit Rate Over Time</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="hitRateChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Store Breakdown (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container" style="height:200px">
                            <canvas x-ref="storeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hit rate gauge -->
            <div class="gd-card">
                <div class="gd-card__header">Hit Rate Gauge</div>
                <div class="gd-card__body" style="display:flex; align-items:center; justify-content:center;">
                    <div style="width:200px; height:120px; position:relative;">
                        <canvas x-ref="gaugeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent log entries -->
            <div class="gd-card">
                <div class="gd-card__header">Cache Snapshots (recent)</div>
                <div class="gd-card__body" style="padding:0">
                    <div class="gd-table-wrapper">
                        <table class="gd-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Store</th>
                                    <th>Hit Rate</th>
                                    <th>Hits</th>
                                    <th>Misses</th>
                                    <th>Writes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs || []).slice(0, 50)" :key="log.id">
                                    <tr>
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td x-text="log.store || '-'"></td>
                                        <td>
                                            <span class="gd-badge" :class="log.hit_rate >= 80 ? 'gd-badge--ok' : log.hit_rate >= 50 ? 'gd-badge--warning' : 'gd-badge--danger'" x-text="Number(log.hit_rate || 0).toFixed(1) + '%'"></span>
                                        </td>
                                        <td x-text="log.hits"></td>
                                        <td x-text="log.misses"></td>
                                        <td x-text="log.writes"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection

@section('scripts')
<script>
function cachePage() {
    return {
        loading: true,
        data: null,
        pollInterval: {{ $pollInterval }} * 1000,
        charts: {},

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        async fetchData() {
            this.loading = true;
            try {
                const res = await guardianFetch('{{ route("guardian.api.cache") }}');
                destroyAllCharts(this.charts);
                this.data = res.data;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Cache fetch failed', e); }
            this.loading = false;
        },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        renderCharts() {
            const isDark = document.documentElement.classList.contains('gd-dark');
            const colors = getChartColors(isDark);

            // Hit rate line chart
            const hrCtx = this.$refs.hitRateChart;
            if (hrCtx && this.data.logs) {
                const logs = [...this.data.logs].reverse().slice(-50);
                this.charts.hitRate = SafeChart(hrCtx, {
                    type: 'line',
                    data: {
                        labels: logs.map(l => formatDateShort(l.created_at)),
                        datasets: [{
                            label: 'Hit Rate %',
                            data: logs.map(l => l.hit_rate),
                            borderColor: colors.green,
                            backgroundColor: colors.green + '20',
                            fill: true, tension: 0.3, pointRadius: 1,
                        }]
                    },
                    options: {
                        scales: {
                            x: { grid: { color: colors.grid }, ticks: { color: colors.text, maxTicksLimit: 10 } },
                            y: { grid: { color: colors.grid }, ticks: { color: colors.text, callback: v => v + '%' }, min: 0, max: 100 }
                        }
                    }
                });
            }

            // Store breakdown
            const stCtx = this.$refs.storeChart;
            if (stCtx && this.data.by_store) {
                const palette = [colors.blue, colors.green, colors.yellow, colors.purple, colors.cyan];
                this.charts.store = SafeChart(stCtx, {
                    type: 'bar',
                    data: {
                        labels: this.data.by_store.map(s => s.store),
                        datasets: [
                            { label: 'Hits', data: this.data.by_store.map(s => s.total_hits), backgroundColor: colors.green + '80', borderRadius: 3 },
                            { label: 'Misses', data: this.data.by_store.map(s => s.total_misses), backgroundColor: colors.red + '60', borderRadius: 3 },
                            { label: 'Writes', data: this.data.by_store.map(s => s.total_writes), backgroundColor: colors.blue + '60', borderRadius: 3 },
                        ]
                    },
                    options: {
                        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12 } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: colors.text } },
                            y: { grid: { color: colors.grid }, ticks: { color: colors.text }, beginAtZero: true }
                        }
                    }
                });
            }

            // Gauge chart
            const gCtx = this.$refs.gaugeChart;
            if (gCtx) {
                const rate = Number(this.data.current_hit_rate || 0);
                this.charts.gauge = SafeChart(gCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [rate, 100 - rate],
                            backgroundColor: [rate >= 80 ? colors.green : rate >= 50 ? colors.yellow : colors.red, isDark ? '#334155' : '#e2e8f0'],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        circumference: 180, rotation: -90, cutout: '75%',
                        plugins: { legend: { display: false }, tooltip: { enabled: false } }
                    }
                });
            }
        },
    };
}
</script>
@endsection
