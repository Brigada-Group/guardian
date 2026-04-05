<!DOCTYPE html>
<html lang="en" x-data="guardianApp()" :class="{ 'gd-dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guardian Dashboard</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --gd-sidebar-width: 240px;
            --gd-sidebar-bg: #0f172a;
            --gd-sidebar-text: #94a3b8;
            --gd-sidebar-active: #38bdf8;
            --gd-sidebar-hover: #1e293b;
            --gd-bg: #f8fafc;
            --gd-surface: #ffffff;
            --gd-border: #e2e8f0;
            --gd-text: #0f172a;
            --gd-text-secondary: #64748b;
            --gd-accent: #3b82f6;
            --gd-accent-hover: #2563eb;
            --gd-success: #10b981;
            --gd-warning: #f59e0b;
            --gd-danger: #ef4444;
            --gd-info: #06b6d4;
            --gd-shadow: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
            --gd-shadow-lg: 0 4px 12px rgba(0,0,0,.1);
            --gd-radius: 8px;
        }
        .gd-dark {
            --gd-bg: #0f172a;
            --gd-surface: #1e293b;
            --gd-border: #334155;
            --gd-text: #f1f5f9;
            --gd-text-secondary: #94a3b8;
            --gd-shadow: 0 1px 3px rgba(0,0,0,.3);
            --gd-shadow-lg: 0 4px 12px rgba(0,0,0,.4);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--gd-bg);
            color: var(--gd-text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }

        /* Sidebar */
        .gd-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--gd-sidebar-width);
            background: var(--gd-sidebar-bg);
            color: var(--gd-sidebar-text);
            display: flex; flex-direction: column;
            z-index: 100;
            transition: transform .2s ease;
            overflow-y: auto;
        }
        .gd-sidebar__brand {
            padding: 20px 20px 16px;
            font-size: 18px; font-weight: 700;
            color: #f1f5f9;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .gd-sidebar__brand svg { width: 24px; height: 24px; }
        .gd-sidebar__nav { padding: 12px 8px; flex: 1; }
        .gd-sidebar__link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 14px; border-radius: 6px;
            font-size: 13.5px; font-weight: 500;
            color: var(--gd-sidebar-text);
            transition: all .15s ease;
            margin-bottom: 2px;
        }
        .gd-sidebar__link:hover { background: var(--gd-sidebar-hover); color: #e2e8f0; }
        .gd-sidebar__link.active { background: rgba(56,189,248,.1); color: var(--gd-sidebar-active); }
        .gd-sidebar__link svg { width: 18px; height: 18px; opacity: .7; flex-shrink: 0; }
        .gd-sidebar__link.active svg { opacity: 1; }
        .gd-sidebar__section {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: #475569;
            padding: 16px 14px 6px;
        }

        /* Main */
        .gd-main {
            margin-left: var(--gd-sidebar-width);
            min-height: 100vh;
        }

        /* Top bar */
        .gd-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 28px;
            background: var(--gd-surface);
            border-bottom: 1px solid var(--gd-border);
            position: sticky; top: 0; z-index: 50;
        }
        .gd-topbar__title { font-size: 16px; font-weight: 700; }
        .gd-topbar__actions { display: flex; align-items: center; gap: 12px; }

        /* Poll indicator */
        .gd-poll-indicator {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--gd-text-secondary);
        }
        .gd-poll-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--gd-success);
            animation: gd-pulse 2s ease-in-out infinite;
        }
        .gd-poll-dot.paused { background: var(--gd-warning); animation: none; }
        @keyframes gd-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }

        /* Buttons */
        .gd-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 6px;
            font-size: 12px; font-weight: 500;
            border: 1px solid var(--gd-border);
            background: var(--gd-surface);
            color: var(--gd-text);
            cursor: pointer; transition: all .15s;
        }
        .gd-btn:hover { border-color: var(--gd-accent); color: var(--gd-accent); }
        .gd-btn--primary { background: var(--gd-accent); color: #fff; border-color: var(--gd-accent); }
        .gd-btn--primary:hover { background: var(--gd-accent-hover); }
        .gd-btn--sm { padding: 4px 8px; font-size: 11px; }
        .gd-btn--danger { background: var(--gd-danger); color: #fff; border-color: var(--gd-danger); }

        /* Content */
        .gd-content { padding: 24px 28px; }

        /* Cards */
        .gd-card {
            background: var(--gd-surface);
            border: 1px solid var(--gd-border);
            border-radius: var(--gd-radius);
            box-shadow: var(--gd-shadow);
            margin-bottom: 20px;
        }
        .gd-card__header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--gd-border);
            font-size: 14px; font-weight: 600;
        }
        .gd-card__body { padding: 20px; }

        /* Metric cards */
        .gd-metrics { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .gd-metric-card {
            background: var(--gd-surface);
            border: 1px solid var(--gd-border);
            border-radius: var(--gd-radius);
            padding: 18px 20px;
            box-shadow: var(--gd-shadow);
        }
        .gd-metric-card__label { font-size: 12px; font-weight: 600; color: var(--gd-text-secondary); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
        .gd-metric-card__value { font-size: 28px; font-weight: 700; line-height: 1.2; }
        .gd-metric-card__subtitle { font-size: 12px; color: var(--gd-text-secondary); margin-top: 4px; }
        .gd-metric-card--alert { border-left: 3px solid var(--gd-danger); }

        /* Tables */
        .gd-table-wrapper { overflow-x: auto; }
        .gd-table {
            width: 100%; border-collapse: collapse; font-size: 13px;
        }
        .gd-table th {
            text-align: left; padding: 10px 14px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: var(--gd-text-secondary);
            background: var(--gd-bg);
            border-bottom: 1px solid var(--gd-border);
            white-space: nowrap;
        }
        .gd-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--gd-border);
            vertical-align: top;
        }
        .gd-table tbody tr:nth-child(even) td { background: rgba(0,0,0,.015); }
        .gd-dark .gd-table tbody tr:nth-child(even) td { background: rgba(255,255,255,.02); }
        .gd-table tbody tr:hover td { background: rgba(59,130,246,.04); }

        /* Status badges */
        .gd-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 10px;
            font-size: 11px; font-weight: 600;
        }
        .gd-badge--ok, .gd-badge--success { background: rgba(16,185,129,.1); color: #059669; }
        .gd-badge--warning { background: rgba(245,158,11,.1); color: #d97706; }
        .gd-badge--critical, .gd-badge--danger, .gd-badge--failed { background: rgba(239,68,68,.1); color: #dc2626; }
        .gd-badge--info { background: rgba(6,182,212,.1); color: #0891b2; }
        .gd-badge--unknown { background: rgba(100,116,139,.1); color: #64748b; }
        .gd-dark .gd-badge--ok, .gd-dark .gd-badge--success { background: rgba(16,185,129,.15); color: #34d399; }
        .gd-dark .gd-badge--warning { background: rgba(245,158,11,.15); color: #fbbf24; }
        .gd-dark .gd-badge--critical, .gd-dark .gd-badge--danger, .gd-dark .gd-badge--failed { background: rgba(239,68,68,.15); color: #f87171; }

        /* Charts */
        .gd-chart-container { position: relative; height: 260px; }

        /* Grid */
        .gd-grid { display: grid; gap: 20px; }
        .gd-grid--2 { grid-template-columns: repeat(2, 1fr); }
        .gd-grid--3 { grid-template-columns: repeat(3, 1fr); }
        @media (max-width: 1024px) {
            .gd-grid--2, .gd-grid--3 { grid-template-columns: 1fr; }
        }

        /* Date filter */
        .gd-date-filter { display: flex; gap: 4px; }
        .gd-date-filter__btn {
            padding: 4px 10px; border-radius: 4px;
            font-size: 11px; font-weight: 600;
            border: 1px solid var(--gd-border);
            background: var(--gd-surface);
            color: var(--gd-text-secondary);
            cursor: pointer; transition: all .15s;
        }
        .gd-date-filter__btn:hover { border-color: var(--gd-accent); color: var(--gd-accent); }
        .gd-date-filter__btn.active { background: var(--gd-accent); color: #fff; border-color: var(--gd-accent); }

        /* Tabs */
        .gd-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--gd-border); margin-bottom: 20px; }
        .gd-tab {
            padding: 10px 18px; font-size: 13px; font-weight: 600;
            color: var(--gd-text-secondary); cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all .15s;
        }
        .gd-tab:hover { color: var(--gd-text); }
        .gd-tab.active { color: var(--gd-accent); border-bottom-color: var(--gd-accent); }

        /* Loading */
        .gd-loading {
            display: flex; align-items: center; justify-content: center;
            padding: 60px 20px; color: var(--gd-text-secondary); font-size: 14px;
        }
        .gd-spinner {
            width: 20px; height: 20px; border: 2px solid var(--gd-border);
            border-top-color: var(--gd-accent); border-radius: 50%;
            animation: gd-spin .6s linear infinite; margin-right: 10px;
        }
        @keyframes gd-spin { to { transform: rotate(360deg); } }

        /* Pagination */
        .gd-pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; font-size: 13px; color: var(--gd-text-secondary); }
        .gd-pagination__links { display: flex; gap: 4px; }
        .gd-pagination__link {
            padding: 4px 10px; border-radius: 4px;
            border: 1px solid var(--gd-border);
            background: var(--gd-surface);
            color: var(--gd-text); cursor: pointer; font-size: 12px;
        }
        .gd-pagination__link:hover { border-color: var(--gd-accent); }
        .gd-pagination__link.active { background: var(--gd-accent); color: #fff; border-color: var(--gd-accent); }
        .gd-pagination__link:disabled { opacity: .4; cursor: not-allowed; }

        /* Expandable row */
        .gd-expandable { cursor: pointer; }
        .gd-expandable__content {
            padding: 12px 14px; background: var(--gd-bg);
            font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px;
            white-space: pre-wrap; word-break: break-all;
            border-bottom: 1px solid var(--gd-border);
        }

        /* Empty state */
        .gd-empty { text-align: center; padding: 48px 20px; color: var(--gd-text-secondary); }
        .gd-empty__icon { font-size: 36px; margin-bottom: 12px; opacity: .5; }
        .gd-empty__text { font-size: 14px; }

        /* Mobile sidebar toggle */
        .gd-mobile-toggle {
            display: none; position: fixed; top: 12px; left: 12px; z-index: 200;
            background: var(--gd-sidebar-bg); color: #f1f5f9;
            width: 36px; height: 36px; border-radius: 8px;
            border: none; cursor: pointer;
            align-items: center; justify-content: center;
        }
        @media (max-width: 768px) {
            .gd-mobile-toggle { display: flex; }
            .gd-sidebar { transform: translateX(-100%); }
            .gd-sidebar.open { transform: translateX(0); }
            .gd-main { margin-left: 0; }
            .gd-topbar { padding-left: 56px; }
        }

        /* Mono text */
        .gd-mono { font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace; font-size: 12px; }

        /* Truncate */
        .gd-truncate { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Status colors for health */
        .gd-status-ok { color: var(--gd-success); }
        .gd-status-warning { color: var(--gd-warning); }
        .gd-status-critical { color: var(--gd-danger); }
        .gd-status-unknown { color: var(--gd-text-secondary); }

        /* Health grid */
        .gd-health-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .gd-health-card {
            background: var(--gd-surface); border: 1px solid var(--gd-border);
            border-radius: var(--gd-radius); padding: 18px 20px;
            box-shadow: var(--gd-shadow);
            display: flex; gap: 14px; align-items: flex-start;
        }
        .gd-health-card__icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .gd-health-card__icon--ok { background: rgba(16,185,129,.1); color: var(--gd-success); }
        .gd-health-card__icon--warning { background: rgba(245,158,11,.1); color: var(--gd-warning); }
        .gd-health-card__icon--critical { background: rgba(239,68,68,.1); color: var(--gd-danger); }
        .gd-health-card__icon--unknown { background: rgba(100,116,139,.1); color: var(--gd-text-secondary); }
        .gd-health-card__body { flex: 1; min-width: 0; }
        .gd-health-card__name { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
        .gd-health-card__message { font-size: 12px; color: var(--gd-text-secondary); margin-bottom: 6px; }
        .gd-health-card__meta { font-size: 11px; color: var(--gd-text-secondary); display: flex; gap: 12px; }
    </style>
</head>
<body>
    <!-- Mobile toggle -->
    <button class="gd-mobile-toggle" @click="sidebarOpen = !sidebarOpen">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>

    <!-- Sidebar -->
    <aside class="gd-sidebar" :class="{ 'open': sidebarOpen }">
        <div class="gd-sidebar__brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Guardian
        </div>
        <nav class="gd-sidebar__nav">
            <div class="gd-sidebar__section">Monitor</div>
            <a href="{{ route('guardian.overview') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.overview') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                Overview
            </a>
            <a href="{{ route('guardian.requests') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.requests') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M9 12l2 2 4-4"/></svg>
                Requests
            </a>
            <a href="{{ route('guardian.queries') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.queries') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                Queries
            </a>
            <a href="{{ route('guardian.outgoing-http') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.outgoing-http') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                Outgoing HTTP
            </a>
            <a href="{{ route('guardian.jobs') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.jobs') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                Jobs
            </a>

            <div class="gd-sidebar__section">Communication</div>
            <a href="{{ route('guardian.mail') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.mail') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 4L12 13 2 4"/></svg>
                Mail
            </a>
            <a href="{{ route('guardian.notifications') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.notifications') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                Notifications
            </a>

            <div class="gd-sidebar__section">Infrastructure</div>
            <a href="{{ route('guardian.cache') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.cache') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                Cache
            </a>
            <a href="{{ route('guardian.exceptions') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.exceptions') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Exceptions
            </a>
            <a href="{{ route('guardian.alerts') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.alerts') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Alerts
            </a>
            <a href="{{ route('guardian.health') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.health') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Health Checks
            </a>
        </nav>
    </aside>

    <!-- Main -->
    <div class="gd-main">
        <header class="gd-topbar">
            <div class="gd-topbar__title">@yield('page-title', 'Dashboard')</div>
            <div class="gd-topbar__actions">
                <div class="gd-poll-indicator" x-show="polling">
                    <div class="gd-poll-dot" :class="{ 'paused': pollPaused }"></div>
                    <span x-text="pollPaused ? 'Paused' : 'Live'"></span>
                </div>
                <button class="gd-btn gd-btn--sm" @click="togglePoll()" x-text="pollPaused ? 'Resume' : 'Pause'"></button>
                <button class="gd-btn gd-btn--sm" @click="darkMode = !darkMode">
                    <span x-text="darkMode ? 'Light' : 'Dark'"></span>
                </button>
            </div>
        </header>
        <main class="gd-content">
            @yield('content')
        </main>
    </div>

    <script>
        function guardianApp() {
            return {
                darkMode: localStorage.getItem('guardian_dark') === 'true',
                sidebarOpen: false,
                polling: true,
                pollPaused: false,
                pollTimer: null,
                idleTimer: null,
                idleTimeout: 30 * 60 * 1000, // 30 min

                init() {
                    this.$watch('darkMode', (v) => localStorage.setItem('guardian_dark', v));

                    // Stop polling when tab is hidden
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            this.stopPoll();
                        } else if (!this.pollPaused) {
                            this.startPoll();
                        }
                    });

                    // Idle detection
                    ['mousemove', 'keydown', 'click', 'scroll'].forEach(evt => {
                        document.addEventListener(evt, () => this.resetIdleTimer(), { passive: true });
                    });
                    this.resetIdleTimer();
                },

                startPoll() {
                    this.polling = true;
                },
                stopPoll() {
                    this.polling = false;
                },
                togglePoll() {
                    this.pollPaused = !this.pollPaused;
                    if (this.pollPaused) {
                        this.stopPoll();
                    } else {
                        this.startPoll();
                        this.resetIdleTimer();
                    }
                },
                resetIdleTimer() {
                    clearTimeout(this.idleTimer);
                    if (!this.pollPaused) {
                        this.polling = true;
                    }
                    this.idleTimer = setTimeout(() => {
                        this.polling = false;
                        this.pollPaused = true;
                    }, this.idleTimeout);
                },
            };
        }

        // Shared chart defaults
        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.plugins.legend.display = false;
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Shared helpers
        function guardianFetch(url, params = {}) {
            const qs = new URLSearchParams(params).toString();
            const fullUrl = qs ? `${url}?${qs}` : url;
            return fetch(fullUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }).then(r => r.json());
        }

        function formatMs(ms) {
            if (ms === null || ms === undefined) return '-';
            if (ms < 1) return '<1ms';
            if (ms < 1000) return Math.round(ms) + 'ms';
            return (ms / 1000).toFixed(2) + 's';
        }

        function formatDate(iso) {
            if (!iso) return '-';
            const d = new Date(iso);
            return d.toLocaleString();
        }

        function formatDateShort(iso) {
            if (!iso) return '-';
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function dateRangeToParams(range) {
            const now = new Date();
            const map = { '1h': 1, '6h': 6, '24h': 24, '7d': 168, '30d': 720 };
            const hours = map[range] || 24;
            const from = new Date(now.getTime() - hours * 60 * 60 * 1000);
            return { date_from: from.toISOString(), date_to: now.toISOString() };
        }

        function getChartColors(isDark) {
            return {
                grid: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)',
                text: isDark ? '#94a3b8' : '#64748b',
                blue: '#3b82f6',
                green: '#10b981',
                red: '#ef4444',
                yellow: '#f59e0b',
                cyan: '#06b6d4',
                purple: '#8b5cf6',
            };
        }

        // Safe Chart.js wrapper — handles null canvas from x-if DOM recreation
        window.SafeChart = function(ctx, config) {
            if (!ctx || !ctx.getContext) return null;
            try {
                return new Chart(ctx, config);
            } catch(e) {
                return null;
            }
        };
        window.destroyChart = function(chart) {
            if (chart) { try { chart.destroy(); } catch(e) {} }
            return null;
        };

        function statusBadgeClass(status) {
            const map = { ok: 'gd-badge--ok', success: 'gd-badge--success', warning: 'gd-badge--warning', critical: 'gd-badge--critical', failed: 'gd-badge--failed', danger: 'gd-badge--danger', info: 'gd-badge--info' };
            return map[status] || 'gd-badge--unknown';
        }
    </script>
    @yield('scripts')
</body>
</html>
