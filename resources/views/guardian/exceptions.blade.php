@extends('guardian::guardian.layout')

@section('page-title', 'Exceptions')

@section('content')
<div x-data="exceptionsPage()" x-init="init()" class="space-y-6">
    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
        </div>
    </div>

    <div x-show="loading && !loaded">
        <div class="gd-skeleton-grid"><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--chart"></div></div>
    </div>

    <div x-show="loaded" x-cloak class="space-y-6">
            <!-- Trend chart -->
            <div class="gd-card">
                <div class="gd-card__header">Exception Trend (48h)</div>
                <div class="gd-card__body">
                    <div class="gd-chart-container">
                        <canvas x-ref="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Grouped exceptions -->
            <div class="gd-card">
                <div class="gd-card__header">Grouped Exceptions</div>
                <div class="gd-card__body" style="padding:0">
                    <template x-if="(data.grouped.data || []).length === 0">
                        <div class="gd-empty">
                            <div class="gd-empty__text">No exceptions found in the selected period</div>
                        </div>
                    </template>
                    <template x-if="(data.grouped.data || []).length > 0">
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Exception</th>
                                        <th>Occurrences</th>
                                        <th>Last Seen</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="exc in (data.grouped.data || [])" :key="exc.check_class">
                                        <tr class="gd-expandable" @click="expanded === exc.check_class ? expanded = null : expanded = exc.check_class">
                                            <td class="gd-mono" x-text="exc.check_class.replace('exception:', '')"></td>
                                            <td>
                                                <span class="gd-badge gd-badge--danger" x-text="exc.occurrence_count"></span>
                                            </td>
                                            <td x-text="formatDate(exc.last_seen)"></td>
                                            <td class="gd-truncate" x-text="exc.message || '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <!-- Expanded details -->
                    <template x-if="expanded">
                        <div class="gd-expandable__content">
                            <strong>Full exception class:</strong> <span class="gd-mono" x-text="expanded"></span>
                            <br><br>
                            <strong>Message:</strong> <span x-text="getExpandedMessage()"></span>
                            <br><br>
                            <button class="gd-btn gd-btn--sm" @click="copyExceptionAsMarkdown(expanded)">
                                Copy as Markdown for AI
                            </button>
                        </div>
                    </template>
                    <template x-if="data.grouped.last_page > 1">
                        <div class="gd-pagination">
                            <span x-text="`Showing ${data.grouped.from}-${data.grouped.to} of ${data.grouped.total}`"></span>
                            <div class="gd-pagination__links">
                                <button class="gd-pagination__link" :disabled="!data.grouped.prev_page_url" @click="goToPage(data.grouped.current_page - 1)">Prev</button>
                                <button class="gd-pagination__link" :disabled="!data.grouped.next_page_url" @click="goToPage(data.grouped.current_page + 1)">Next</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function exceptionsPage() {
    return {
        loading: true,
        data: null, loaded: false,
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
                const params = { ...dateRangeToParams(this.dateRange), page: this.page };
                const res = await guardianFetch('{{ route("guardian.api.exceptions") }}', params);
                this.data = res.data; this.loaded = true;
                this.$nextTick(() => { this.$nextTick(() => this.renderCharts()); });
            } catch (e) { console.error('Exceptions fetch failed', e); }
            this.loading = false;
        },

        getExpandedMessage() {
            if (!this.expanded || !this.data) return '';
            const exc = (this.data.grouped.data || []).find(e => e.check_class === this.expanded);
            return exc ? exc.message : '';
        },

        copyExceptionAsMarkdown(checkClass) {
            const exc = (this.data.grouped.data || []).find(e => e.check_class === checkClass);
            if (!exc) return;
            const parts = checkClass.replace('exception:', '').split(':');
            let md = `## Exception Report\n\n`;
            md += `- **Exception:** ${parts[0] || checkClass}\n`;
            md += `- **File:** ${parts.slice(1).join(':') || 'unknown'}\n`;
            md += `- **Message:** ${exc.message || 'N/A'}\n`;
            md += `- **Occurrences:** ${exc.occurrence_count || 1}\n`;
            md += `- **Last Seen:** ${exc.last_seen || 'N/A'}\n`;
            md += `\n---\n*Please analyze this error and suggest a fix. Include the root cause and any code changes needed.*\n`;
            navigator.clipboard.writeText(md);
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
            const ctx = this.$refs.trendChart;
            if (ctx && this.data.trend) {
                this.charts.trend = SafeChart(ctx, {
                    type: 'bar',
                    data: {
                        labels: this.data.trend.map(d => formatDateShort(d.hour)),
                        datasets: [{
                            label: 'Exceptions',
                            data: this.data.trend.map(d => d.count),
                            backgroundColor: colors.red + '70',
                            borderColor: colors.red,
                            borderWidth: 1, borderRadius: 3,
                        }]
                    },
                    options: {
                        scales: {
                            x: { grid: { display: false }, ticks: { color: colors.text, maxTicksLimit: 12 } },
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
