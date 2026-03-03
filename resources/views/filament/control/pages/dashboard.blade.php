<x-filament-panels::page>
    @php
        $stats = $this->getMainStats();
        $periodOptions = $this->getPeriodOptions();
        $activity = $this->getWeeklyActivitySeries();
        $distribution = $this->getKhatmaTypeDistribution();
        $users = $this->getLatestUsersTable();
        $recentActivities = $this->getRecentActivities();
        $healthMetrics = $this->getHealthMetrics();
        $maxActivity = max((int) ($activity['max_value'] ?? 1), 1);
        $controlLinks = [
            [
                'label' => 'إدارة المستخدمين',
                'hint' => 'عرض الحسابات، الأدوار، والتحقق',
                'url' => \App\Filament\Control\Resources\UserResource::getUrl('index'),
                'icon' => '👥',
            ],
            [
                'label' => 'إدارة الختمات',
                'hint' => 'التحكم بجميع ختمات المنصة',
                'url' => \App\Filament\Control\Resources\KhatmaResource::getUrl('index'),
                'icon' => '📚',
            ],
            [
                'label' => 'سجل الإنجاز',
                'hint' => 'تتبع سجلات الإنجاز اليومية',
                'url' => \App\Filament\Control\Resources\DailyRecordResource::getUrl('index'),
                'icon' => '🧾',
            ],
            [
                'label' => 'إعدادات النظام',
                'hint' => 'التحكم بالقيم العامة للمنصة',
                'url' => \App\Filament\Control\Pages\Settings::getUrl(),
                'icon' => '⚙️',
            ],
        ];

        $gradientParts = [];
        $offset = 0.0;

        foreach (($distribution['segments'] ?? []) as $segment) {
            $percent = (float) ($segment['percent'] ?? 0);
            if ($percent <= 0) {
                continue;
            }

            $start = round($offset, 2);
            $offset += $percent;
            $end = round(min($offset, 100), 2);
            $gradientParts[] = "{$segment['color']} {$start}% {$end}%";
        }

        $donutGradient = count($gradientParts) > 0
            ? 'conic-gradient(' . implode(', ', $gradientParts) . ')'
            : 'conic-gradient(rgba(148,163,184,0.35) 0% 100%)';
    @endphp

    <style>
        .ctrl-wrap {
            display: grid;
            gap: 1rem;
        }

        .ctrl-toolbar {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            background: color-mix(in srgb, var(--gray-50, #f8fafc) 84%, transparent);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .dark .ctrl-toolbar {
            border-color: rgba(148, 163, 184, 0.14);
            background: color-mix(in srgb, var(--gray-950, #020617) 92%, transparent);
        }

        .ctrl-title {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
            color: var(--gray-900, #0f172a);
            font-weight: 700;
            font-size: 1rem;
        }

        .dark .ctrl-title {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-subtle {
            color: var(--gray-500, #64748b);
            font-size: 0.76rem;
            font-weight: 500;
        }

        .ctrl-live {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #047857;
            background: rgba(16, 185, 129, 0.1);
            padding: 0.15rem 0.55rem;
            font-size: 0.66rem;
            font-weight: 700;
        }

        .dark .ctrl-live {
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.18);
        }

        .ctrl-live-dot {
            width: 0.4rem;
            height: 0.4rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.9;
        }

        .ctrl-actions {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
        }

        .ctrl-periods {
            display: inline-flex;
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
            background: rgba(148, 163, 184, 0.08);
        }

        .dark .ctrl-periods {
            border-color: rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.55);
        }

        .ctrl-period-btn {
            border: 0;
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-600, #475569);
            background: transparent;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .dark .ctrl-period-btn {
            color: var(--gray-300, #cbd5e1);
        }

        .ctrl-period-btn:hover {
            background: rgba(109, 40, 217, 0.12);
        }

        .ctrl-period-btn.active {
            color: #fff;
            background: linear-gradient(135deg, #6d28d9, #4c1d95);
        }

        .ctrl-refresh {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 0.65rem;
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-700, #334155);
            background: rgba(148, 163, 184, 0.08);
            cursor: pointer;
        }

        .dark .ctrl-refresh {
            color: var(--gray-200, #e2e8f0);
            background: rgba(15, 23, 42, 0.55);
            border-color: rgba(148, 163, 184, 0.2);
        }

        .ctrl-section-label {
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gray-500, #64748b);
            margin: 0.1rem 0;
        }

        .ctrl-shortcuts {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .ctrl-shortcut {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.9rem;
            background: color-mix(in srgb, var(--gray-50, #f8fafc) 90%, transparent);
            padding: 0.75rem 0.85rem;
            transition: border-color 0.2s ease, transform 0.2s ease, background 0.2s ease;
        }

        .ctrl-shortcut:hover {
            border-color: rgba(109, 40, 217, 0.35);
            background: rgba(109, 40, 217, 0.08);
            transform: translateY(-1px);
        }

        .dark .ctrl-shortcut {
            border-color: rgba(148, 163, 184, 0.14);
            background: color-mix(in srgb, var(--gray-950, #020617) 95%, transparent);
        }

        .dark .ctrl-shortcut:hover {
            border-color: rgba(196, 181, 253, 0.4);
            background: rgba(109, 40, 217, 0.18);
        }

        .ctrl-shortcut-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.65rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(109, 40, 217, 0.12);
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .ctrl-shortcut-title {
            font-size: 0.79rem;
            font-weight: 700;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-shortcut-title {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-shortcut-hint {
            font-size: 0.68rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .ctrl-card {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            background: color-mix(in srgb, var(--gray-50, #f8fafc) 88%, transparent);
            padding: 1rem;
        }

        .dark .ctrl-card {
            border-color: rgba(148, 163, 184, 0.14);
            background: color-mix(in srgb, var(--gray-950, #020617) 92%, transparent);
        }

        .ctrl-kpi-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
            font-size: 0.78rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-kpi-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .ctrl-kpi-value {
            font-size: clamp(1.4rem, 2.1vw, 2rem);
            font-weight: 800;
            line-height: 1.1;
            color: var(--gray-950, #020617);
        }

        .dark .ctrl-kpi-value {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-kpi-label {
            margin-top: 0.2rem;
            color: var(--gray-600, #475569);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .dark .ctrl-kpi-label {
            color: var(--gray-300, #cbd5e1);
        }

        .ctrl-kpi-sub {
            margin-top: 0.25rem;
            color: var(--gray-500, #64748b);
            font-size: 0.72rem;
        }

        .ctrl-kpi-purple .ctrl-kpi-icon { background: rgba(109, 40, 217, 0.14); }
        .ctrl-kpi-green .ctrl-kpi-icon { background: rgba(16, 185, 129, 0.14); }
        .ctrl-kpi-gold .ctrl-kpi-icon { background: rgba(245, 158, 11, 0.14); }
        .ctrl-kpi-blue .ctrl-kpi-icon { background: rgba(59, 130, 246, 0.14); }
        .ctrl-kpi-red .ctrl-kpi-icon { background: rgba(239, 68, 68, 0.14); }

        .ctrl-grid-main {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 0.85rem;
        }

        .ctrl-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.95rem;
        }

        .ctrl-card-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-card-title {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-card-meta {
            font-size: 0.72rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-legend {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            font-size: 0.69rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-legend-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            display: inline-block;
            margin-left: 0.25rem;
        }

        .ctrl-bars {
            height: 9.5rem;
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
            align-items: end;
        }

        .ctrl-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            min-width: 0;
        }

        .ctrl-day-bars {
            width: 100%;
            height: 8rem;
            display: flex;
            align-items: flex-end;
            gap: 0.12rem;
        }

        .ctrl-bar {
            flex: 1;
            min-height: 0.2rem;
            border-radius: 0.35rem 0.35rem 0 0;
        }

        .ctrl-bar-purple { background: linear-gradient(180deg, #6d28d9, #4c1d95); }
        .ctrl-bar-green { background: linear-gradient(180deg, #10b981, #047857); }
        .ctrl-bar-gold { background: linear-gradient(180deg, #f59e0b, #b45309); }

        .ctrl-day-label {
            font-size: 0.66rem;
            color: var(--gray-500, #64748b);
            text-align: center;
            width: 100%;
        }

        .ctrl-donut-wrap {
            display: grid;
            gap: 0.85rem;
            justify-items: center;
        }

        .ctrl-donut {
            width: 8.9rem;
            height: 8.9rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            position: relative;
        }

        .ctrl-donut::after {
            content: '';
            position: absolute;
            inset: 1.3rem;
            background: color-mix(in srgb, var(--gray-50, #f8fafc) 90%, transparent);
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .dark .ctrl-donut::after {
            background: color-mix(in srgb, var(--gray-950, #020617) 95%, transparent);
            border-color: rgba(148, 163, 184, 0.14);
        }

        .ctrl-donut-center {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .ctrl-donut-num {
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-donut-num {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-donut-sub {
            font-size: 0.68rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-donut-legend {
            width: 100%;
            display: grid;
            gap: 0.45rem;
        }

        .ctrl-donut-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.77rem;
        }

        .ctrl-bottom {
            display: grid;
            grid-template-columns: 1.35fr 1.15fr 0.9fr;
            gap: 0.85rem;
        }

        .ctrl-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ctrl-table th {
            text-align: right;
            font-size: 0.68rem;
            color: var(--gray-500, #64748b);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .ctrl-table td {
            padding: 0.62rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            vertical-align: middle;
            font-size: 0.8rem;
            color: var(--gray-700, #334155);
        }

        .dark .ctrl-table td {
            color: var(--gray-300, #cbd5e1);
            border-bottom-color: rgba(148, 163, 184, 0.12);
        }

        .ctrl-table tbody tr:last-child td {
            border-bottom: none;
        }

        .ctrl-user-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ctrl-user-avatar {
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 0.55rem;
            background: linear-gradient(135deg, #6d28d9, #4c1d95);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .ctrl-user-name {
            font-size: 0.79rem;
            font-weight: 700;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-user-name {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-user-meta {
            font-size: 0.68rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .ctrl-status-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
        }

        .ctrl-status.active { color: #047857; }
        .ctrl-status.active .ctrl-status-dot { background: #10b981; }
        .ctrl-status.idle { color: #b45309; }
        .ctrl-status.idle .ctrl-status-dot { background: #f59e0b; }

        .ctrl-feed {
            display: grid;
            gap: 0.3rem;
        }

        .ctrl-feed-item {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .dark .ctrl-feed-item {
            border-bottom-color: rgba(148, 163, 184, 0.12);
        }

        .ctrl-feed-item:last-child {
            border-bottom: none;
        }

        .ctrl-feed-icon {
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 0.55rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            flex-shrink: 0;
        }

        .ctrl-feed-text {
            font-size: 0.77rem;
            line-height: 1.5;
            color: var(--gray-700, #334155);
        }

        .dark .ctrl-feed-text {
            color: var(--gray-300, #cbd5e1);
        }

        .ctrl-feed-time {
            margin-top: 0.15rem;
            font-size: 0.67rem;
            color: var(--gray-500, #64748b);
        }

        .ctrl-badge-placeholder {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px dashed rgba(109, 40, 217, 0.35);
            color: #6d28d9;
            background: rgba(109, 40, 217, 0.07);
            padding: 0.08rem 0.45rem;
            font-size: 0.63rem;
            font-weight: 700;
            margin-top: 0.25rem;
        }

        .dark .ctrl-badge-placeholder {
            color: #c4b5fd;
            border-color: rgba(196, 181, 253, 0.4);
            background: rgba(109, 40, 217, 0.16);
        }

        .ctrl-mini-list {
            display: grid;
            gap: 0.55rem;
        }

        .ctrl-mini {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.75rem;
            padding: 0.55rem 0.65rem;
            background: rgba(148, 163, 184, 0.05);
        }

        .dark .ctrl-mini {
            border-color: rgba(148, 163, 184, 0.12);
            background: rgba(15, 23, 42, 0.45);
        }

        .ctrl-mini-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.4rem;
        }

        .ctrl-mini-label {
            font-size: 0.72rem;
            color: var(--gray-600, #475569);
            font-weight: 600;
        }

        .dark .ctrl-mini-label {
            color: var(--gray-300, #cbd5e1);
        }

        .ctrl-mini-value {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-mini-value {
            color: var(--gray-100, #f1f5f9);
        }

        .ctrl-mini-bar {
            margin-top: 0.3rem;
            height: 0.28rem;
            background: rgba(148, 163, 184, 0.2);
            border-radius: 999px;
            overflow: hidden;
        }

        .ctrl-mini-fill {
            height: 100%;
            border-radius: inherit;
        }

        .ctrl-simple-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .ctrl-simple-row {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 0.85rem;
        }

        .ctrl-simple-list {
            display: grid;
            gap: 0.45rem;
        }

        .ctrl-simple-item {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 0.75rem;
            padding: 0.6rem 0.7rem;
            background: rgba(148, 163, 184, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .dark .ctrl-simple-item {
            border-color: rgba(148, 163, 184, 0.12);
            background: rgba(15, 23, 42, 0.45);
        }

        .ctrl-simple-label {
            font-size: 0.77rem;
            color: var(--gray-600, #475569);
            font-weight: 600;
        }

        .dark .ctrl-simple-label {
            color: var(--gray-300, #cbd5e1);
        }

        .ctrl-simple-value {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--gray-900, #0f172a);
        }

        .dark .ctrl-simple-value {
            color: var(--gray-100, #f1f5f9);
        }

        @media (max-width: 1280px) {
            .ctrl-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ctrl-shortcuts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ctrl-grid-main {
                grid-template-columns: 1fr;
            }

            .ctrl-bottom {
                grid-template-columns: 1fr;
            }

            .ctrl-simple-grid,
            .ctrl-simple-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .ctrl-kpis {
                grid-template-columns: 1fr;
            }

            .ctrl-shortcuts {
                grid-template-columns: 1fr;
            }

            .ctrl-period-btn {
                padding: 0.35rem 0.55rem;
                font-size: 0.69rem;
            }
        }
    </style>

    <div class="ctrl-wrap">
        <div class="ctrl-toolbar">
            <div>
                <div class="ctrl-title">
                    <span>مركز التحكم</span>
                    <span class="ctrl-live"><span class="ctrl-live-dot"></span> مباشر</span>
                </div>
                <div class="ctrl-subtle">{{ $stats['range_label'] }} · آخر تحديث {{ $this->lastRefreshedAt }}</div>
            </div>

            <div class="ctrl-actions">
                <div class="ctrl-periods">
                    @foreach($periodOptions as $key => $label)
                        <button
                            class="ctrl-period-btn {{ $this->period === $key ? 'active' : '' }}"
                            wire:click="setPeriod('{{ $key }}')"
                            wire:loading.attr="disabled"
                            wire:target="setPeriod"
                            type="button"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <button
                    class="ctrl-refresh"
                    wire:click="refreshData"
                    wire:loading.attr="disabled"
                    wire:target="refreshData"
                    type="button"
                >
                    تحديث
                </button>
            </div>
        </div>

        <section class="ctrl-shortcuts">
            @foreach($controlLinks as $link)
                <a class="ctrl-shortcut" href="{{ $link['url'] }}">
                    <span class="ctrl-shortcut-icon">{{ $link['icon'] }}</span>
                    <span>
                        <span class="ctrl-shortcut-title">{{ $link['label'] }}</span><br>
                        <span class="ctrl-shortcut-hint">{{ $link['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </section>

        @if($this->variant === 'rich')
            <div class="ctrl-section-label">المستخدمون</div>
            <section class="ctrl-kpis">
                <article class="ctrl-card ctrl-kpi-purple">
                    <div class="ctrl-kpi-head">
                        <span>إجمالي المستخدمين</span>
                        <span class="ctrl-kpi-icon">👥</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['total_users']) }}</div>
                    <div class="ctrl-kpi-label">المسجلون في المنصة</div>
                    <div class="ctrl-kpi-sub">+ {{ number_format($stats['new_users']) }} خلال {{ $stats['range_label'] }}</div>
                </article>

                <article class="ctrl-card ctrl-kpi-green">
                    <div class="ctrl-kpi-head">
                        <span>نشاط المستخدمين</span>
                        <span class="ctrl-kpi-icon">🟢</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['active_users']) }}</div>
                    <div class="ctrl-kpi-label">نشطون في الفترة المختارة</div>
                    <div class="ctrl-kpi-sub">اليوم: {{ number_format($stats['active_users_today']) }}</div>
                </article>

                <article class="ctrl-card ctrl-kpi-gold">
                    <div class="ctrl-kpi-head">
                        <span>التسجيلات الجديدة</span>
                        <span class="ctrl-kpi-icon">🆕</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['new_users']) }}</div>
                    <div class="ctrl-kpi-label">خلال {{ $stats['range_label'] }}</div>
                    <div class="ctrl-kpi-sub">تعكس نمو المنصة</div>
                </article>

                <article class="ctrl-card ctrl-kpi-red">
                    <div class="ctrl-kpi-head">
                        <span>مستخدمون غير نشطين</span>
                        <span class="ctrl-kpi-icon">😴</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['dormant_users_7']) }}</div>
                    <div class="ctrl-kpi-label">انقطاع 7 أيام فأكثر</div>
                    <div class="ctrl-kpi-sub">قيمة تشغيلية لمتابعة الالتزام</div>
                </article>
            </section>

            <div class="ctrl-section-label">الختمات</div>
            <section class="ctrl-kpis">
                <article class="ctrl-card ctrl-kpi-blue">
                    <div class="ctrl-kpi-head">
                        <span>إجمالي الختمات</span>
                        <span class="ctrl-kpi-icon">📚</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['total_khatmas']) }}</div>
                    <div class="ctrl-kpi-label">كل الختمات المسجلة</div>
                    <div class="ctrl-kpi-sub">متوسط {{ $stats['avg_khatmas_per_user'] }} ختمة لكل مستخدم</div>
                </article>

                <article class="ctrl-card ctrl-kpi-green">
                    <div class="ctrl-kpi-head">
                        <span>ختمات نشطة</span>
                        <span class="ctrl-kpi-icon">▶️</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['active_khatmas']) }}</div>
                    <div class="ctrl-kpi-label">تُنفذ حاليًا</div>
                    <div class="ctrl-kpi-sub">{{ $stats['active_khatma_share'] }}% من إجمالي الختمات</div>
                </article>

                <article class="ctrl-card ctrl-kpi-gold">
                    <div class="ctrl-kpi-head">
                        <span>ختمات مكتملة</span>
                        <span class="ctrl-kpi-icon">🏆</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['completed_khatmas']) }}</div>
                    <div class="ctrl-kpi-label">الختمات التي انتهت</div>
                    <div class="ctrl-kpi-sub">معدل الإكمال {{ $stats['completion_rate'] }}%</div>
                </article>

                <article class="ctrl-card ctrl-kpi-purple">
                    <div class="ctrl-kpi-head">
                        <span>صفحات منجزة</span>
                        <span class="ctrl-kpi-icon">📄</span>
                    </div>
                    <div class="ctrl-kpi-value">{{ number_format($stats['completed_pages']) }}</div>
                    <div class="ctrl-kpi-label">ضمن {{ $stats['range_label'] }}</div>
                    <div class="ctrl-kpi-sub">ختمات جديدة: {{ number_format($stats['new_khatmas']) }}</div>
                </article>
            </section>

            <section class="ctrl-grid-main">
                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div>
                            <div class="ctrl-card-title">نشاط الأسبوع</div>
                            <div class="ctrl-card-meta">مستخدمون جدد + ختمات جديدة + إكمالات يومية</div>
                        </div>

                        <div class="ctrl-legend">
                            <span><span class="ctrl-legend-dot" style="background:#6d28d9;"></span>مستخدمون</span>
                            <span><span class="ctrl-legend-dot" style="background:#10b981;"></span>ختمات</span>
                            <span><span class="ctrl-legend-dot" style="background:#f59e0b;"></span>مكتملة</span>
                        </div>
                    </div>

                    <div class="ctrl-bars">
                        @foreach(($activity['days'] ?? []) as $day)
                            @php
                                $usersHeight = max((int) round((($day['users'] ?? 0) / $maxActivity) * 100), ($day['users'] ?? 0) > 0 ? 5 : 2);
                                $khatmasHeight = max((int) round((($day['khatmas'] ?? 0) / $maxActivity) * 100), ($day['khatmas'] ?? 0) > 0 ? 5 : 2);
                                $completedHeight = max((int) round((($day['completed'] ?? 0) / $maxActivity) * 100), ($day['completed'] ?? 0) > 0 ? 5 : 2);
                            @endphp
                            <div class="ctrl-day" title="{{ $day['label'] }} | مستخدمون {{ $day['users'] }} · ختمات {{ $day['khatmas'] }} · مكتملة {{ $day['completed'] }}">
                                <div class="ctrl-day-bars">
                                    <div class="ctrl-bar ctrl-bar-purple" style="height: {{ $usersHeight }}%;"></div>
                                    <div class="ctrl-bar ctrl-bar-green" style="height: {{ $khatmasHeight }}%;"></div>
                                    <div class="ctrl-bar ctrl-bar-gold" style="height: {{ $completedHeight }}%;"></div>
                                </div>
                                <div class="ctrl-day-label">{{ $day['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">توزيع أنواع الختمات</div>
                        <div class="ctrl-card-meta">{{ number_format($distribution['total'] ?? 0) }} ختمة</div>
                    </div>

                    <div class="ctrl-donut-wrap">
                        <div class="ctrl-donut" style="background: {{ $donutGradient }};">
                            <div class="ctrl-donut-center">
                                <div class="ctrl-donut-num">{{ number_format($distribution['total'] ?? 0) }}</div>
                                <div class="ctrl-donut-sub">إجمالي الختمات</div>
                            </div>
                        </div>

                        <div class="ctrl-donut-legend">
                            @foreach(($distribution['segments'] ?? []) as $segment)
                                <div class="ctrl-donut-row">
                                    <span class="ctrl-legend-dot" style="background: {{ $segment['color'] }};"></span>
                                    <span>{{ $segment['label'] }}</span>
                                    <strong>{{ number_format($segment['value']) }}</strong>
                                    <span class="ctrl-card-meta">{{ $segment['percent'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            </section>

            <section class="ctrl-bottom">
                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">أحدث المستخدمين</div>
                        <div class="ctrl-card-meta">آخر 8 حسابات</div>
                    </div>

                    @if(count($users) === 0)
                        <div class="ctrl-card-meta">لا يوجد مستخدمون بعد.</div>
                    @else
                        <table class="ctrl-table">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الختمات</th>
                                    <th>الحالة</th>
                                    <th>الالتزام</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                    <tr>
                                        <td>
                                            <div class="ctrl-user-cell">
                                                <span class="ctrl-user-avatar">{{ $user['avatar'] }}</span>
                                                <div>
                                                    <div class="ctrl-user-name">{{ $user['name'] }}</div>
                                                    <div class="ctrl-user-meta">{{ $user['joined_label'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($user['khatmas_count']) }}</td>
                                        <td>
                                            <span class="ctrl-status {{ $user['status'] }}">
                                                <span class="ctrl-status-dot"></span>
                                                {{ $user['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $user['commitment_rate'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">آخر النشاطات</div>
                        <span class="ctrl-live"><span class="ctrl-live-dot"></span> مباشر</span>
                    </div>

                    @if(count($recentActivities) === 0)
                        <div class="ctrl-card-meta">لا توجد نشاطات حتى الآن.</div>
                    @else
                        <div class="ctrl-feed">
                            @foreach($recentActivities as $event)
                                <div class="ctrl-feed-item">
                                    <span class="ctrl-feed-icon" style="{{ $event['icon_style'] ?? '' }}">{{ $event['icon'] }}</span>
                                    <div>
                                        <div class="ctrl-feed-text">{{ $event['message'] }}</div>
                                        <div class="ctrl-feed-time">{{ $event['time_label'] }}</div>

                                        @if(! empty($event['badge_label']))
                                            <span class="ctrl-badge-placeholder">🏅 {{ $event['badge_label'] }}</span>
                                        @else
                                            <span class="ctrl-badge-placeholder">جاهز لإضافة الأوسمة</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">مؤشرات الصحة</div>
                        <div class="ctrl-card-meta">آخر 30 يوم</div>
                    </div>

                    <div class="ctrl-mini-list">
                        @foreach($healthMetrics as $metric)
                            <div class="ctrl-mini">
                                <div class="ctrl-mini-head">
                                    <span class="ctrl-mini-label">{{ $metric['icon'] }} {{ $metric['label'] }}</span>
                                    <span class="ctrl-mini-value">{{ $metric['value'] }}</span>
                                </div>
                                <div class="ctrl-mini-bar">
                                    <div class="ctrl-mini-fill" style="width: {{ $metric['bar_percent'] }}%; background: {{ $metric['bar_color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>
        @else
            <div class="ctrl-section-label">نسخة B المبسطة</div>
            <section class="ctrl-simple-grid">
                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">ملخص المستخدمين</div>
                        <div class="ctrl-card-meta">{{ $stats['range_label'] }}</div>
                    </div>

                    <div class="ctrl-simple-list">
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">إجمالي المستخدمين</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['total_users']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">نشطون في الفترة</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['active_users']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">تسجيلات جديدة</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['new_users']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">غير نشطين 7+ أيام</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['dormant_users_7']) }}</span>
                        </div>
                    </div>
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">ملخص الختمات</div>
                        <div class="ctrl-card-meta">تشغيلي</div>
                    </div>

                    <div class="ctrl-simple-list">
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">إجمالي الختمات</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['total_khatmas']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">ختمات نشطة</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['active_khatmas']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">ختمات مكتملة</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['completed_khatmas']) }}</span>
                        </div>
                        <div class="ctrl-simple-item">
                            <span class="ctrl-simple-label">الصفحات المنجزة</span>
                            <span class="ctrl-simple-value">{{ number_format($stats['completed_pages']) }}</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="ctrl-simple-row">
                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">النشاط الأسبوعي</div>
                        <div class="ctrl-card-meta">
                            مستخدمون {{ $activity['totals']['users'] ?? 0 }} · ختمات {{ $activity['totals']['khatmas'] ?? 0 }} · مكتملة {{ $activity['totals']['completed'] ?? 0 }}
                        </div>
                    </div>

                    <div class="ctrl-simple-list">
                        @foreach(($activity['days'] ?? []) as $day)
                            <div class="ctrl-simple-item">
                                <span class="ctrl-simple-label">{{ $day['label'] }}: {{ $day['users'] }} / {{ $day['khatmas'] }} / {{ $day['completed'] }}</span>
                                <span class="ctrl-simple-value">{{ $day['completed'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">توزيع أنواع الختمات</div>
                        <div class="ctrl-card-meta">{{ number_format($distribution['total'] ?? 0) }} ختمة</div>
                    </div>

                    <div class="ctrl-simple-list">
                        @foreach(($distribution['segments'] ?? []) as $segment)
                            <div class="ctrl-simple-item">
                                <span class="ctrl-simple-label">{{ $segment['label'] }} ({{ $segment['percent'] }}%)</span>
                                <span class="ctrl-simple-value">{{ number_format($segment['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="ctrl-simple-row">
                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">أحدث المستخدمين</div>
                        <div class="ctrl-card-meta">آخر 8 حسابات</div>
                    </div>

                    @if(count($users) === 0)
                        <div class="ctrl-card-meta">لا يوجد مستخدمون بعد.</div>
                    @else
                        <table class="ctrl-table">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الختمات</th>
                                    <th>الحالة</th>
                                    <th>الالتزام</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                    <tr>
                                        <td>
                                            <div class="ctrl-user-cell">
                                                <span class="ctrl-user-avatar">{{ $user['avatar'] }}</span>
                                                <div>
                                                    <div class="ctrl-user-name">{{ $user['name'] }}</div>
                                                    <div class="ctrl-user-meta">{{ $user['joined_label'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($user['khatmas_count']) }}</td>
                                        <td>
                                            <span class="ctrl-status {{ $user['status'] }}">
                                                <span class="ctrl-status-dot"></span>
                                                {{ $user['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $user['commitment_rate'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </article>

                <article class="ctrl-card">
                    <div class="ctrl-card-header">
                        <div class="ctrl-card-title">آخر النشاطات + مؤشرات الصحة</div>
                        <span class="ctrl-live"><span class="ctrl-live-dot"></span> مباشر</span>
                    </div>

                    @if(count($recentActivities) === 0)
                        <div class="ctrl-card-meta">لا توجد نشاطات حتى الآن.</div>
                    @else
                        <div class="ctrl-feed">
                            @foreach(array_slice($recentActivities, 0, 6) as $event)
                                <div class="ctrl-feed-item">
                                    <span class="ctrl-feed-icon" style="{{ $event['icon_style'] ?? '' }}">{{ $event['icon'] }}</span>
                                    <div>
                                        <div class="ctrl-feed-text">{{ $event['message'] }}</div>
                                        <div class="ctrl-feed-time">{{ $event['time_label'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div style="height:0.75rem;"></div>

                    <div class="ctrl-mini-list">
                        @foreach(array_slice($healthMetrics, 0, 3) as $metric)
                            <div class="ctrl-mini">
                                <div class="ctrl-mini-head">
                                    <span class="ctrl-mini-label">{{ $metric['icon'] }} {{ $metric['label'] }}</span>
                                    <span class="ctrl-mini-value">{{ $metric['value'] }}</span>
                                </div>
                                <div class="ctrl-mini-bar">
                                    <div class="ctrl-mini-fill" style="width: {{ $metric['bar_percent'] }}%; background: {{ $metric['bar_color'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>
        @endif
    </div>
</x-filament-panels::page>
