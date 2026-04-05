@extends('guardian::guardian.layout')

@section('page-title', 'Health Checks')

@section('content')
<div x-data="healthPage()" x-init="init()">
    <template x-if="loading && !data">
        <div class="gd-loading"><div class="gd-spinner"></div> Loading health check data...</div>
    </template>

    <template x-if="data">
        <div>
            <!-- Summary -->
            <div class="gd-metrics">
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Total Checks</div>
                    <div class="gd-metric-card__value" x-text="data.checks.length"></div>
                </div>
                <div class="gd-metric-card">
                    <div class="gd-metric-card__label">Passing</div>
                    <div class="gd-metric-card__value gd-status-ok" x-text="data.checks.filter(c => c.status === 'ok').length"></div>
                </div>
                <div class="gd-metric-card" :class="{ 'gd-metric-card--alert': data.checks.filter(c => c.status === 'warning').length > 0 }">
                    <div class="gd-metric-card__label">Warnings</div>
                    <div class="gd-metric-card__value gd-status-warning" x-text="data.checks.filter(c => c.status === 'warning').length"></div>
                </div>
                <div class="gd-metric-card" :class="{ 'gd-metric-card--alert': data.checks.filter(c => c.status === 'critical').length > 0 }">
                    <div class="gd-metric-card__label">Critical</div>
                    <div class="gd-metric-card__value gd-status-critical" x-text="data.checks.filter(c => c.status === 'critical').length"></div>
                </div>
            </div>

            <!-- Health check grid -->
            <div class="gd-health-grid">
                <template x-for="check in data.checks" :key="check.class">
                    <div class="gd-health-card">
                        <div class="gd-health-card__icon" :class="{
                            'gd-health-card__icon--ok': check.status === 'ok',
                            'gd-health-card__icon--warning': check.status === 'warning',
                            'gd-health-card__icon--critical': check.status === 'critical',
                            'gd-health-card__icon--unknown': !['ok','warning','critical'].includes(check.status),
                        }">
                            <template x-if="check.status === 'ok'">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                            </template>
                            <template x-if="check.status === 'warning'">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            </template>
                            <template x-if="check.status === 'critical'">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </template>
                            <template x-if="!['ok','warning','critical'].includes(check.status)">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </template>
                        </div>
                        <div class="gd-health-card__body">
                            <div class="gd-health-card__name" x-text="check.name"></div>
                            <div class="gd-health-card__message" x-text="check.message"></div>
                            <div class="gd-health-card__meta">
                                <span x-text="'Schedule: ' + check.schedule"></span>
                                <span x-text="check.last_run ? 'Last: ' + formatDate(check.last_run) : 'Never run'"></span>
                            </div>
                            <div style="margin-top:8px">
                                <button class="gd-btn gd-btn--sm gd-btn--primary"
                                    :disabled="runningCheck === check.class"
                                    @click="runCheck(check)">
                                    <span x-text="runningCheck === check.class ? 'Running...' : 'Run Now'"></span>
                                </button>
                                <template x-if="runResult && runResult.class === check.class">
                                    <span class="gd-badge" :class="statusBadgeClass(runResult.status)" x-text="runResult.status + ': ' + runResult.message" style="margin-left:8px"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
@endsection

@section('scripts')
<script>
function healthPage() {
    return {
        loading: true,
        data: null,
        runningCheck: null,
        runResult: null,
        pollInterval: {{ $pollInterval }} * 1000,

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        async fetchData() {
            this.loading = true;
            try {
                const res = await guardianFetch('{{ route("guardian.api.health") }}');
                this.data = res.data;
            } catch (e) { console.error('Health fetch failed', e); }
            this.loading = false;
        },

        async runCheck(check) {
            this.runningCheck = check.class;
            this.runResult = null;
            try {
                // Extract class basename
                const parts = check.class.split('\\');
                const basename = parts[parts.length - 1];
                const res = await fetch('{{ url(config("guardian.dashboard.path", "guardian")) }}/api/health/run/' + basename, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.data) {
                    this.runResult = { class: check.class, status: data.data.status, message: data.data.message };
                }
            } catch (e) {
                this.runResult = { class: check.class, status: 'critical', message: 'Failed to run check' };
            }
            this.runningCheck = null;
        },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },
    };
}
</script>
@endsection
