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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --gd-sidebar-width: 240px;
            --gd-sidebar-bg: #0c1222;
            --gd-sidebar-text: #7a8599;
            --gd-sidebar-active-bg: rgba(99, 102, 241, 0.12);
            --gd-sidebar-active: #818cf8;
            --gd-sidebar-hover: rgba(255,255,255,.04);
            --gd-sidebar-section: #4b5563;
            --gd-bg: #f1f5f9;
            --gd-surface: #ffffff;
            --gd-border: #e5e7eb;
            --gd-text: #111827;
            --gd-text-secondary: #64748b;
            --gd-accent: #6366f1;
            --gd-accent-hover: #4f46e5;
            --gd-success: #22c55e;
            --gd-warning: #eab308;
            --gd-danger: #ef4444;
            --gd-info: #3b82f6;
            --gd-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.02);
            --gd-shadow-hover: 0 4px 12px rgba(0,0,0,.06), 0 2px 4px rgba(0,0,0,.04);
            --gd-radius: 12px;
            --gd-radius-sm: 6px;
            --gd-topbar-height: 56px;
        }
        .gd-dark {
            --gd-bg: #0a0f1a;
            --gd-surface: #141b2d;
            --gd-border: #1e293b;
            --gd-text: #f1f5f9;
            --gd-text-secondary: #94a3b8;
            --gd-shadow: 0 1px 3px rgba(0,0,0,.2), 0 1px 2px rgba(0,0,0,.12);
            --gd-shadow-hover: 0 4px 12px rgba(0,0,0,.3);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--gd-bg);
            color: var(--gd-text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { color: inherit; text-decoration: none; }

        /* ============ SIDEBAR ============ */
        .gd-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--gd-sidebar-width);
            background: var(--gd-sidebar-bg);
            color: var(--gd-sidebar-text);
            display: flex; flex-direction: column;
            z-index: 100;
            transition: transform .2s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .gd-sidebar__brand {
            padding: 16px 18px;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .gd-sidebar__brand-icon {
            width: 32px; height: 32px;
            background: rgba(99,102,241,.15);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .gd-sidebar__brand-icon svg { width: 18px; height: 18px; color: #818cf8; }
        .gd-sidebar__brand-text {
            font-size: 15px; font-weight: 700; color: #f1f5f9;
            letter-spacing: -0.01em;
        }

        .gd-sidebar__nav { padding: 8px 8px; flex: 1; }
        .gd-sidebar__section {
            font-size: 10px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .08em; color: var(--gd-sidebar-section);
            padding: 20px 12px 6px;
        }
        .gd-sidebar__section:first-child { padding-top: 12px; }
        .gd-sidebar__link {
            display: flex; align-items: center; gap: 10px;
            padding: 0 12px; height: 36px;
            border-radius: var(--gd-radius-sm);
            font-size: 13px; font-weight: 500;
            color: var(--gd-sidebar-text);
            transition: all .15s ease;
            margin-bottom: 1px;
            border-left: 2px solid transparent;
        }
        .gd-sidebar__link:hover {
            background: var(--gd-sidebar-hover);
            color: #c5cad3;
        }
        .gd-sidebar__link.active {
            background: var(--gd-sidebar-active-bg);
            color: var(--gd-sidebar-active);
            border-left-color: var(--gd-sidebar-active);
        }
        .gd-sidebar__link svg {
            width: 16px; height: 16px; opacity: .55; flex-shrink: 0;
        }
        .gd-sidebar__link.active svg { opacity: 1; color: var(--gd-sidebar-active); }

        .gd-sidebar__footer {
            padding: 16px 18px;
            border-top: 1px solid rgba(255,255,255,.06);
            font-size: 11px; color: #4b5563;
        }
        .gd-sidebar__footer-project {
            font-weight: 600; color: #94a3b8; margin-bottom: 4px;
        }
        .gd-sidebar__footer-meta {
            display: flex; align-items: center; gap: 8px;
        }

        /* ============ MAIN ============ */
        .gd-main {
            margin-left: var(--gd-sidebar-width);
            min-height: 100vh;
        }

        /* ============ TOP BAR ============ */
        .gd-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px;
            height: var(--gd-topbar-height);
            background: var(--gd-surface);
            border-bottom: 1px solid var(--gd-border);
            box-shadow: 0 1px 2px rgba(0,0,0,.03);
            position: sticky; top: 0; z-index: 50;
        }
        .gd-topbar__left {
            display: flex; align-items: center; gap: 12px;
        }
        .gd-topbar__title {
            font-size: 15px; font-weight: 600; letter-spacing: -0.01em;
        }
        .gd-topbar__actions {
            display: flex; align-items: center; gap: 16px;
        }

        /* Poll / Live indicator */
        .gd-live-indicator {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 500; color: var(--gd-text-secondary);
        }
        .gd-live-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--gd-success);
            box-shadow: 0 0 0 3px rgba(34,197,94,.2);
            animation: gd-live-pulse 2s ease-in-out infinite;
        }
        .gd-live-dot.paused {
            background: #9ca3af;
            box-shadow: none;
            animation: none;
        }
        @keyframes gd-live-pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
            50% { box-shadow: 0 0 0 6px rgba(34,197,94,.05); }
        }

        /* Icon buttons in topbar */
        .gd-icon-btn {
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; border: 1px solid var(--gd-border);
            background: var(--gd-surface); color: var(--gd-text-secondary);
            cursor: pointer; transition: all .15s ease;
        }
        .gd-icon-btn:hover {
            border-color: var(--gd-accent); color: var(--gd-accent);
            background: rgba(99,102,241,.04);
        }
        .gd-icon-btn svg { width: 16px; height: 16px; }

        /* ============ BUTTONS ============ */
        .gd-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: var(--gd-radius-sm);
            font-size: 12px; font-weight: 500; font-family: inherit;
            border: 1px solid var(--gd-border);
            background: var(--gd-surface);
            color: var(--gd-text);
            cursor: pointer; transition: all .15s ease;
        }
        .gd-btn:hover { border-color: var(--gd-accent); color: var(--gd-accent); }
        .gd-btn--primary { background: var(--gd-accent); color: #fff; border-color: var(--gd-accent); }
        .gd-btn--primary:hover { background: var(--gd-accent-hover); }
        .gd-btn--sm { padding: 4px 10px; font-size: 11px; }
        .gd-btn--danger { background: var(--gd-danger); color: #fff; border-color: var(--gd-danger); }
        .gd-btn--copy { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; }

        /* ============ CONTENT ============ */
        .gd-content { padding: 24px 28px; }

        /* ============ CARDS ============ */
        .gd-card {
            background: var(--gd-surface);
            border-radius: var(--gd-radius);
            box-shadow: var(--gd-shadow);
            margin-bottom: 20px;
            transition: box-shadow .2s ease;
            border: 1px solid var(--gd-border);
        }
        .gd-card__header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--gd-border);
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: var(--gd-text-secondary);
        }
        .gd-card__body { padding: 20px; }

        /* ============ STAT / METRIC CARDS (Nightwatch style) ============ */
        .gd-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        @media (max-width: 1024px) { .gd-metrics { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .gd-metrics { grid-template-columns: 1fr; } }

        .gd-stat-card {
            background: var(--gd-surface);
            border-radius: var(--gd-radius);
            padding: 20px;
            box-shadow: var(--gd-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
            border: 1px solid var(--gd-border);
            border-left: 3px solid transparent;
        }
        .gd-stat-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--gd-shadow-hover);
        }
        .gd-stat-card--alert {
            border-left-color: var(--gd-danger);
        }
        .gd-stat-card__header {
            display: flex; align-items: center; gap: 8px; margin-bottom: 12px;
        }
        .gd-stat-card__dot {
            width: 4px; height: 4px; border-radius: 50%;
            flex-shrink: 0;
        }
        .gd-stat-card__label {
            font-size: 11px; font-weight: 500;
            color: var(--gd-text-secondary);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .gd-stat-card__value {
            font-size: 32px; font-weight: 600;
            line-height: 1.1; letter-spacing: -0.02em;
            color: var(--gd-text);
        }
        .gd-stat-card__trend {
            font-size: 12px; color: var(--gd-text-secondary);
            margin-top: 6px; font-weight: 400;
        }

        /* Legacy metric card class support for other pages */
        .gd-metric-card {
            background: var(--gd-surface);
            border-radius: var(--gd-radius);
            padding: 20px;
            box-shadow: var(--gd-shadow);
            transition: transform .2s ease, box-shadow .2s ease;
            border: 1px solid var(--gd-border);
            border-left: 3px solid transparent;
        }
        .gd-metric-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--gd-shadow-hover);
        }
        .gd-metric-card--alert { border-left-color: var(--gd-danger); }
        .gd-metric-card__label {
            font-size: 11px; font-weight: 500; color: var(--gd-text-secondary);
            text-transform: uppercase; letter-spacing: .04em; margin-bottom: 10px;
        }
        .gd-metric-card__value {
            font-size: 32px; font-weight: 600; line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .gd-metric-card__subtitle {
            font-size: 12px; color: var(--gd-text-secondary); margin-top: 6px;
        }

        /* ============ TABLES ============ */
        .gd-table-wrapper { overflow-x: auto; }
        .gd-table {
            width: 100%; border-collapse: collapse; font-size: 13px;
        }
        .gd-table th {
            text-align: left; padding: 10px 16px;
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: var(--gd-text-secondary);
            background: transparent;
            border-bottom: 1px solid var(--gd-border);
            white-space: nowrap;
            position: sticky; top: 0; z-index: 1;
        }
        .gd-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,.04);
            vertical-align: middle;
        }
        .gd-dark .gd-table td {
            border-bottom-color: rgba(255,255,255,.04);
        }
        .gd-table tbody tr {
            transition: background .15s ease;
            height: 44px;
        }
        .gd-table tbody tr:hover td {
            background: rgba(99,102,241,.03);
        }

        /* Status dots for tables */
        .gd-status-dot {
            display: inline-block;
            width: 8px; height: 8px; border-radius: 50%;
        }
        .gd-status-dot--ok, .gd-status-dot--success { background: var(--gd-success); }
        .gd-status-dot--warning { background: var(--gd-warning); }
        .gd-status-dot--critical, .gd-status-dot--danger, .gd-status-dot--failed { background: var(--gd-danger); }
        .gd-status-dot--info { background: var(--gd-info); }
        .gd-status-dot--unknown { background: #9ca3af; }

        /* ============ BADGES ============ */
        .gd-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: var(--gd-radius-sm);
            font-size: 10px; font-weight: 500;
            text-transform: uppercase; letter-spacing: .02em;
        }
        .gd-badge--ok, .gd-badge--success {
            background: rgba(34,197,94,.08); color: #15803d;
        }
        .gd-badge--warning {
            background: rgba(234,179,8,.08); color: #a16207;
        }
        .gd-badge--critical, .gd-badge--danger, .gd-badge--failed {
            background: rgba(239,68,68,.08); color: #dc2626;
        }
        .gd-badge--info {
            background: rgba(59,130,246,.08); color: #2563eb;
        }
        .gd-badge--unknown {
            background: rgba(100,116,139,.08); color: #64748b;
        }
        .gd-dark .gd-badge--ok, .gd-dark .gd-badge--success {
            background: rgba(34,197,94,.12); color: #4ade80;
        }
        .gd-dark .gd-badge--warning {
            background: rgba(234,179,8,.12); color: #facc15;
        }
        .gd-dark .gd-badge--critical, .gd-dark .gd-badge--danger, .gd-dark .gd-badge--failed {
            background: rgba(239,68,68,.12); color: #f87171;
        }
        .gd-dark .gd-badge--info {
            background: rgba(59,130,246,.12); color: #60a5fa;
        }

        /* Log level badge variants */
        .gd-badge--emergency, .gd-badge--alert, .gd-badge--critical {
            background: rgba(239,68,68,.1); color: #dc2626;
        }
        .gd-badge--error {
            background: rgba(234,88,12,.1); color: #ea580c;
        }
        .gd-badge--warning-solid {
            background: rgba(234,179,8,.1); color: #ca8a04;
        }
        .gd-badge--completed {
            background: rgba(34,197,94,.08); color: #15803d;
        }
        .gd-badge--processing {
            background: rgba(59,130,246,.08); color: #2563eb;
        }
        .gd-dark .gd-badge--emergency, .gd-dark .gd-badge--alert {
            background: rgba(239,68,68,.15); color: #f87171;
        }
        .gd-dark .gd-badge--error {
            background: rgba(234,88,12,.15); color: #fb923c;
        }
        .gd-dark .gd-badge--completed {
            background: rgba(34,197,94,.12); color: #4ade80;
        }
        .gd-dark .gd-badge--processing {
            background: rgba(59,130,246,.12); color: #60a5fa;
        }

        /* ============ CHARTS ============ */
        .gd-chart-container { position: relative; height: 260px; }

        /* ============ GRID ============ */
        .gd-grid { display: grid; gap: 20px; }
        .gd-grid--2 { grid-template-columns: repeat(2, 1fr); }
        .gd-grid--3 { grid-template-columns: repeat(3, 1fr); }
        @media (max-width: 1024px) {
            .gd-grid--2, .gd-grid--3 { grid-template-columns: 1fr; }
        }

        /* ============ DATE FILTER (segmented control style) ============ */
        .gd-date-filter {
            display: inline-flex; gap: 0;
            background: var(--gd-bg);
            border-radius: 8px; padding: 3px;
            border: 1px solid var(--gd-border);
        }
        .gd-date-filter__btn {
            padding: 4px 12px; border-radius: 6px;
            font-size: 11px; font-weight: 500; font-family: inherit;
            border: none;
            background: transparent;
            color: var(--gd-text-secondary);
            cursor: pointer; transition: all .15s ease;
        }
        .gd-date-filter__btn:hover {
            color: var(--gd-text);
        }
        .gd-date-filter__btn.active {
            background: var(--gd-surface);
            color: var(--gd-accent);
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            font-weight: 600;
        }

        /* ============ TABS (segmented control) ============ */
        .gd-tabs {
            display: inline-flex; gap: 0;
            background: var(--gd-bg);
            border-radius: 8px; padding: 3px;
            border: 1px solid var(--gd-border);
            margin-bottom: 20px;
        }
        .gd-tab {
            padding: 8px 18px; font-size: 13px; font-weight: 500;
            color: var(--gd-text-secondary); cursor: pointer;
            border-radius: 6px; border: none; background: transparent;
            transition: all .15s ease; font-family: inherit;
        }
        .gd-tab:hover { color: var(--gd-text); }
        .gd-tab.active {
            background: var(--gd-surface);
            color: var(--gd-accent);
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            font-weight: 600;
        }

        /* ============ LOADING / SKELETON ============ */
        .gd-loading {
            padding: 60px 20px;
        }
        .gd-skeleton-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        @media (max-width: 1024px) { .gd-skeleton-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .gd-skeleton-grid { grid-template-columns: 1fr; } }

        .gd-skeleton {
            border-radius: var(--gd-radius);
            background: linear-gradient(90deg, var(--gd-surface) 25%, var(--gd-border) 50%, var(--gd-surface) 75%);
            background-size: 200% 100%;
            animation: gd-shimmer 1.5s ease infinite;
        }
        .gd-skeleton--card { height: 110px; }
        .gd-skeleton--chart { height: 280px; border-radius: var(--gd-radius); }
        .gd-skeleton--row { height: 44px; margin-bottom: 4px; border-radius: 6px; }
        @keyframes gd-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Legacy spinner kept for compatibility */
        .gd-spinner {
            width: 20px; height: 20px; border: 2px solid var(--gd-border);
            border-top-color: var(--gd-accent); border-radius: 50%;
            animation: gd-spin .6s linear infinite; margin-right: 10px;
        }
        @keyframes gd-spin { to { transform: rotate(360deg); } }

        /* ============ PAGINATION ============ */
        .gd-pagination {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; font-size: 13px; color: var(--gd-text-secondary);
        }
        .gd-pagination__links { display: flex; gap: 4px; }
        .gd-pagination__link {
            padding: 4px 10px; border-radius: var(--gd-radius-sm);
            border: 1px solid var(--gd-border);
            background: var(--gd-surface);
            color: var(--gd-text); cursor: pointer; font-size: 12px;
            font-family: inherit; transition: all .15s ease;
        }
        .gd-pagination__link:hover { border-color: var(--gd-accent); color: var(--gd-accent); }
        .gd-pagination__link.active { background: var(--gd-accent); color: #fff; border-color: var(--gd-accent); }
        .gd-pagination__link:disabled { opacity: .4; cursor: not-allowed; }

        /* ============ EXPANDABLE ROW ============ */
        .gd-expandable { cursor: pointer; }
        .gd-expandable__content {
            padding: 14px 16px; background: var(--gd-bg);
            font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px;
            white-space: pre-wrap; word-break: break-all;
            border-bottom: 1px solid var(--gd-border);
        }
        .gd-expandable-section {
            overflow: hidden; transition: max-height .3s ease;
        }

        /* ============ EMPTY STATE ============ */
        .gd-empty {
            text-align: center; padding: 60px 20px;
            color: var(--gd-text-secondary);
        }
        .gd-empty svg {
            width: 40px; height: 40px; margin: 0 auto 14px;
            opacity: 0.3; display: block;
        }
        .gd-empty__icon { font-size: 36px; margin-bottom: 12px; opacity: .3; }
        .gd-empty__text { font-size: 14px; font-weight: 500; margin-bottom: 4px; }
        .gd-empty__hint { font-size: 12px; color: var(--gd-text-secondary); opacity: .7; }

        /* ============ ENVIRONMENT BADGE ============ */
        .gd-env-badge {
            font-size: 10px; padding: 2px 8px; border-radius: 4px;
            text-transform: uppercase; font-weight: 600;
            letter-spacing: .5px; display: inline-block;
            margin-left: 8px;
        }
        .gd-env-badge--production { background: rgba(239,68,68,.1); color: #dc2626; }
        .gd-env-badge--staging { background: rgba(234,179,8,.1); color: #ca8a04; }
        .gd-env-badge--local { background: rgba(99,102,241,.1); color: #6366f1; }
        .gd-env-badge--testing { background: rgba(139,92,246,.1); color: #8b5cf6; }
        .gd-dark .gd-env-badge--production { background: rgba(239,68,68,.15); color: #f87171; }
        .gd-dark .gd-env-badge--staging { background: rgba(234,179,8,.15); color: #facc15; }
        .gd-dark .gd-env-badge--local { background: rgba(99,102,241,.15); color: #a5b4fc; }
        .gd-dark .gd-env-badge--testing { background: rgba(139,92,246,.15); color: #c4b5fd; }

        /* ============ MOBILE ============ */
        .gd-mobile-toggle {
            display: none; position: fixed; top: 10px; left: 10px; z-index: 200;
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
            .gd-content { padding: 16px; }
            .gd-metrics { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }
        @media (max-width: 480px) {
            .gd-metrics { grid-template-columns: 1fr; }
        }

        /* ============ UTILITY ============ */
        .gd-mono { font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace; font-size: 12px; }
        .gd-truncate { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Status text colors */
        .gd-status-ok { color: var(--gd-success); }
        .gd-status-warning { color: var(--gd-warning); }
        .gd-status-critical { color: var(--gd-danger); }
        .gd-status-unknown { color: var(--gd-text-secondary); }

        /* ============ HEALTH GRID ============ */
        .gd-health-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .gd-health-card {
            background: var(--gd-surface); border: 1px solid var(--gd-border);
            border-radius: var(--gd-radius); padding: 18px 20px;
            box-shadow: var(--gd-shadow);
            display: flex; gap: 14px; align-items: flex-start;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .gd-health-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--gd-shadow-hover);
        }
        .gd-health-card__icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .gd-health-card__icon--ok { background: rgba(34,197,94,.08); color: var(--gd-success); }
        .gd-health-card__icon--warning { background: rgba(234,179,8,.08); color: var(--gd-warning); }
        .gd-health-card__icon--critical { background: rgba(239,68,68,.08); color: var(--gd-danger); }
        .gd-health-card__icon--unknown { background: rgba(100,116,139,.08); color: var(--gd-text-secondary); }
        .gd-health-card__body { flex: 1; min-width: 0; }
        .gd-health-card__name { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
        .gd-health-card__message { font-size: 12px; color: var(--gd-text-secondary); margin-bottom: 6px; }
        .gd-health-card__meta { font-size: 11px; color: var(--gd-text-secondary); display: flex; gap: 12px; }

        /* ============ FILTER INPUTS ============ */
        .gd-filter-input {
            padding: 6px 12px; border-radius: var(--gd-radius-sm); font-size: 12px;
            border: 1px solid var(--gd-border); background: var(--gd-surface);
            color: var(--gd-text); transition: border-color 0.15s; font-family: inherit;
        }
        .gd-filter-input:focus { outline: none; border-color: var(--gd-accent); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }

        /* ============ CONTEXT JSON ============ */
        .gd-context-json {
            padding: 14px; border-radius: 8px; background: var(--gd-bg);
            font-family: 'SF Mono', 'Fira Code', monospace; font-size: 11px;
            white-space: pre-wrap; word-break: break-all; max-height: 300px;
            overflow-y: auto; border: 1px solid var(--gd-border);
        }

        /* ============ TRANSITIONS ============ */
        .gd-fade-in { animation: gd-fade .2s ease; }
        @keyframes gd-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

        .gd-toast { animation: gd-slide-in 0.3s ease; }
        @keyframes gd-slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ============ SCROLLBAR ============ */
        .gd-sidebar::-webkit-scrollbar { width: 4px; }
        .gd-sidebar::-webkit-scrollbar-track { background: transparent; }
        .gd-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 2px; }

        .gd-dark ::-webkit-scrollbar { width: 6px; height: 6px; }
        .gd-dark ::-webkit-scrollbar-track { background: var(--gd-surface); }
        .gd-dark ::-webkit-scrollbar-thumb { background: var(--gd-border); border-radius: 3px; }
        .gd-dark ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Poll indicator - legacy alias */
        .gd-poll-indicator { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--gd-text-secondary); }
        .gd-poll-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gd-success); box-shadow: 0 0 0 3px rgba(34,197,94,.2); animation: gd-live-pulse 2s ease-in-out infinite; }
        .gd-poll-dot.paused { background: #9ca3af; box-shadow: none; animation: none; }
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
            <div class="gd-sidebar__brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <span class="gd-sidebar__brand-text">Guardian</span>
        </div>
        <nav class="gd-sidebar__nav">
            <div class="gd-sidebar__section">Monitoring</div>
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

            <div class="gd-sidebar__section">Analysis</div>
            <a href="{{ route('guardian.mail') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.mail') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 4L12 13 2 4"/></svg>
                Mail
            </a>
            <a href="{{ route('guardian.notifications') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.notifications') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                Notifications
            </a>
            <a href="{{ route('guardian.exceptions') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.exceptions') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Exceptions
            </a>
            <a href="{{ route('guardian.logs') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.logs') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Logs
            </a>

            <div class="gd-sidebar__section">System</div>
            <a href="{{ route('guardian.cache') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.cache') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                Cache
            </a>
            <a href="{{ route('guardian.queue') }}" class="gd-sidebar__link {{ request()->routeIs('guardian.queue') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                Queue Jobs
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
        <div class="gd-sidebar__footer">
            <div class="gd-sidebar__footer-project">{{ config('app.name', 'Laravel') }}</div>
            <div class="gd-sidebar__footer-meta">
                @php $gdEnv = config('guardian.environment', config('app.env', 'local')); @endphp
                <span class="gd-env-badge gd-env-badge--{{ $gdEnv }}">{{ $gdEnv }}</span>
                <span style="opacity:.5">v1.0</span>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="gd-main">
        <header class="gd-topbar">
            <div class="gd-topbar__left">
                <h1 class="gd-topbar__title">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="gd-topbar__actions">
                <div class="gd-live-indicator" x-show="polling || pollPaused">
                    <div class="gd-live-dot" :class="{ 'paused': pollPaused }"></div>
                    <span x-text="pollPaused ? 'Paused' : 'Live'" style="font-weight:500"></span>
                </div>
                <button class="gd-icon-btn" @click="togglePoll()" :title="pollPaused ? 'Resume polling' : 'Pause polling'">
                    <template x-if="pollPaused">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </template>
                    <template x-if="!pollPaused">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                    </template>
                </button>
                <button class="gd-icon-btn" @click="darkMode = !darkMode" :title="darkMode ? 'Light mode' : 'Dark mode'">
                    <template x-if="!darkMode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </template>
                    <template x-if="darkMode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </template>
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
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
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
                grid: isDark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)',
                text: isDark ? '#94a3b8' : '#64748b',
                blue: '#6366f1',
                green: '#22c55e',
                red: '#ef4444',
                yellow: '#eab308',
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
        // Destroy all charts in a charts object — call BEFORE updating data to stop animation loops
        window.destroyAllCharts = function(charts) {
            Object.keys(charts).forEach(key => {
                if (charts[key]) {
                    try { charts[key].destroy(); } catch(e) {}
                    charts[key] = null;
                }
            });
        };

        function statusBadgeClass(status) {
            const map = { ok: 'gd-badge--ok', success: 'gd-badge--success', warning: 'gd-badge--warning', critical: 'gd-badge--critical', failed: 'gd-badge--failed', danger: 'gd-badge--danger', info: 'gd-badge--info' };
            return map[status] || 'gd-badge--unknown';
        }

        // Helper to create gradient fills for charts
        window.createChartGradient = function(ctx, color, height) {
            if (!ctx || !ctx.getContext) return color + '20';
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, height || 260);
            gradient.addColorStop(0, color + '30');
            gradient.addColorStop(1, color + '02');
            return gradient;
        };
    </script>
    @yield('scripts')
</body>
</html>
