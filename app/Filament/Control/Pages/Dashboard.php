<?php

namespace App\Filament\Control\Pages;

use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use App\Support\AppSettings;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'التحليلات';

    protected static ?string $title = 'مركز التحكم';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.control.pages.dashboard';

    protected static ?string $slug = 'dashboard';

    public string $period = 'week';

    public string $variant = 'rich';

    public string $lastRefreshedAt = '';

    public function mount(): void
    {
        $this->lastRefreshedAt = now()->format('H:i');
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', 'week', 'month', 'all'], true)) {
            return;
        }

        $this->period = $period;
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->lastRefreshedAt = now()->format('H:i');
    }

    public function getPeriodOptions(): array
    {
        return [
            'today' => 'اليوم',
            'week' => 'الأسبوع',
            'month' => 'الشهر',
            'all' => 'الكل',
        ];
    }

    public function getMainStats(): array
    {
        [$startDate, $endDate, $rangeLabel] = $this->resolveDateRangeForPeriod();
        $today = Carbon::today();

        $totalUsers = (int) User::query()->count();
        $newUsers = (int) $this->applyDateRange(
            User::query(),
            'created_at',
            $startDate,
            $endDate,
        )->count();

        $activeUsers = (int) $this->applyDateRange(
            DailyRecord::query()->where('is_completed', true),
            'date',
            $startDate,
            $endDate,
        )
            ->distinct('user_id')
            ->count('user_id');

        $activeUsersToday = (int) DailyRecord::query()
            ->where('is_completed', true)
            ->whereDate('date', $today)
            ->distinct('user_id')
            ->count('user_id');

        $dormantUsers7 = (int) User::query()
            ->whereDoesntHave('dailyRecords', function (Builder $query) use ($today): void {
                $query->where('is_completed', true)
                    ->whereDate('date', '>=', $today->copy()->subDays(6));
            })
            ->count();

        $totalKhatmas = (int) Khatma::query()->count();
        $activeKhatmas = (int) Khatma::query()->where('status', KhatmaStatus::Active)->count();
        $completedKhatmas = (int) Khatma::query()->where('status', KhatmaStatus::Completed)->count();
        $pausedKhatmas = (int) Khatma::query()->where('status', KhatmaStatus::Paused)->count();
        $newKhatmas = (int) $this->applyDateRange(
            Khatma::query(),
            'created_at',
            $startDate,
            $endDate,
        )->count();

        $completedPages = (int) $this->applyDateRange(
            DailyRecord::query()->where('is_completed', true),
            'date',
            $startDate,
            $endDate,
        )
            ->sum('pages_count');

        $avgKhatmasPerUser = $totalUsers > 0
            ? round($totalKhatmas / $totalUsers, 1)
            : 0;

        $completionRate = $totalKhatmas > 0
            ? round(($completedKhatmas / $totalKhatmas) * 100, 1)
            : 0;

        $activeKhatmaShare = $totalKhatmas > 0
            ? round(($activeKhatmas / $totalKhatmas) * 100, 1)
            : 0;

        return [
            'range_label' => $rangeLabel,
            'total_users' => $totalUsers,
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'active_users_today' => $activeUsersToday,
            'dormant_users_7' => $dormantUsers7,
            'total_khatmas' => $totalKhatmas,
            'new_khatmas' => $newKhatmas,
            'active_khatmas' => $activeKhatmas,
            'completed_khatmas' => $completedKhatmas,
            'paused_khatmas' => $pausedKhatmas,
            'completed_pages' => $completedPages,
            'avg_khatmas_per_user' => $avgKhatmasPerUser,
            'completion_rate' => $completionRate,
            'active_khatma_share' => $activeKhatmaShare,
        ];
    }

    public function getWeeklyActivitySeries(): array
    {
        $start = Carbon::today()->subDays(6);
        $end = Carbon::today();

        $newUsersByDate = User::query()
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->day => (int) $row->total]);

        $newKhatmasByDate = Khatma::query()
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->day => (int) $row->total]);

        $completedByDate = DailyRecord::query()
            ->where('is_completed', true)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->selectRaw('date as day, COUNT(DISTINCT khatma_id) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row): array => [Carbon::parse($row->day)->toDateString() => (int) $row->total]);

        $weekdayNames = [
            Carbon::SUNDAY => 'أحد',
            Carbon::MONDAY => 'إثنين',
            Carbon::TUESDAY => 'ثلاثاء',
            Carbon::WEDNESDAY => 'أربعاء',
            Carbon::THURSDAY => 'خميس',
            Carbon::FRIDAY => 'جمعة',
            Carbon::SATURDAY => 'سبت',
        ];

        $days = [];
        $maxValue = 1;
        $totals = [
            'users' => 0,
            'khatmas' => 0,
            'completed' => 0,
        ];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->copy()->addDays($offset);
            $key = $date->toDateString();
            $users = (int) ($newUsersByDate[$key] ?? 0);
            $khatmas = (int) ($newKhatmasByDate[$key] ?? 0);
            $completed = (int) ($completedByDate[$key] ?? 0);

            $days[] = [
                'label' => $weekdayNames[$date->dayOfWeek] ?? $date->format('d/m'),
                'users' => $users,
                'khatmas' => $khatmas,
                'completed' => $completed,
            ];

            $maxValue = max($maxValue, $users, $khatmas, $completed);
            $totals['users'] += $users;
            $totals['khatmas'] += $khatmas;
            $totals['completed'] += $completed;
        }

        return [
            'days' => $days,
            'max_value' => $maxValue,
            'totals' => $totals,
        ];
    }

    public function getKhatmaTypeDistribution(): array
    {
        $rows = Khatma::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($row): array {
                $type = $row->type instanceof \BackedEnum
                    ? $row->type->value
                    : (string) $row->type;

                return [$type => (int) $row->total];
            });

        $colors = [
            KhatmaType::Hifz->value => '#6D28D9',
            KhatmaType::Review->value => '#10B981',
            KhatmaType::Tilawa->value => '#F59E0B',
        ];

        $total = max(array_sum($rows->all()), 0);
        $segments = [];

        foreach (KhatmaType::cases() as $typeCase) {
            $value = (int) ($rows[$typeCase->value] ?? 0);
            $segments[] = [
                'type' => $typeCase,
                'label' => (string) $typeCase->getLabel(),
                'value' => $value,
                'percent' => $total > 0 ? round(($value / $total) * 100, 1) : 0,
                'color' => $colors[$typeCase->value] ?? '#6B7280',
            ];
        }

        return [
            'total' => $total,
            'segments' => $segments,
        ];
    }

    public function getLatestUsersTable(): array
    {
        $users = User::query()
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'created_at']);

        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id');
        $today = Carbon::today();

        $khatmasByUser = Khatma::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $lastCompletedByUser = DailyRecord::query()
            ->where('is_completed', true)
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, MAX(completed_at) as last_completed_at')
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->user_id => $row->last_completed_at]);

        $firstStartByUser = Khatma::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, MIN(start_date) as first_start')
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->user_id => $row->first_start]);

        $completedDaysByUser = DailyRecord::query()
            ->where('is_completed', true)
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(DISTINCT date) as completed_days')
            ->groupBy('user_id')
            ->pluck('completed_days', 'user_id');

        return $users->map(function (User $user) use (
            $khatmasByUser,
            $lastCompletedByUser,
            $firstStartByUser,
            $completedDaysByUser,
            $today,
        ): array {
            $lastCompletedAt = $lastCompletedByUser[$user->id] ?? null;
            $isActive = $lastCompletedAt
                ? Carbon::parse($lastCompletedAt)->gte($today->copy()->subDays(6))
                : false;

            $commitmentRate = 0.0;
            $firstStart = $firstStartByUser[$user->id] ?? null;

            if ($firstStart) {
                $start = Carbon::parse($firstStart)->startOfDay();
                if ($start->lte($today)) {
                    $totalDays = $start->diffInDays($today) + 1;
                    $completedDays = (int) ($completedDaysByUser[$user->id] ?? 0);
                    if ($totalDays > 0) {
                        $commitmentRate = round(min(($completedDays / $totalDays) * 100, 100), 1);
                    }
                }
            }

            return [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'avatar' => mb_strtoupper(mb_substr((string) $user->name, 0, 1)),
                'joined_label' => $this->formatJoinedLabel($user->created_at),
                'khatmas_count' => (int) ($khatmasByUser[$user->id] ?? 0),
                'status' => $isActive ? 'active' : 'idle',
                'status_label' => $isActive ? 'نشط' : 'خامل',
                'commitment_rate' => $commitmentRate,
            ];
        })->all();
    }

    public function getRecentActivities(): array
    {
        $activityLimit = max(min((int) AppSettings::get(
            AppSettings::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT,
            12,
        ), 100), 5);

        $recordEvents = DailyRecord::query()
            ->where('is_completed', true)
            ->with([
                'user:id,name',
                'khatma:id,name',
            ])
            ->latest('completed_at')
            ->limit($activityLimit * 2)
            ->get()
            ->map(function (DailyRecord $record): array {
                $timestamp = $record->completed_at ?? $record->created_at ?? now();

                return [
                    'type' => 'daily_record_completed',
                    'icon' => '✅',
                    'icon_style' => 'background:rgba(16,185,129,0.15);',
                    'message' => sprintf(
                        '%s أتم %s (%d صفحات)',
                        $record->user?->name ? "المستخدم {$record->user->name}" : 'مستخدم',
                        $record->khatma?->name ? "ورده في {$record->khatma->name}" : 'ورده اليومي',
                        (int) $record->pages_count,
                    ),
                    'timestamp' => $timestamp,
                ];
            });

        $khatmaEvents = Khatma::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(max((int) ceil($activityLimit * 1.5), $activityLimit))
            ->get()
            ->map(function (Khatma $khatma): array {
                $timestamp = $khatma->created_at ?? now();

                return [
                    'type' => 'khatma_created',
                    'icon' => '📖',
                    'icon_style' => 'background:rgba(109,40,217,0.15);',
                    'message' => sprintf(
                        '%s أنشأ ختمة جديدة (%s)',
                        $khatma->user?->name ? "المستخدم {$khatma->user->name}" : 'مستخدم',
                        $khatma->name,
                    ),
                    'timestamp' => $timestamp,
                ];
            });

        $userEvents = User::query()
            ->latest('created_at')
            ->limit(max((int) ceil($activityLimit * 1.5), $activityLimit))
            ->get()
            ->map(function (User $user): array {
                $timestamp = $user->created_at ?? now();

                return [
                    'type' => 'user_registered',
                    'icon' => '🆕',
                    'icon_style' => 'background:rgba(59,130,246,0.15);',
                    'message' => "تسجيل مستخدم جديد ({$user->name})",
                    'timestamp' => $timestamp,
                ];
            });

        $events = collect()
            ->concat($recordEvents)
            ->concat($khatmaEvents)
            ->concat($userEvents)
            ->sortByDesc(fn (array $event) => Carbon::parse($event['timestamp'])->getTimestamp())
            ->take($activityLimit)
            ->values();

        return $events->map(function (array $event): array {
            return [
                'icon' => $event['icon'],
                'icon_style' => $event['icon_style'],
                'message' => $event['message'],
                'time_label' => $this->formatRelativeTime(Carbon::parse($event['timestamp'])),
            ];
        })->all();
    }

    public function getHealthMetrics(): array
    {
        $today = Carbon::today();
        $highestStreak = $this->calculateHighestStreak();
        $averageCommitment = $this->calculateAverageCommitmentRate();

        $activeKhatmas = (int) Khatma::query()
            ->where('status', KhatmaStatus::Active)
            ->count();
        $khatmasDoneToday = (int) DailyRecord::query()
            ->where('is_completed', true)
            ->whereDate('date', $today)
            ->distinct('khatma_id')
            ->count('khatma_id');

        $dailyCompletionRate = $activeKhatmas > 0
            ? round(min(($khatmasDoneToday / $activeKhatmas) * 100, 100), 1)
            : 0;

        $avgUserAgeDays = (float) User::query()
            ->get(['created_at'])
            ->avg(fn (User $user): int => $user->created_at?->diffInDays($today) ?? 0);
        $avgUserAgeDays = round($avgUserAgeDays, 1);

        $dueThisWeek = (int) Khatma::query()
            ->where('status', KhatmaStatus::Active)
            ->whereNotNull('expected_end_date')
            ->whereDate('expected_end_date', '>=', $today)
            ->whereDate('expected_end_date', '<=', $today->copy()->addDays(6))
            ->count();

        return [
            [
                'icon' => '🔥',
                'label' => 'أعلى streak نشط',
                'value' => "{$highestStreak} يوم",
                'bar_percent' => min(round(($highestStreak / 90) * 100), 100),
                'bar_color' => '#F59E0B',
            ],
            [
                'icon' => '📊',
                'label' => 'متوسط الالتزام العام',
                'value' => "{$averageCommitment}%",
                'bar_percent' => min($averageCommitment, 100),
                'bar_color' => '#6D28D9',
            ],
            [
                'icon' => '⚡',
                'label' => 'معدل إكمال الورد اليومي',
                'value' => "{$dailyCompletionRate}%",
                'bar_percent' => min($dailyCompletionRate, 100),
                'bar_color' => '#10B981',
            ],
            [
                'icon' => '📅',
                'label' => 'متوسط عمر المستخدم',
                'value' => "{$avgUserAgeDays} يوم",
                'bar_percent' => min(round(($avgUserAgeDays / 90) * 100), 100),
                'bar_color' => '#3B82F6',
            ],
            [
                'icon' => '🎯',
                'label' => 'ختمات متوقعة هذا الأسبوع',
                'value' => "{$dueThisWeek} ختمة",
                'bar_percent' => min(round(($dueThisWeek / max($activeKhatmas, 1)) * 100), 100),
                'bar_color' => '#D97706',
            ],
        ];
    }

    public function getTopActiveUsers(): array
    {
        $start30 = Carbon::today()->subDays(29);

        return DailyRecord::query()
            ->where('is_completed', true)
            ->whereDate('date', '>=', $start30)
            ->join('users', 'users.id', '=', 'daily_records.user_id')
            ->selectRaw('users.id, users.name, users.email, SUM(daily_records.pages_count) as pages_total, COUNT(DISTINCT daily_records.date) as active_days')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('pages_total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'pages_total' => (int) $row->pages_total,
                'active_days' => (int) $row->active_days,
            ])
            ->all();
    }

    private function resolveDateRangeForPeriod(): array
    {
        $today = Carbon::today();

        return match ($this->period) {
            'today' => [$today->copy(), $today->copy(), 'اليوم'],
            'month' => [$today->copy()->subDays(29), $today->copy(), 'آخر 30 يوم'],
            'all' => [null, null, 'كل الفترة'],
            default => [$today->copy()->subDays(6), $today->copy(), 'آخر 7 أيام'],
        };
    }

    private function applyDateRange(
        Builder $query,
        string $column,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): Builder {
        if ($startDate) {
            $query->whereDate($column, '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate($column, '<=', $endDate);
        }

        return $query;
    }

    private function calculateHighestStreak(): int
    {
        $today = Carbon::today();

        $records = DailyRecord::query()
            ->where('is_completed', true)
            ->whereDate('date', '>=', $today->copy()->subDays(180))
            ->get(['user_id', 'date'])
            ->groupBy('user_id');

        $highest = 0;

        foreach ($records as $userRecords) {
            $dates = $userRecords
                ->pluck('date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique()
                ->flip();

            $current = $today->copy();
            if (! isset($dates[$current->toDateString()])) {
                $current->subDay();
            }

            $streak = 0;
            while (isset($dates[$current->toDateString()])) {
                $streak++;
                $current->subDay();
            }

            $highest = max($highest, $streak);
        }

        return $highest;
    }

    private function calculateAverageCommitmentRate(): float
    {
        $today = Carbon::today();

        $firstStartByUser = Khatma::query()
            ->selectRaw('user_id, MIN(start_date) as first_start')
            ->groupBy('user_id')
            ->get();

        if ($firstStartByUser->isEmpty()) {
            return 0.0;
        }

        $completedDaysByUser = DailyRecord::query()
            ->where('is_completed', true)
            ->selectRaw('user_id, COUNT(DISTINCT date) as completed_days')
            ->groupBy('user_id')
            ->pluck('completed_days', 'user_id');

        $rates = [];

        foreach ($firstStartByUser as $item) {
            if (! $item->first_start) {
                continue;
            }

            $start = Carbon::parse($item->first_start)->startOfDay();

            if ($start->gt($today)) {
                continue;
            }

            $totalDays = $start->diffInDays($today) + 1;
            if ($totalDays <= 0) {
                continue;
            }

            $completedDays = (int) ($completedDaysByUser[$item->user_id] ?? 0);
            $rates[] = round(min(($completedDays / $totalDays) * 100, 100), 1);
        }

        if (count($rates) === 0) {
            return 0.0;
        }

        return round(array_sum($rates) / count($rates), 1);
    }

    private function formatRelativeTime(Carbon $timestamp): string
    {
        $now = now();

        if ($timestamp->greaterThan($now)) {
            return 'الآن';
        }

        $minutes = $timestamp->diffInMinutes($now);
        if ($minutes < 1) {
            return 'الآن';
        }

        if ($minutes < 60) {
            return "منذ {$minutes} دقيقة";
        }

        $hours = $timestamp->diffInHours($now);
        if ($hours < 24) {
            return "منذ {$hours} ساعة";
        }

        $days = $timestamp->diffInDays($now);

        return "منذ {$days} يوم";
    }

    private function formatJoinedLabel(Carbon $createdAt): string
    {
        $days = $createdAt->diffInDays(now());

        if ($days <= 0) {
            return 'اليوم';
        }

        if ($days === 1) {
            return 'منذ يوم';
        }

        return "منذ {$days} يوم";
    }
}
