@extends('guardian::guardian.layout')

@section('page-title', 'Queue Jobs')

@section('content')
<div x-data="queuePage()" x-init="init()" class="space-y-6">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <select class="gd-btn" x-model="filters.status" @change="fetchData()">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="processing">Processing</option>
            </select>
            <input type="text" class="gd-filter-input" placeholder="Queue name..." x-model="filters.queue" @input.debounce.300ms="fetchData()">
            <input type="text" class="gd-filter-input" placeholder="Search job class..." x-model="filters.job_class" @input.debounce.300ms="fetchData()">
        </div>
    </div>

    <div x-show="loading && !loaded">
        <div class="gd-skeleton-grid"><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--chart"></div></div>
    </div>

    <div x-show="loaded" x-cloak class="gd-fade-in space-y-6">
            <!-- Metric cards -->
            <div class="gd-metrics">
                <div class="gd-stat-card">
                    <div class="gd-stat-card__label">Total Jobs (24h)</div>
                    <div class="gd-stat-card__value" x-text="Number(data?.summary?.total || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card">
                    <div class="gd-stat-card__label">Completed</div>
                    <div class="gd-stat-card__value" style="color:var(--gd-success)" x-text="Number(data?.summary?.completed || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data?.summary?.failed > 0 }">
                    <div class="gd-stat-card__label">Failed</div>
                    <div class="gd-stat-card__value" style="color:var(--gd-danger)" x-text="Number(data?.summary?.failed || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card">
                    <div class="gd-stat-card__label">Avg Duration</div>
                    <div class="gd-stat-card__value" x-text="formatMs(data?.summary?.avg_duration)"></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Throughput (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="throughputChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Top Failed Jobs (24h)</div>
                    <div class="gd-card__body" style="padding:0">
                        <template x-if="(data?.top_failed || []).length === 0">
                            <div class="gd-empty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <div class="gd-empty__text">No failed jobs in the last 24 hours</div>
                            </div>
                        </template>
                        <template x-if="(data?.top_failed || []).length > 0">
                            <div class="gd-table-wrapper">
                                <table class="gd-table">
                                    <thead>
                                        <tr>
                                            <th>Job Class</th>
                                            <th>Failures</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="job in (data?.top_failed || [])" :key="job.job_class">
                                            <tr>
                                                <td class="gd-mono gd-truncate" x-text="shortClass(job.job_class)"></td>
                                                <td><span class="gd-badge gd-badge--danger" x-text="job.fail_count"></span></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Job log table -->
            <div class="gd-card">
                <div class="gd-card__header">Job Log</div>
                <div class="gd-card__body" style="padding:0">
                    <template x-if="(data?.logs?.data || []).length === 0">
                        <div class="gd-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                            <div class="gd-empty__text">No queue jobs found matching your filters</div>
                        </div>
                    </template>
                    <template x-if="(data?.logs?.data || []).length > 0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Job Class</th>
                                        <th>Queue</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Attempt</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="log in (data?.logs?.data || [])" :key="log.id">
                                        <tr>
                                            <td style="white-space:nowrap;" x-text="formatDate(log.created_at)"></td>
                                            <td class="gd-mono gd-truncate" style="max-width:250px;" x-text="shortClass(log.job_class)"></td>
                                            <td x-text="log.queue || 'default'"></td>
                                            <td>
                                                <span class="gd-badge" :class="queueBadgeClass(log.status)" x-text="log.status"></span>
                                            </td>
                                            <td x-text="formatMs(log.duration_ms)"></td>
                                            <td x-text="log.attempt || 1"></td>
                                            <td style="white-space:nowrap;">
                                                <button class="gd-btn gd-btn--sm" @click="toggleDetail(log)" x-text="expandedId === log.id ? 'Hide' : 'Details'"></button>
                                                <template x-if="log.status === 'failed'">
                                                    <button class="gd-btn gd-btn--sm gd-btn--copy" @click="copyAsMarkdown(log)" title="Copy as Markdown">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                        MD
                                                    </button>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- Expanded detail panel -->
                    <template x-if="expandedLog">
                        <div class="gd-expandable__content">
                            <div style="display:grid; grid-template-columns: 120px 1fr; gap:8px; font-size:13px;">
                                <strong>Full Class:</strong>
                                <span class="gd-mono" x-text="expandedLog.job_class"></span>

                                <strong>Queue:</strong>
                                <span x-text="expandedLog.queue || 'default'"></span>

                                <strong>Connection:</strong>
                                <span x-text="expandedLog.connection || '-'"></span>

                                <strong>Status:</strong>
                                <span class="gd-badge" :class="queueBadgeClass(expandedLog.status)" x-text="expandedLog.status"></span>

                                <strong>Duration:</strong>
                                <span x-text="formatMs(expandedLog.duration_ms)"></span>

                                <strong>Attempt:</strong>
                                <span x-text="expandedLog.attempt || 1"></span>

                                <strong>Started:</strong>
                                <span x-text="formatDate(expandedLog.started_at || expandedLog.created_at)"></span>

                                <template x-if="expandedLog.finished_at">
                                    <strong>Finished:</strong>
                                </template>
                                <template x-if="expandedLog.finished_at">
                                    <span x-text="formatDate(expandedLog.finished_at)"></span>
                                </template>
                            </div>

                            <template x-if="expandedLog.exception">
                                <div style="margin-top:12px;">
                                    <strong>Error:</strong>
                                    <pre class="gd-context-json" x-text="expandedLog.exception"></pre>
                                </div>
                            </template>

                            <template x-if="expandedLog.payload">
                                <div style="margin-top:12px;">
                                    <strong>Payload:</strong>
                                    <pre class="gd-context-json" x-text="typeof expandedLog.payload === 'object' ? JSON.stringify(expandedLog.payload, null, 2) : expandedLog.payload"></pre>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Pagination -->
                    <template x-if="data?.logs?.last_page > 1">
                        <div class="gd-pagination">
                            <span x-text="`Showing ${data?.logs?.from}-${data?.logs?.to} of ${data?.logs?.total}`"></span>
                            <div class="gd-pagination__links">
                                <button class="gd-pagination__link" :disabled="!data?.logs?.prev_page_url" @click="goToPage(data?.logs?.current_page - 1)">Prev</button>
                                <button class="gd-pagination__link" :disabled="!data?.logs?.next_page_url" @click="goToPage(data?.logs?.current_page + 1)">Next</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
    </template>

    <!-- Toast notification -->
    <div x-show="copyToast" x-transition class="gd-toast" style="position:fixed; bottom:20px; right:20px; background:#10b981; color:white; padding:10px 20px; border-radius:8px; font-size:13px; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,.2);">
        Copied as Markdown!
    </div>
</div>
@endsection

@section('scripts')
<script>
function queuePage() {
    return {
        loading: true,
        data: null, loaded: false,
        dateRange: '24h',
        filters: { status: '', queue: '', job_class: '' },
        page: 1,
        pollInterval: {{ $pollInterval }} * 1000,
        charts: {},
        expandedId: null,
        expandedLog: null,
        copyToast: false,

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
                if (this.filters.queue) params.queue = this.filters.queue;
                if (this.filters.job_class) params.job_class = this.filters.job_class;
                const res = await guardianFetch('{{ route("guardian.api.queue") }}', params);
                this.data = res.data; this.loaded = true;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Queue fetch failed', e); }
            this.loading = false;
        },

        goToPage(p) { this.page = p; this.fetchData(); },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        shortClass(cls) {
            if (!cls) return '-';
            return cls.split('\\').pop();
        },

        queueBadgeClass(status) {
            const map = {
                completed: 'gd-badge--completed',
                failed: 'gd-badge--failed',
                processing: 'gd-badge--processing',
            };
            return map[status] || 'gd-badge--unknown';
        },

        toggleDetail(log) {
            if (this.expandedId === log.id) {
                this.expandedId = null;
                this.expandedLog = null;
            } else {
                this.expandedId = log.id;
                this.expandedLog = log;
            }
        },

        copyAsMarkdown(log) {
            let md = `## Failed Job Report\n\n`;
            md += `- **Job Class:** ${log.job_class}\n`;
            md += `- **Queue:** ${log.queue || 'default'}\n`;
            md += `- **Status:** ${log.status}\n`;
            md += `- **Duration:** ${formatMs(log.duration_ms)}\n`;
            md += `- **Attempt:** ${log.attempt || 1}\n`;
            md += `- **Time:** ${log.created_at}\n`;

            if (log.connection) md += `- **Connection:** ${log.connection}\n`;

            if (log.exception) {
                md += `\n### Error\n\`\`\`\n${log.exception}\n\`\`\`\n`;
            }

            if (log.payload) {
                const payload = typeof log.payload === 'object' ? JSON.stringify(log.payload, null, 2) : log.payload;
                md += `\n### Payload\n\`\`\`json\n${payload}\n\`\`\`\n`;
            }

            md += `\n---\n*Please analyze this failed job and suggest a fix. Include the root cause and any code changes needed.*\n`;

            navigator.clipboard.writeText(md).then(() => {
                this.copyToast = true;
                setTimeout(() => { this.copyToast = false; }, 2000);
            });
        },

        renderCharts() {
            const isDark = document.documentElement.classList.contains('gd-dark');
            const colors = getChartColors(isDark);

            // Throughput chart
            const ctx = this.$refs.throughputChart;
            if (ctx && this.data?.throughput) {
                const hours = [...new Set(this.data?.throughput.map(d => d.hour))].sort();
                const completedData = hours.map(h => {
                    const item = this.data?.throughput.find(d => d.hour === h && d.status === 'completed');
                    return item ? item.count : 0;
                });
                const failedData = hours.map(h => {
                    const item = this.data?.throughput.find(d => d.hour === h && d.status === 'failed');
                    return item ? item.count : 0;
                });

                this.charts.throughput = SafeChart(ctx, {
                    type: 'bar',
                    data: {
                        labels: hours.map(h => formatDateShort(h)),
                        datasets: [
                            {
                                label: 'Completed',
                                data: completedData,
                                backgroundColor: colors.green + '80',
                                borderColor: colors.green,
                                borderWidth: 1,
                                borderRadius: 3,
                            },
                            {
                                label: 'Failed',
                                data: failedData,
                                backgroundColor: colors.red + '80',
                                borderColor: colors.red,
                                borderWidth: 1,
                                borderRadius: 3,
                            },
                        ]
                    },
                    options: {
                        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12 } } },
                        scales: {
                            x: { stacked: true, grid: { display: false }, ticks: { color: colors.text, maxTicksLimit: 12 } },
                            y: { stacked: true, grid: { color: colors.grid }, ticks: { color: colors.text, stepSize: 1 }, beginAtZero: true }
                        }
                    }
                });
            }
        },
    };
}
</script>
@endsection
