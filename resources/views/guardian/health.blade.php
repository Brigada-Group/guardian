@extends('guardian::guardian.layout')

@section('page-title', 'Health Checks')

@section('content')
<div x-data="healthPage()" x-init="init()" class="space-y-6">
    <!-- Loading -->
    <div x-show="loading && !loaded">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
            <div class="gd-skeleton gd-skeleton--card"></div>
        </div>
    </div>

    <div x-show="loaded" x-cloak class="space-y-6">
        <!-- Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="gd-stat-card">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#6366f1"></span>
                    <span class="gd-stat-card__label">Total Checks</span>
                </div>
                <div class="gd-stat-card__value" x-text="data?.checks?.length ?? 0"></div>
            </div>
            <div class="gd-stat-card">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#22c55e"></span>
                    <span class="gd-stat-card__label">Passing</span>
                </div>
                <div class="gd-stat-card__value" x-text="(data?.checks ?? []).filter(c => c.status === 'ok').length"></div>
            </div>
            <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': (data?.checks ?? []).filter(c => c.status === 'warning').length > 0 }">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#eab308"></span>
                    <span class="gd-stat-card__label">Warnings</span>
                </div>
                <div class="gd-stat-card__value" x-text="(data?.checks ?? []).filter(c => c.status === 'warning').length"></div>
            </div>
            <div class="gd-stat-card" :class="{ 'gd-stat-card--alert': (data?.checks ?? []).filter(c => c.status === 'critical' || c.status === 'error').length > 0 }">
                <div class="gd-stat-card__header">
                    <span class="gd-stat-card__dot" style="background:#ef4444"></span>
                    <span class="gd-stat-card__label">Critical / Error</span>
                </div>
                <div class="gd-stat-card__value" x-text="(data?.checks ?? []).filter(c => c.status === 'critical' || c.status === 'error').length"></div>
            </div>
        </div>

        <!-- Health check grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="check in (data?.checks ?? [])" :key="check.class">
                <div class="gd-card" style="cursor:pointer;" @click="openModal(check)">
                    <div class="gd-card__body" style="display:flex; align-items:flex-start; gap:12px;">
                        <span class="gd-status-dot" :class="'gd-status-dot--' + check.status" style="margin-top:5px; flex-shrink:0;"></span>
                        <div style="min-width:0; flex:1;">
                            <div style="font-size:13px; font-weight:600;" x-text="check.name"></div>
                            <div style="font-size:12px; color:#64748b; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="check.message"></div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:4px;" x-text="check.last_run ? formatDate(check.last_run) : 'Never run'"></div>
                        </div>
                        <span class="gd-badge" :class="statusBadgeClass(check.status)" x-text="check.status" style="flex-shrink:0;"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Modal -->
    <template x-if="modalCheck">
        <div style="position:fixed; inset:0; z-index:999; display:flex; align-items:center; justify-content:center; padding:16px;" @click.self="modalCheck = null">
            <!-- Backdrop -->
            <div style="position:absolute; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px);"></div>
            <!-- Modal card -->
            <div style="position:relative; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; background:white; border-radius:12px; border:1px solid hsl(214.3 31.8% 91.4%); box-shadow:0 25px 50px -12px rgba(0,0,0,.25);">
                <!-- Header -->
                <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid hsl(214.3 31.8% 91.4%);">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="gd-status-dot" :class="'gd-status-dot--' + modalCheck.status"></span>
                        <span style="font-size:15px; font-weight:600;" x-text="modalCheck.name"></span>
                    </div>
                    <button @click="modalCheck = null" style="padding:4px; border-radius:6px; color:#64748b; cursor:pointer; border:none; background:none;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Body -->
                <div style="padding:24px; display:flex; flex-direction:column; gap:16px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em;">Status</span>
                        <span class="gd-badge" :class="statusBadgeClass(modalCheck.status)" x-text="modalCheck.status"></span>
                    </div>

                    <div>
                        <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px;">Message</div>
                        <div style="font-size:13px; line-height:1.6; padding:12px; border-radius:8px; background:#f8fafc; border:1px solid hsl(214.3 31.8% 91.4%); word-break:break-word;" x-text="modalCheck.message"></div>
                    </div>

                    <div>
                        <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Schedule</div>
                        <div style="font-size:13px;" x-text="modalCheck.schedule"></div>
                    </div>

                    <div>
                        <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Last Run</div>
                        <div style="font-size:13px;" x-text="modalCheck.last_run ? formatDate(modalCheck.last_run) : 'Never'"></div>
                    </div>

                    <div>
                        <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Check Class</div>
                        <div class="gd-mono" style="font-size:11px; color:#64748b; word-break:break-all;" x-text="modalCheck.class"></div>
                    </div>
                </div>
                <!-- Footer -->
                <div style="display:flex; justify-content:flex-end; gap:8px; padding:16px 24px; border-top:1px solid hsl(214.3 31.8% 91.4%);">
                    <button class="gd-btn" @click="modalCheck = null">Close</button>
                    <button class="gd-btn gd-btn--primary" @click="runCheck(modalCheck)" :disabled="running">
                        <span x-show="!running">Run Now</span>
                        <span x-show="running">Running...</span>
                    </button>
                </div>
                <!-- Run result -->
                <template x-if="runResult">
                    <div style="padding:0 24px 20px 24px;">
                        <div style="padding:12px; border-radius:8px; border:1px solid hsl(214.3 31.8% 91.4%);" :style="runResult.status === 'ok' ? 'background:#f0fdf4; border-color:#bbf7d0;' : runResult.status === 'warning' ? 'background:#fefce8; border-color:#fef08a;' : 'background:#fef2f2; border-color:#fecaca;'">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <span class="gd-badge" :class="statusBadgeClass(runResult.status)" x-text="runResult.status"></span>
                                <span style="font-size:12px; font-weight:600;">Run Result</span>
                            </div>
                            <div style="font-size:13px;" x-text="runResult.message"></div>
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
        data: null, loaded: false,
        pollInterval: {{ $pollInterval }} * 1000,
        modalCheck: null,
        running: false,
        runResult: null,

        async init() {
            await this.fetchData();
            this.startPolling();
        },

        async fetchData() {
            this.loading = true;
            try {
                const res = await guardianFetch('{{ route("guardian.api.health") }}');
                this.data = res.data; this.loaded = true;
            } catch (e) { console.error('Health fetch failed', e); }
            this.loading = false;
        },

        startPolling() {
            setInterval(() => {
                const app = Alpine.evaluate(document.documentElement, '$data');
                if (app && app.polling && !app.pollPaused) this.fetchData();
            }, this.pollInterval);
        },

        openModal(check) {
            this.modalCheck = check;
            this.runResult = null;
        },

        async runCheck(check) {
            this.running = true;
            this.runResult = null;
            try {
                const className = check.class.split('\\').pop();
                const res = await fetch('{{ url(config("guardian.dashboard.path", "guardian")) }}/api/health/run/' + className, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                this.runResult = json.data || json;
                this.fetchData();
            } catch (e) {
                this.runResult = { status: 'error', message: 'Failed to run check: ' + e.message };
            }
            this.running = false;
        },
    };
}
</script>
@endsection
