@extends('guardian::guardian.layout')

@section('page-title', 'Alerts')

@section('content')
<div x-data="alertsPage()" x-init="init()">
    <!-- Summary cards -->
    <div class="gd-grid gd-grid--4" style="margin-bottom:20px;">
        <div x-show="loaded" x-cloak>
            <div class="gd-grid gd-grid--4" style="grid-column: 1/-1;">
                @include('guardian::guardian.partials.metric-card', ['xValue' => 'data.summary.total_24h', 'title' => 'Alerts (24h)', 'color' => 'blue'])
                @include('guardian::guardian.partials.metric-card', ['xValue' => 'data.summary.critical_24h', 'title' => 'Critical', 'color' => 'red'])
                @include('guardian::guardian.partials.metric-card', ['xValue' => 'data.summary.warning_24h', 'title' => 'Warning', 'color' => 'yellow'])
                @include('guardian::guardian.partials.metric-card', ['xValue' => 'data.summary.error_24h', 'title' => 'Errors', 'color' => 'orange'])
            </div>
        </template>
    </div>

    <!-- Filters -->
    <div class="gd-card">
        <div class="gd-card__body" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            @include('guardian::guardian.partials.date-filter')
            <select class="gd-btn" x-model="filters.status" @change="fetchData()">
                <option value="">All Statuses</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="ok">OK</option>
            </select>
            <select class="gd-btn" x-model="filters.type" @change="fetchData()">
                <option value="">All Types</option>
                <option value="exception">Exceptions</option>
                <option value="monitor">Monitor Alerts</option>
                <option value="check">Health Checks</option>
            </select>
        </div>
    </div>

    <div x-show="loading && !loaded">
        <div class="gd-skeleton-grid"><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--card"></div><div class="gd-skeleton gd-skeleton--chart"></div></div>
    </div>

    <div x-show="loaded" x-cloak>
        <div class="gd-card">
            <div class="gd-card__header">Alert History</div>
            <div class="gd-card__body" style="padding:0">
                <div class="gd-table-wrapper">
                    <table class="gd-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="alert in (data.alerts.data || [])" :key="alert.id">
                                <tr>
                                    <td style="white-space:nowrap;" x-text="formatDate(alert.notified_at || alert.created_at)"></td>
                                    <td>
                                        <span class="gd-badge" :class="statusBadgeClass(alert.status)" x-text="alert.status"></span>
                                    </td>
                                    <td class="gd-mono gd-truncate" style="max-width:200px;" x-text="formatSource(alert.check_class)"></td>
                                    <td class="gd-truncate" style="max-width:400px;" x-text="alert.message"></td>
                                    <td style="white-space:nowrap;">
                                        <button class="gd-btn gd-btn--sm" @click="toggleDetail(alert)" x-text="expandedId === alert.id ? 'Hide' : 'Details'"></button>
                                        <button class="gd-btn gd-btn--sm" @click="copyAsMarkdown(alert)" title="Copy as Markdown for AI">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            MD
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Expanded detail panel -->
                <template x-if="expandedAlert">
                    <div class="gd-card" style="margin:16px; background:var(--gd-card-bg); border:1px solid var(--gd-border);">
                        <div class="gd-card__header" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Alert Detail</span>
                            <button class="gd-btn gd-btn--sm" @click="copyAsMarkdown(expandedAlert)">Copy as Markdown for AI</button>
                        </div>
                        <div class="gd-card__body">
                            <div style="display:grid; grid-template-columns: 120px 1fr; gap:8px; font-size:13px;">
                                <strong>Status:</strong>
                                <span class="gd-badge" :class="statusBadgeClass(expandedAlert.status)" x-text="expandedAlert.status"></span>

                                <strong>Source:</strong>
                                <span class="gd-mono" x-text="expandedAlert.check_class"></span>

                                <strong>Message:</strong>
                                <span x-text="expandedAlert.message"></span>

                                <strong>Time:</strong>
                                <span x-text="formatDate(expandedAlert.notified_at || expandedAlert.created_at)"></span>

                                <template x-if="expandedAlert.metadata?.url">
                                    <strong>URL:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.url">
                                    <span class="gd-mono" x-text="expandedAlert.metadata.url"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.status_code">
                                    <strong>Status Code:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.status_code">
                                    <span x-text="expandedAlert.metadata.status_code"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.user">
                                    <strong>User:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.user">
                                    <span x-text="expandedAlert.metadata.user"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.ip">
                                    <strong>IP:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.ip">
                                    <span x-text="expandedAlert.metadata.ip"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.exception_class">
                                    <strong>Exception:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.exception_class">
                                    <span class="gd-mono" x-text="expandedAlert.metadata.exception_class"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.file">
                                    <strong>File:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.file">
                                    <span class="gd-mono" x-text="expandedAlert.metadata.file + ':' + (expandedAlert.metadata.line || '')"></span>
                                </template>

                                <template x-if="expandedAlert.metadata?.headers && expandedAlert.metadata.headers !== 'N/A'">
                                    <strong>Headers:</strong>
                                </template>
                                <template x-if="expandedAlert.metadata?.headers && expandedAlert.metadata.headers !== 'N/A'">
                                    <pre class="gd-mono" style="margin:0; white-space:pre-wrap; font-size:12px;" x-text="expandedAlert.metadata.headers"></pre>
                                </template>
                            </div>

                            <template x-if="expandedAlert.metadata?.stack_trace">
                                <div style="margin-top:12px;">
                                    <strong>Stack Trace:</strong>
                                    <pre class="gd-mono" style="margin-top:4px; padding:12px; border-radius:6px; background:var(--gd-bg); font-size:11px; overflow-x:auto; white-space:pre-wrap;" x-text="expandedAlert.metadata.stack_trace"></pre>
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
