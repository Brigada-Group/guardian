@extends('guardian::guardian.layout')

@section('page-title', 'Notifications')

@section('content')
<div x-data="notificationsPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <select class="gd-btn" x-model="filters.channel" @change="fetchData()">
                <option value="">All Channels</option>
                <option value="mail">Mail</option>
                <option value="database">Database</option>
                <option value="broadcast">Broadcast</option>
                <option value="slack">Slack</option>
            </select>
            <select class="gd-btn" x-model="filters.status" @change="fetchData()">
                <option value="">All Statuses</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
            </select>
        </div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading notification data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Channel breakdown -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">By Channel</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container" style="height:200px">
                            <canvas x-ref="channelChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Channel Stats</div>
                    <div class="gd-card__body" style="padding:0">
                        <table class="gd-table">
                            <thead><tr><th>Channel</th><th>Count</th><th>Failures</th><th>Fail Rate</th></tr></thead>
                            <tbody>
                                <template x-for="ch in (data.by_channel || [])" :key="ch.channel">
                                    <tr>
                                        <td x-text="ch.channel"></td>
                                        <td x-text="ch.count"></td>
                                        <td>
                                            <span class="gd-badge" :class="ch.failures > 0 ? 'gd-badge--danger' : 'gd-badge--ok'" x-text="ch.failures"></span>
                                        </td>
                                        <td x-text="ch.count > 0 ? (ch.failures / ch.count * 100).toFixed(1) + '%' : '0%'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Log table -->
            <div class="gd-card">
                <div class="gd-card__header">Notification Log</div>
                <div class="gd-card__body" style="padding:0">
                    <div class="gd-table-wrapper">
                        <table class="gd-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Notification</th>
                                    <th>Channel</th>
                                    <th>Notifiable</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="log in (data.logs.data || [])" :key="log.id">
                                    <tr>
                                        <td x-text="formatDate(log.created_at)"></td>
                                        <td class="gd-mono gd-truncate" x-text="log.notification_class || '-'"></td>
                                        <td><span class="gd-badge gd-badge--info" x-text="log.channel"></span></td>
                                        <td class="gd-truncate" x-text="log.notifiable_type || '-'"></td>
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
function notificationsPage() {
    return {
        loading: true,
        data: null,
        filters: { channel: '', status: '' },
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
                const params = { page: this.page };
                if (this.filters.channel) params.channel = this.filters.channel;
                if (this.filters.status) params.status = this.filters.status;
                const res = await guardianFetch('{{ route("guardian.api.notifications") }}', params);
                this.data = res.data;
                this.$nextTick(() => this.renderCharts());
            } catch (e) { console.error('Notifications fetch failed', e); }
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
            if (this.charts.channel) this.charts.channel.destroy();
            const ctx = this.$refs.channelChart;
            if (!ctx || !this.data.by_channel) return;

            const palette = [colors.blue, colors.green, colors.yellow, colors.purple, colors.cyan, colors.red];
            this.charts.channel = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: this.data.by_channel.map(c => c.channel),
                    datasets: [{
                        data: this.data.by_channel.map(c => c.count),
                        backgroundColor: this.data.by_channel.map((_, i) => palette[i % palette.length]),
                    }]
                },
                options: { plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 12 } } } }
            });
        },
    };
}
</script>
@endsection
