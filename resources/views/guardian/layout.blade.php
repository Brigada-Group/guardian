<!DOCTYPE html>
<html lang="en" x-data="guardianApp()" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guardian Dashboard</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    border: 'hsl(214.3 31.8% 91.4%)',
                    input: 'hsl(214.3 31.8% 91.4%)',
                    ring: 'hsl(222.2 84% 4.9%)',
                    background: 'hsl(0 0% 100%)',
                    foreground: 'hsl(222.2 84% 4.9%)',
                    primary: { DEFAULT: 'hsl(222.2 47.4% 11.2%)', foreground: 'hsl(210 40% 98%)' },
                    secondary: { DEFAULT: 'hsl(210 40% 96.1%)', foreground: 'hsl(222.2 47.4% 11.2%)' },
                    destructive: { DEFAULT: 'hsl(0 84.2% 60.2%)', foreground: 'hsl(210 40% 98%)' },
                    muted: { DEFAULT: 'hsl(210 40% 96.1%)', foreground: 'hsl(215.4 16.3% 46.9%)' },
                    accent: { DEFAULT: 'hsl(210 40% 96.1%)', foreground: 'hsl(222.2 47.4% 11.2%)' },
                    card: { DEFAULT: 'hsl(0 0% 100%)', foreground: 'hsl(222.2 84% 4.9%)' },
                    sidebar: { DEFAULT: '#0a0a0a', foreground: '#a1a1aa', primary: '#fafafa', accent: '#1a1a2e', border: '#27272a', ring: '#3f3f46' },
                },
                borderRadius: { lg: '0.5rem', md: 'calc(0.5rem - 2px)', sm: 'calc(0.5rem - 4px)' },
            }
        }
    }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        [x-cloak] { display: none !important; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* shadcn dark mode tokens */
        .dark {
            --tw-bg-opacity: 1;
            color-scheme: dark;
        }

        /* Chart container */
        .gd-chart-container { position: relative; height: 260px; }

        /* Animations */
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
        .animate-pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }

        /* Skeleton shimmer */
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .gd-skeleton { background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 0.5rem; }
        .dark .gd-skeleton { background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%); background-size: 200% 100%; }

        /* Status dots */
        .gd-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .gd-status-dot--ok, .gd-status-dot--success, .gd-status-dot--completed { background: #22c55e; }
        .gd-status-dot--warning { background: #eab308; }
        .gd-status-dot--critical, .gd-status-dot--danger, .gd-status-dot--failed { background: #ef4444; }
        .gd-status-dot--error { background: #f97316; }
        .gd-status-dot--unknown { background: #94a3b8; }

        /* Badge overrides for backward compat */
        .gd-badge--ok, .gd-badge--success { background: rgb(16 185 129 / 0.1); color: #059669; }
        .dark .gd-badge--ok, .dark .gd-badge--success { color: #34d399; }
        .gd-badge--warning { background: rgb(234 179 8 / 0.1); color: #ca8a04; }
        .dark .gd-badge--warning { color: #facc15; }
        .gd-badge--critical, .gd-badge--danger, .gd-badge--failed { background: rgb(239 68 68 / 0.1); color: #dc2626; }
        .dark .gd-badge--critical, .dark .gd-badge--danger, .dark .gd-badge--failed { color: #f87171; }
        .gd-badge--error { background: rgb(249 115 22 / 0.1); color: #ea580c; }
        .dark .gd-badge--error { color: #fb923c; }
        .gd-badge--info { background: rgb(59 130 246 / 0.1); color: #2563eb; }
        .dark .gd-badge--info { color: #60a5fa; }
        .gd-badge--unknown { background: rgb(113 113 122 / 0.1); color: #71717a; }
        .gd-badge--emergency, .gd-badge--alert { background: rgb(239 68 68 / 0.1); color: #dc2626; }
        .dark .gd-badge--emergency, .dark .gd-badge--alert { color: #f87171; }
        .gd-badge--completed { background: rgb(34 197 94 / 0.08); color: #15803d; }
        .dark .gd-badge--completed { color: #4ade80; }
        .gd-badge--processing { background: rgb(59 130 246 / 0.08); color: #2563eb; }
        .dark .gd-badge--processing { color: #60a5fa; }
        .gd-badge--warning-solid { background: rgb(234 179 8 / 0.1); color: #ca8a04; }

        /* Scrollbar */
        .dark ::-webkit-scrollbar { width: 6px; height: 6px; }
        .dark ::-webkit-scrollbar-track { background: transparent; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        /* Backward compatibility for existing views */
        .gd-card {
            border-radius: 0.5rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            color: hsl(222.2 84% 4.9%);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        .dark .gd-card {
            border-color: #27272a;
            background: #141b2d;
            color: #f1f5f9;
        }
        .gd-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid hsl(214.3 31.8% 91.4%);
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .dark .gd-card__header { border-bottom-color: #27272a; }
        .gd-card__body { padding: 1.25rem; }

        .gd-table-wrapper { overflow-x: auto; }
        .gd-table { width: 100%; font-size: 0.875rem; }
        .gd-table th {
            text-align: left;
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: hsl(215.4 16.3% 46.9%);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid hsl(214.3 31.8% 91.4%);
        }
        .dark .gd-table th { color: #94a3b8; border-bottom-color: #27272a; }
        .gd-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid hsl(214.3 31.8% 91.4% / 0.3);
        }
        .dark .gd-table td { border-bottom-color: rgba(255,255,255,.06); }
        .gd-table tbody tr:hover { background: hsl(210 40% 96.1% / 0.5); }
        .dark .gd-table tbody tr:hover { background: rgba(255,255,255,.03); }

        .gd-metrics {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1280px) { .gd-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 768px) { .gd-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .gd-metrics { grid-template-columns: minmax(0, 1fr); } }

        .gd-grid { display: grid; gap: 1.25rem; margin-bottom: 1.25rem; }
        .gd-grid--2 { grid-template-columns: repeat(2, 1fr); }
        .gd-grid--3 { grid-template-columns: repeat(3, 1fr); }
        .gd-grid--4 { grid-template-columns: repeat(4, 1fr); }
        @media (max-width: 1024px) { .gd-grid--2, .gd-grid--3, .gd-grid--4 { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .gd-grid--2 { grid-template-columns: 1fr; } }

        .gd-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.125rem 0.5rem;
            font-size: 0.625rem;
            font-weight: 600;
        }

        .gd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: hsl(222.2 84% 4.9%);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            transition: all 0.15s;
            cursor: pointer;
            font-family: inherit;
            gap: 0.375rem;
        }
        .gd-btn:hover { background: hsl(210 40% 96.1%); color: hsl(222.2 47.4% 11.2%); }
        .dark .gd-btn { border-color: #27272a; background: #141b2d; color: #f1f5f9; }
        .dark .gd-btn:hover { background: #1e293b; }
        .gd-btn--sm { height: 1.75rem; padding: 0 0.5rem; font-size: 0.6875rem; }
        .gd-btn--primary { background: hsl(222.2 47.4% 11.2%); color: hsl(210 40% 98%); border-color: transparent; }
        .gd-btn--primary:hover { opacity: 0.9; }
        .gd-btn--danger { background: #ef4444; color: #fff; border-color: #ef4444; }
        .gd-btn--copy { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; }

        .gd-mono { font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace; font-size: 0.75rem; }
        .gd-truncate { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .gd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem 1rem; color: hsl(215.4 16.3% 46.9%); }
        .gd-empty svg { width: 40px; height: 40px; margin: 0 auto 14px; opacity: 0.3; display: block; }
        .gd-empty__icon { font-size: 36px; margin-bottom: 12px; opacity: .3; }
        .gd-empty__text { margin-top: 0.75rem; font-size: 0.875rem; font-weight: 500; }
        .gd-empty__hint { margin-top: 0.25rem; font-size: 0.75rem; opacity: 0.7; }

        .gd-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.25rem;
            border-top: 1px solid hsl(214.3 31.8% 91.4%);
            font-size: 0.75rem;
            color: hsl(215.4 16.3% 46.9%);
        }
        .dark .gd-pagination { border-top-color: #27272a; color: #94a3b8; }
        .gd-pagination__links { display: flex; gap: 0.25rem; }
        .gd-pagination__link {
            border-radius: 0.375rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.15s;
            cursor: pointer;
            font-family: inherit;
        }
        .gd-pagination__link:hover { background: hsl(210 40% 96.1%); }
        .gd-pagination__link:disabled { opacity: 0.4; cursor: not-allowed; }
        .dark .gd-pagination__link { border-color: #27272a; background: #141b2d; color: #f1f5f9; }

        .gd-loading { padding: 1.5rem; }

        .gd-skeleton-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1024px) { .gd-skeleton-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .gd-skeleton-grid { grid-template-columns: 1fr; } }
        .gd-skeleton--card { height: 6rem; border-radius: 0.5rem; }
        .gd-skeleton--chart { height: 260px; border-radius: 0.5rem; }
        .gd-skeleton--row { height: 3rem; border-radius: 0.25rem; }

        .gd-stat-card {
            border-radius: 0.5rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 1rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            transition: all 0.2s;
        }
        .gd-stat-card:hover { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .dark .gd-stat-card { border-color: #27272a; background: #141b2d; }
        .gd-stat-card__header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .gd-stat-card__dot { height: 0.5rem; width: 0.5rem; border-radius: 9999px; }
        .gd-stat-card__label { font-size: 0.6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: hsl(215.4 16.3% 46.9%); }
        .gd-stat-card__value { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.025em; color: hsl(222.2 84% 4.9%); }
        .dark .gd-stat-card__value { color: #f1f5f9; }
        .gd-stat-card__trend { margin-top: 0.25rem; font-size: 0.75rem; color: hsl(215.4 16.3% 46.9%); }
        .gd-stat-card--alert { border-left: 2px solid hsl(0 84.2% 60.2%); }

        /* Legacy metric card class support */
        .gd-metric-card {
            border-radius: 0.5rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            transition: all 0.2s;
        }
        .gd-metric-card:hover { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); transform: translateY(-1px); }
        .dark .gd-metric-card { border-color: #27272a; background: #141b2d; }
        .gd-metric-card--alert { border-left: 3px solid #ef4444; }
        .gd-metric-card__label { font-size: 0.6875rem; font-weight: 500; color: hsl(215.4 16.3% 46.9%); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.625rem; }
        .gd-metric-card__value { font-size: 2rem; font-weight: 600; line-height: 1.1; letter-spacing: -0.02em; }
        .gd-metric-card__subtitle { font-size: 0.75rem; color: hsl(215.4 16.3% 46.9%); margin-top: 0.375rem; }

        .gd-fade-in { animation: gd-fade 0.2s ease; }
        @keyframes gd-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

        /* Filter / tab pills */
        .gd-date-filter {
            display: inline-flex;
            border-radius: 0.5rem;
            background: hsl(210 40% 96.1%);
            padding: 0.125rem;
        }
        .dark .gd-date-filter { background: #1e293b; }
        .gd-date-filter__btn,
        .gd-date-filter button {
            border-radius: 0.375rem;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: hsl(215.4 16.3% 46.9%);
            transition: all 0.15s;
            border: none;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
        }
        .gd-date-filter__btn:hover,
        .gd-date-filter button:hover { color: hsl(222.2 84% 4.9%); }
        .dark .gd-date-filter__btn:hover,
        .dark .gd-date-filter button:hover { color: #f1f5f9; }
        .gd-date-filter__btn.active,
        .gd-date-filter button.active {
            background: hsl(0 0% 100%);
            color: hsl(222.2 84% 4.9%);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        .dark .gd-date-filter__btn.active,
        .dark .gd-date-filter button.active { background: #141b2d; color: #f1f5f9; }

        /* Tabs */
        .gd-tabs {
            display: inline-flex;
            background: hsl(210 40% 96.1%);
            border-radius: 0.5rem;
            padding: 0.125rem;
            margin-bottom: 1.25rem;
        }
        .dark .gd-tabs { background: #1e293b; }
        .gd-tab {
            padding: 0.5rem 1.125rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: hsl(215.4 16.3% 46.9%);
            cursor: pointer;
            border-radius: 0.375rem;
            border: none;
            background: transparent;
            transition: all 0.15s;
            font-family: inherit;
        }
        .gd-tab:hover { color: hsl(222.2 84% 4.9%); }
        .dark .gd-tab:hover { color: #f1f5f9; }
        .gd-tab.active {
            background: hsl(0 0% 100%);
            color: hsl(222.2 84% 4.9%);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            font-weight: 600;
        }
        .dark .gd-tab.active { background: #141b2d; color: #f1f5f9; }

        /* Filter inputs */
        .gd-filter-input {
            height: 2rem;
            border-radius: 0.375rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 0 0.625rem;
            font-size: 0.75rem;
            transition: all 0.15s;
            font-family: inherit;
            color: hsl(222.2 84% 4.9%);
        }
        .gd-filter-input:focus { outline: none; box-shadow: 0 0 0 2px hsl(222.2 84% 4.9% / 0.2); }
        .dark .gd-filter-input { border-color: #27272a; background: #141b2d; color: #f1f5f9; }

        /* Health check cards */
        .gd-health-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.75rem; }
        .gd-health-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid hsl(214.3 31.8% 91.4%);
            background: hsl(0 0% 100%);
            padding: 1rem;
            transition: all 0.2s;
        }
        .gd-health-card:hover { box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
        .dark .gd-health-card { border-color: #27272a; background: #141b2d; }
        .gd-health-card__icon,
        .gd-health-icon {
            display: flex;
            height: 2.25rem;
            width: 2.25rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            font-size: 1.125rem;
        }
        .gd-health-card__icon--ok, .gd-health-icon--ok { background: rgb(34 197 94 / 0.1); color: #059669; }
        .gd-health-card__icon--warning, .gd-health-icon--warning { background: rgb(234 179 8 / 0.1); color: #ca8a04; }
        .gd-health-card__icon--critical, .gd-health-icon--critical { background: rgb(239 68 68 / 0.1); color: #dc2626; }
        .gd-health-card__icon--unknown, .gd-health-icon--unknown { background: rgb(113 113 122 / 0.1); color: #71717a; }
        .gd-health-card__body { flex: 1; min-width: 0; }
        .gd-health-card__name { font-size: 0.875rem; font-weight: 600; margin-bottom: 0.125rem; }
        .gd-health-card__message { font-size: 0.75rem; color: hsl(215.4 16.3% 46.9%); margin-bottom: 0.375rem; }
        .gd-health-card__meta { font-size: 0.6875rem; color: hsl(215.4 16.3% 46.9%); display: flex; gap: 0.75rem; }

        /* Toast */
        .gd-toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 9999;
            border-radius: 0.5rem;
            background: hsl(222.2 84% 4.9%);
            padding: 0.625rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: hsl(210 40% 98%);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            animation: gd-slide-in 0.3s ease;
        }
        @keyframes gd-slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Env badges */
        .gd-env-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.125rem 0.5rem;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .gd-env-badge--production { background: rgb(239 68 68 / 0.1); color: #dc2626; }
        .gd-env-badge--staging { background: rgb(234 179 8 / 0.1); color: #ca8a04; }
        .gd-env-badge--local, .gd-env-badge--testing { background: rgb(59 130 246 / 0.1); color: #2563eb; }
        .dark .gd-env-badge--production { color: #f87171; }
        .dark .gd-env-badge--staging { color: #facc15; }
        .dark .gd-env-badge--local, .dark .gd-env-badge--testing { color: #60a5fa; }

        /* Expandable rows */
        .gd-expandable { cursor: pointer; }
        .gd-expandable__content {
            padding: 0.875rem 1rem;
            background: hsl(210 40% 96.1%);
            font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
            font-size: 0.75rem;
            white-space: pre-wrap;
            word-break: break-all;
            border-bottom: 1px solid hsl(214.3 31.8% 91.4%);
        }
        .dark .gd-expandable__content { background: #0f172a; border-bottom-color: #27272a; }
        .gd-expandable-section { overflow: hidden; transition: max-height 0.3s ease; }

        /* Context JSON */
        .gd-context-json {
            padding: 0.875rem;
            border-radius: 0.5rem;
            background: hsl(210 40% 96.1%);
            font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
            font-size: 0.6875rem;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid hsl(214.3 31.8% 91.4%);
        }
        .dark .gd-context-json { background: #0f172a; border-color: #27272a; }

        /* Status text colors */
        .gd-status-ok { color: #22c55e; }
        .gd-status-warning { color: #eab308; }
        .gd-status-critical { color: #ef4444; }
        .gd-status-unknown { color: hsl(215.4 16.3% 46.9%); }

        /* Legacy spinner */
        .gd-spinner {
            width: 20px; height: 20px; border: 2px solid hsl(214.3 31.8% 91.4%);
            border-top-color: hsl(222.2 47.4% 11.2%); border-radius: 50%;
            animation: gd-spin 0.6s linear infinite; margin-right: 10px;
        }
        @keyframes gd-spin { to { transform: rotate(360deg); } }

        /* Poll indicator - legacy alias */
        .gd-poll-indicator { display: flex; align-items: center; gap: 6px; font-size: 12px; color: hsl(215.4 16.3% 46.9%); }
        .gd-poll-dot { width: 6px; height: 6px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); animation: pulse-dot 2s ease-in-out infinite; }
        .gd-poll-dot.paused { background: #9ca3af; box-shadow: none; animation: none; }

        /* Sidebar scrollbar */
        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 2px; }
    </style>
</head>
<body class="bg-background text-foreground antialiased dark:bg-[#0a0f1a] dark:text-slate-100">
    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-sidebar-border bg-sidebar transition-transform duration-200"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        <!-- Brand -->
        <div class="flex h-14 items-center gap-2.5 border-b border-sidebar-border px-5">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-white/10">
                <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <span class="text-sm font-semibold text-sidebar-primary tracking-tight">Guardian</span>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <div>
                <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">Monitoring</p>
                <div class="space-y-0.5">
                    <a href="{{ route('guardian.overview') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.overview') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                        Overview
                    </a>
                    <a href="{{ route('guardian.requests') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.requests') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M9 12l2 2 4-4"/></svg>
                        Requests
                    </a>
                    <a href="{{ route('guardian.queries') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.queries') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Queries
                    </a>
                    <a href="{{ route('guardian.outgoing-http') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.outgoing-http') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                        Outgoing HTTP
                    </a>
                </div>
            </div>
            <div>
                <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">Analysis</p>
                <div class="space-y-0.5">
                    <a href="{{ route('guardian.jobs') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.jobs') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                        Jobs & Scheduler
                    </a>
                    <a href="{{ route('guardian.queue') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.queue') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                        Queue Jobs
                    </a>
                    <a href="{{ route('guardian.mail') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.mail') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 4L12 13 2 4"/></svg>
                        Mail
                    </a>
                    <a href="{{ route('guardian.notifications') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.notifications') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                        Notifications
                    </a>
                    <a href="{{ route('guardian.cache') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.cache') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        Cache
                    </a>
                    <a href="{{ route('guardian.logs') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.logs') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Logs
                    </a>
                </div>
            </div>
            <div>
                <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">System</p>
                <div class="space-y-0.5">
                    <a href="{{ route('guardian.exceptions') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.exceptions') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Exceptions
                    </a>
                    <a href="{{ route('guardian.alerts') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.alerts') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Alerts
                    </a>
                    <a href="{{ route('guardian.health') }}" class="group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium transition-colors {{ request()->routeIs('guardian.health') ? 'bg-sidebar-accent text-sidebar-primary' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-primary' }}">
                        <svg class="h-4 w-4 shrink-0 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        Health Checks
                    </a>
                </div>
            </div>
        </nav>

        <!-- Footer -->
        <div class="border-t border-sidebar-border p-4">
            <p class="text-xs font-medium text-sidebar-primary truncate">{{ config('app.name', 'Laravel') }}</p>
            <div class="mt-1 flex items-center gap-2">
                @php $gdEnv = config('guardian.environment', config('app.env', 'local')); @endphp
                <span class="gd-env-badge gd-env-badge--{{ $gdEnv }}">{{ $gdEnv }}</span>
                <span class="text-[10px] text-sidebar-foreground/50">v1.0</span>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <main class="lg:pl-[260px]">
        <!-- Top bar -->
        <header class="sticky top-0 z-40 flex h-14 items-center justify-between border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 px-6 dark:bg-[#0a0f1a]/95 dark:border-[#1e293b]">
            <!-- Mobile toggle + page title -->
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden -ml-2 rounded-md p-1.5 text-muted-foreground hover:text-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                </button>
                <h1 class="text-sm font-semibold text-foreground dark:text-slate-100">@yield('page-title', 'Dashboard')</h1>
            </div>
            <!-- Actions -->
            <div class="flex items-center gap-2">
                <!-- Live dot -->
                <div class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs"
                     :class="polling && !pollPaused ? 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'border-zinc-300 text-muted-foreground dark:border-zinc-700'"
                     x-show="polling || pollPaused">
                    <span class="h-1.5 w-1.5 rounded-full" :class="polling && !pollPaused ? 'bg-emerald-500 animate-pulse-dot' : 'bg-zinc-400'"></span>
                    <span x-text="polling && !pollPaused ? 'Live' : 'Paused'"></span>
                </div>
                <!-- Pause/Play -->
                <button @click="togglePoll()" class="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors" :title="pollPaused ? 'Resume polling' : 'Pause polling'">
                    <template x-if="pollPaused">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </template>
                    <template x-if="!pollPaused">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                    </template>
                </button>
                <!-- Dark mode -->
                <button @click="darkMode = !darkMode" class="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors" :title="darkMode ? 'Light mode' : 'Dark mode'">
                    <template x-if="!darkMode">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </template>
                    <template x-if="darkMode">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </template>
                </button>
            </div>
        </header>
        <div class="p-6">
            @yield('content')
        </div>
    </main>

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
