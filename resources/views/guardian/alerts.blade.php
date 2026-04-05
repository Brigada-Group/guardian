@extends('guardian::guardian.layout')

@section('page-title', 'Alerts')

@section('content')
<div x-data="alertsPage()" x-init="init()" class="space-y-6">
    <!-- Loading -->
    <div x-show="loading && !loaded">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
        </div>
        <div class="gd-skeleton gd-skeleton--chart"></div>
    </div>

    <div x-show="loaded" x-cloak class="space-y-6">
        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="gd-stat-card">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#6366f1"></span>
                    <span class="gd-stat-card__label">Alerts (24h)</span>
                </div>
                <div class="gd-stat-card__value" x-text="data?.summary?.total_24h ?? 0"></div>
            </div>
            <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': (data?.summary?.critical_24h ?? 0) > 0 }">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#ef4444"></span>
                    <span class="gd-stat-card__label">Critical</span>
                </div>
                <div class="gd-stat-card__value" x-text="data?.summary?.critical_24h ?? 0"></div>
            </div>
            <div class="gd-stat-card">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#eab308"></span>
                    <span class="gd-stat-card__label">Warning</span>
                </div>
                <div class="gd-stat-card__value" x-text="data?.summary?.warning_24h ?? 0"></div>
            </div>
            <div class="gd-stat-card">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#f97316"></span>
                    <span class="gd-stat-card__label">Errors</span>
                </div>
                <div class="gd-stat-card__value" x-text="data?.summary?.error_24h ?? 0"></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="gd-card">
            <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                @include('guardian::guardian.partials.date-filter')
                <select class="gd-filter-input" x-model="filters.status" @change="fetchData()">
                    <option value="">All Statuses</option>
                    <option value="critical">Critical</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="ok">OK</option>
                </select>
                <select class="gd-filter-input" x-model="filters.type" @change="fetchData()">
                    <option value="">All Types</option>
                    <option value="exception">Exceptions</option>
                    <option value="monitor">Monitor Alerts</option>
                    <option value="check">Health Checks</option>
                </select>
            </div>
        </div>

        <!-- Alert table -->
        <div class="gd-card">
            <div class="gd-card__header">Alert History</div>
            <div class="gd-card__body" style="padding:0">
                <!-- Empty state -->
                <template x-if="!data?.alerts?.data?.length">
                    <div class="gd-empty">
                        <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <div class="gd-empty__text">No alerts found</div>
                        <div class="gd-empty__hint">Alerts appear when monitoring thresholds are exceeded</div>
                    </div>
                </template>

                <!-- Table -->
                <template x-if="data?.alerts?.data?.length > 0">
                    <div>
                        <div class="gd-table-wrapper">
                            <table class="gd-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                        <th>Message</th>
                                        <th style="width:120px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="alert in data.alerts.data" :key="alert.id">
                                        <tr>
                                            <td style="white-space:nowrap; font-size:12px; color:var(--tw-text-opacity, #64748b);" x-text="formatDate(alert.notified_at || alert.created_at)"></td>
                                            <td>
                                                <span class="gd-badge" :class="statusBadgeClass(alert.status)" x-text="alert.status"></span>
                                            </td>
                                            <td class="gd-mono" style="max-width:180px;" x-text="formatSource(alert.check_class)"></td>
                                            <td style="max-width:400px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="alert.message"></td>
                                            <td>
                                                <div style="display:flex; gap:4px;">
                                                    <button class="gd-btn gd-btn--sm" @click="toggleDetail(alert)" x-text="expandedId === alert.id ? 'Hide' : 'Details'"></button>
                                                    <button class="gd-btn gd-btn--sm" @click="copyAsMarkdown(alert)" title="Copy as Markdown for AI">MD</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Expanded detail -->
                        <template x-if="expandedAlert">
                            <div style="margin:16px; padding:20px; border-radius:8px; border:1px solid hsl(214.3 31.8% 91.4%); background:hsl(210 40% 96.1%);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <span style="font-size:14px; font-weight:600;">Alert Detail</span>
                                    <button class="gd-btn gd-btn--sm" @click="copyAsMarkdown(expandedAlert)">Copy as Markdown for AI</button>
                                </div>
                                <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                                    <div style="display:flex; gap:12px;">
                                        <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">Status</span>
                                        <span class="gd-badge" :class="statusBadgeClass(expandedAlert.status)" x-text="expandedAlert.status"></span>
                                    </div>
                                    <div style="display:flex; gap:12px;">
                                        <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">Source</span>
                                        <span class="gd-mono" x-text="expandedAlert.check_class" style="word-break:break-all;"></span>
                                    </div>
                                    <div style="display:flex; gap:12px;">
                                        <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">Message</span>
                                        <span x-text="expandedAlert.message"></span>
                                    </div>
                                    <div style="display:flex; gap:12px;">
                                        <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">Time</span>
                                        <span x-text="formatDate(expandedAlert.notified_at || expandedAlert.created_at)"></span>
                                    </div>
                                    <template x-if="expandedAlert.metadata?.exception_class">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">Exception</span>
                                            <span class="gd-mono" x-text="expandedAlert.metadata.exception_class" style="word-break:break-all;"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.file">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">File</span>
                                            <span class="gd-mono" x-text="expandedAlert.metadata.file + ':' + (expandedAlert.metadata.line || '')"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.url && expandedAlert.metadata.url !== 'N/A'">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">URL</span>
                                            <span class="gd-mono" x-text="expandedAlert.metadata.url"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.status_code">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">HTTP Status</span>
                                            <span x-text="expandedAlert.metadata.status_code"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.user && expandedAlert.metadata.user !== 'N/A'">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">User</span>
                                            <span x-text="expandedAlert.metadata.user"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.ip && expandedAlert.metadata.ip !== 'N/A'">
                                        <div style="display:flex; gap:12px;">
                                            <span style="width:100px; font-weight:600; color:#64748b; flex-shrink:0;">IP</span>
                                            <span x-text="expandedAlert.metadata.ip"></span>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.headers && expandedAlert.metadata.headers !== 'N/A'">
                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                            <span style="font-weight:600; color:#64748b;">Headers</span>
                                            <pre class="gd-mono" style="margin:0; padding:10px; border-radius:6px; background:white; border:1px solid hsl(214.3 31.8% 91.4%); font-size:11px; white-space:pre-wrap; overflow-x:auto;" x-text="expandedAlert.metadata.headers"></pre>
                                        </div>
                                    </template>
                                    <template x-if="expandedAlert.metadata?.stack_trace">
                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                            <span style="font-weight:600; color:#64748b;">Stack Trace</span>
                                            <pre class="gd-mono" style="margin:0; padding:10px; border-radius:6px; background:white; border:1px solid hsl(214.3 31.8% 91.4%); font-size:11px; white-space:pre-wrap; overflow-x:auto;" x-text="expandedAlert.metadata.stack_trace"></pre>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Pagination -->
                        <template x-if="data.alerts.last_page > 1">
                            <div class="gd-pagination">
                                <span x-text="`Showing ${data.alerts.from}-${data.alerts.to} of ${data.alerts.total}`"></span>
                                <div class="gd-pagination__links">
                                    <button class="gd-pagination__link" :disabled="!data.alerts.prev_page_url" @click="goToPage(data.alerts.current_page - 1)">Prev</button>
                                    <button class="gd-pagination__link" :disabled="!data.alerts.next_page_url" @click="goToPage(data.alerts.current_page + 1)">Next</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="copyToast" x-transition class="gd-toast">Copied as Markdown!</div>
</div>
@endsection

@section('scripts')
<script>
function alertsPage() {
    return {
        loading: true,
        data: null, loaded: false,
        dateRange: '24h',
        filters: { status: '', type: '' },
        page: 1,
        pollInterval: {{ $pollInterval }} * 1000,
        expandedId: null,
        expandedAlert: null,
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
                if (this.filters.type) params.type = this.filters.type;
                const res = await guardianFetch('{{ route("guardian.api.alerts") }}', params);
                this.data = res.data; this.loaded = true;
            } catch (e) { console.error('Alerts fetch failed', e); }
            this.loading = false;
        },

        goToPage(p) { this.page = p; this.fetchData(); },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        formatSource(checkClass) {
            if (!checkClass) return '-';
            if (checkClass.startsWith('exception:')) {
                const parts = checkClass.replace('exception:', '').split(':');
                return parts[0].split('\\').pop();
            }
            if (checkClass.startsWith('monitor:')) {
                return checkClass.replace('monitor:', '').split(':')[0];
            }
            return checkClass.split('\\').pop();
        },

        toggleDetail(alert) {
            if (this.expandedId === alert.id) {
                this.expandedId = null;
                this.expandedAlert = null;
            } else {
                this.expandedId = alert.id;
                this.expandedAlert = alert;
            }
        },

        copyAsMarkdown(alert) {
            const meta = alert.metadata || {};
            let md = `## Guardian Alert\n\n`;
            md += `- **Status:** ${alert.status}\n`;
            md += `- **Time:** ${alert.notified_at || alert.created_at}\n`;
            md += `- **Source:** ${alert.check_class}\n`;
            md += `- **Message:** ${alert.message}\n`;
            if (meta.exception_class) md += `- **Exception:** ${meta.exception_class}\n`;
            if (meta.file) md += `- **File:** ${meta.file}${meta.line ? ':' + meta.line : ''}\n`;
            if (meta.url && meta.url !== 'N/A') md += `- **URL:** ${meta.url}\n`;
            if (meta.status_code) md += `- **HTTP Status:** ${meta.status_code}\n`;
            if (meta.user && meta.user !== 'N/A') md += `- **User:** ${meta.user}\n`;
            if (meta.ip && meta.ip !== 'N/A') md += `- **IP:** ${meta.ip}\n`;
            if (meta.headers && meta.headers !== 'N/A') {
                md += `\n### Headers\n\`\`\`\n${meta.headers}\n\`\`\`\n`;
            }
            if (meta.stack_trace) {
                md += `\n### Stack Trace\n\`\`\`\n${meta.stack_trace}\n\`\`\`\n`;
            }
            md += `\n---\n*Please analyze this error and suggest a fix. Include the root cause and any code changes needed.*\n`;
            navigator.clipboard.writeText(md).then(() => {
                this.copyToast = true;
                setTimeout(() => { this.copyToast = false; }, 2000);
            });
        },
    };
}
</script>
@endsection
