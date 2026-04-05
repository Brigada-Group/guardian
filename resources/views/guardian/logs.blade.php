@extends('guardian::guardian.layout')

@section('page-title', 'Logs')

@section('content')
<div x-data="logsPage()" x-init="init()">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <select class="gd-btn" x-model="filters.level" @change="fetchData()">
                <option value="">All Levels</option>
                <option value="emergency">Emergency</option>
                <option value="alert">Alert</option>
                <option value="critical">Critical</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
            </select>
            <input type="text" class="gd-filter-input" placeholder="Channel..." x-model="filters.channel" @input.debounce.300ms="fetchData()">
            <input type="text" class="gd-filter-input" placeholder="Search message..." x-model="filters.search" @input.debounce.300ms="fetchData()">
        </div>
    </div>

    <template x-if="loading && !data">
        <div class="gd-skeleton-grid"><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--chart"></div></div>
    </template>

    <template x-if="data">
        <div class="gd-fade-in">
            <!-- Metric cards -->
            <div class="gd-metrics">
                <div class="gd-stat-card">
                    <div class="gd-stat-card__label">Total (24h)</div>
                    <div class="gd-stat-card__value" x-text="Number(data.summary.total || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data.summary.emergency > 0 }">
                    <div class="gd-stat-card__label">Emergency</div>
                    <div class="gd-stat-card__value" style="color:#dc2626" x-text="Number(data.summary.emergency || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data.summary.critical > 0 }">
                    <div class="gd-stat-card__label">Critical</div>
                    <div class="gd-stat-card__value" style="color:#dc2626" x-text="Number(data.summary.critical || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': data.summary.error > 0 }">
                    <div class="gd-stat-card__label">Error</div>
                    <div class="gd-stat-card__value" style="color:#ea580c" x-text="Number(data.summary.error || 0).toLocaleString()"></div>
                </div>
                <div class="gd-stat-card">
                    <div class="gd-stat-card__label">Warning</div>
                    <div class="gd-stat-card__value" style="color:#f59e0b" x-text="Number(data.summary.warning || 0).toLocaleString()"></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="gd-grid gd-grid--2">
                <div class="gd-card">
                    <div class="gd-card__header">Level Distribution (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container" style="height:220px">
                            <canvas x-ref="levelChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="gd-card">
                    <div class="gd-card__header">Hourly Trend (24h)</div>
                    <div class="gd-card__body">
                        <div class="gd-chart-container">
                            <canvas x-ref="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log table -->
            <div class="gd-card">
                <div class="gd-card__header">Log Entries</div>
                <div class="gd-card__body" style="padding:0">
                    <template x-if="(data.logs.data || []).length === 0">
                        <div class="gd-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <div class="gd-empty__text">No log entries found matching your filters</div>
                        </div>
                    </template>
                    <template x-if="(data.logs.data || []).length > 0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Level</th>
                                        <th>Message</th>
                                        <th>Channel</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="log in (data.logs.data || [])" :key="log.id">
                                        <tr>
                                            <td style="white-space:nowrap;" x-text="formatDate(log.created_at)"></td>
                                            <td>
                                                <span class="gd-badge" :class="levelBadgeClass(log.level)" x-text="log.level"></span>
                                            </td>
                                            <td class="gd-truncate" style="max-width:400px;" x-text="log.message || '-'"></td>
                                            <td x-text="log.channel || '-'"></td>
                                            <td style="white-space:nowrap;">
                                                <button class="gd-btn gd-btn--sm" @click="toggleDetail(log)" x-text="expandedId === log.id ? 'Hide' : 'Details'"></button>
                                                <button class="gd-btn gd-btn--sm gd-btn--copy" @click="copyAsMarkdown(log)" title="Copy as Markdown">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                    MD
                                                </button>
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
                                <strong>Level:</strong>
                                <span class="gd-badge" :class="levelBadgeClass(expandedLog.level)" x-text="expandedLog.level"></span>

                                <strong>Channel:</strong>
                                <span x-text="expandedLog.channel || '-'"></span>

                                <strong>Time:</strong>
                                <span x-text="formatDate(expandedLog.created_at)"></span>

                                <strong>Message:</strong>
                                <span x-text="expandedLog.message || '-'"></span>
                            </div>

                            <template x-if="expandedLog.context">
                                <div style="margin-top:12px;">
                                    <strong>Context:</strong>
                                    <pre class="gd-context-json" x-text="typeof expandedLog.context === 'object' ? JSON.stringify(expandedLog.context, null, 2) : expandedLog.context"></pre>
                                </div>
                            </template>

                            <template x-if="expandedLog.extra">
                                <div style="margin-top:12px;">
                                    <strong>Extra:</strong>
                                    <pre class="gd-context-json" x-text="typeof expandedLog.extra === 'object' ? JSON.stringify(expandedLog.extra, null, 2) : expandedLog.extra"></pre>
                                </div>
                            </template>
                        </div>
                    </template>

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
    </template>

    <!-- Toast notification -->
    <div x-show="copyToast" x-transition class="gd-toast" style="position:fixed; bottom:20px; right:20px; background:#10b981; color:white; padding:10px 20px; border-radius:8px; font-size:13px; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,.2);">
        Copied as Markdown!
    </div>
</div>
@endsection

@section('scripts')
<script>
function logsPage() {
    return {
        loading: true,
        data: null,
        dateRange: '24h',
        filters: { level: '', channel: '', search: '' },
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
                if (this.filters.level) params.level = this.filters.level;
                if (this.filters.channel) params.channel = this.filters.channel;
                if (this.filters.search) params.search = this.filters.search;
                const res = await guardianFetch('{{ route("guardian.api.logs") }}', params);
                destroyAllCharts(this.charts);
                this.data = res.data;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Logs fetch failed', e); }
            this.loading = false;
        },

        goToPage(p) { this.page = p; this.fetchData(); },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        levelBadgeClass(level) {
            const map = {
                emergency: 'gd-badge--emergency',
                alert: 'gd-badge--alert',
                critical: 'gd-badge--critical',
                error: 'gd-badge--error',
                warning: 'gd-badge--warning-solid',
            };
            return map[level] || 'gd-badge--info';
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
            let md = `## Log Entry Report\n\n`;
            md += `- **Level:** ${log.level}\n`;
            md += `- **Channel:** ${log.channel || 'N/A'}\n`;
            md += `- **Time:** ${log.created_at}\n`;
            md += `- **Message:** ${log.message || 'N/A'}\n`;

            if (log.context) {
                const ctx = typeof log.context === 'object' ? JSON.stringify(log.context, null, 2) : log.context;
                md += `\n### Context\n\`\`\`json\n${ctx}\n\`\`\`\n`;
            }

            if (log.extra) {
                const extra = typeof log.extra === 'object' ? JSON.stringify(log.extra, null, 2) : log.extra;
                md += `\n### Extra\n\`\`\`json\n${extra}\n\`\`\`\n`;
            }

            md += `\n---\n*Please analyze this log entry and provide context on what might have caused it.*\n`;

            navigator.clipboard.writeText(md).then(() => {
                this.copyToast = true;
                setTimeout(() => { this.copyToast = false; }, 2000);
            });
        },

        renderCharts() {
            const isDark = document.documentElement.classList.contains('gd-dark');
            const colors = getChartColors(isDark);

            // Level distribution doughnut
            const levelCtx = this.$refs.levelChart;
            if (levelCtx && this.data.by_level) {
                const levelColors = {
                    emergency: '#dc2626',
                    alert: '#dc2626',
                    critical: '#dc2626',
                    error: '#ea580c',
                    warning: '#f59e0b',
                    notice: '#06b6d4',
                    info: '#3b82f6',
                    debug: '#8b5cf6',
                };
                const levels = this.data.by_level;
                this.charts.level = SafeChart(levelCtx, {
                    type: 'doughnut',
                    data: {
                        labels: levels.map(l => l.level),
                        datasets: [{
                            data: levels.map(l => l.count),
                            backgroundColor: levels.map(l => levelColors[l.level] || '#64748b'),
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        cutout: '60%',
                        plugins: {
                            legend: { display: true, position: 'right', labels: { boxWidth: 12, color: colors.text } },
                        }
                    }
                });
            }

            // Hourly trend stacked bar
            const trendCtx = this.$refs.trendChart;
            if (trendCtx && this.data.trend) {
                const levelColors = {
                    emergency: '#dc2626',
                    alert: '#dc2626',
                    critical: '#b91c1c',
                    error: '#ea580c',
                    warning: '#f59e0b',
                    notice: '#06b6d4',
                    info: '#3b82f6',
                    debug: '#8b5cf6',
                };
                const hours = [...new Set(this.data.trend.map(d => d.hour))].sort();
                const uniqueLevels = [...new Set(this.data.trend.map(d => d.level))];

                const datasets = uniqueLevels.map(level => ({
                    label: level,
                    data: hours.map(h => {
                        const item = this.data.trend.find(d => d.hour === h && d.level === level);
                        return item ? item.count : 0;
                    }),
                    backgroundColor: (levelColors[level] || '#64748b') + '80',
                    borderColor: levelColors[level] || '#64748b',
                    borderWidth: 1,
                    borderRadius: 2,
                }));

                this.charts.trend = SafeChart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: hours.map(h => formatDateShort(h)),
                        datasets: datasets,
                    },
                    options: {
                        plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, color: colors.text } } },
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
