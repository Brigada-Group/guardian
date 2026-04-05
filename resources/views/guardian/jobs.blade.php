@extends('guardian::guardian.layout')

@section('page-title', 'Jobs & Scheduler')

@section('content')
<div x-data="jobsPage()" x-init="init()">
    <!-- Tabs -->
    <div class="gd-tabs">
        <div class="gd-tab" :class="{ 'active': tab === 'commands' }" @click="tab = 'commands'; fetchData()">Commands</div>
        <div class="gd-tab" :class="{ 'active': tab === 'scheduled' }" @click="tab = 'scheduled'; fetchData()">Scheduled Tasks</div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading jobs data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Charts -->
            <div class="gd-card">
                <div class="gd-card__header" x-text="tab === 'commands' ? 'Exit Codes' : 'Status Breakdown'"></div>
                <div class="gd-card__body">
                    <div class="gd-chart-container" style="height:200px">
                        <canvas x-ref="breakdownChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Commands table -->
            <template x-if="tab === 'commands'">
                <div class="gd-card">
                    <div class="gd-card__header">Command Log</div>
                    <div class="gd-card__body" style="padding:0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Command</th>
                                        <th>Exit Code</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="log in (data.logs.data || [])" :key="log.id">
                                        <tr>
                                            <td x-text="formatDate(log.created_at)"></td>
                                            <td class="gd-mono" x-text="log.command"></td>
                                            <td>
                                                <span class="gd-badge" :class="log.exit_code === 0 ? 'gd-badge--ok' : 'gd-badge--danger'" x-text="log.exit_code"></span>
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
            </template>

            <!-- Scheduled tasks table -->
            <template x-if="tab === 'scheduled'">
                <div class="gd-card">
                    <div class="gd-card__header">Scheduled Task Log</div>
                    <div class="gd-card__body" style="padding:0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Task</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Output</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="log in (data.logs.data || [])" :key="log.id">
                                        <tr>
                                            <td x-text="formatDate(log.created_at)"></td>
                                            <td class="gd-mono gd-truncate" x-text="log.task"></td>
                                            <td><span class="gd-badge" :class="statusBadgeClass(log.status)" x-text="log.status"></span></td>
                                            <td x-text="formatMs(log.duration_ms)"></td>
                                            <td class="gd-truncate" x-text="log.output || '-'"></td>
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
            </template>
        </div>
    </template>
</div>
@endsection

@section('scripts')
<script>
function jobsPage() {
    return {
        loading: true,
        data: null,
        tab: 'commands',
        page: 1,
        pollInterval: {{ $pollInterval }} * 1000,
        charts: {},

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        async fetchData() {
            this.loading = true;
            try {
                const params = { tab: this.tab, page: this.page };
                const res = await guardianFetch('{{ route("guardian.api.jobs") }}', params);
                destroyAllCharts(this.charts);
                this.data = res.data;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Jobs fetch failed', e); }
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
            const ctx = this.$refs.breakdownChart;
            if (!ctx) return;

            if (this.tab === 'commands' && this.data.exit_codes) {
                const items = this.data.exit_codes;
                this.charts.breakdown = SafeChart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: items.map(i => 'Exit ' + i.exit_code),
                        datasets: [{
                            data: items.map(i => i.count),
                            backgroundColor: items.map(i => i.exit_code === 0 ? colors.green : colors.red),
                        }]
                    },
                    options: { plugins: { legend: { display: true, position: 'right' } } }
                });
            } else if (this.tab === 'scheduled' && this.data.status_breakdown) {
                const items = this.data.status_breakdown;
                const colorMap = { finished: colors.green, failed: colors.red, skipped: colors.yellow, starting: colors.blue };
                this.charts.breakdown = SafeChart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: items.map(i => i.status),
                        datasets: [{
                            data: items.map(i => i.count),
                            backgroundColor: items.map(i => colorMap[i.status] || colors.blue),
                        }]
                    },
                    options: { plugins: { legend: { display: true, position: 'right' } } }
                });
            }
        },
    };
}
</script>
@endsection
