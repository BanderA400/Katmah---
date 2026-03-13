<?php

namespace App\Console\Commands;

use App\Enums\KhatmaStatus;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use App\Notifications\PeriodicProgressReportNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class SendPeriodicReportsCommand extends Command
{
    protected $signature = 'khatma:send-periodic-reports
        {period : weekly|monthly}
        {--date= : Optional date/time anchor (Y-m-d or Y-m-d H:i)}';

    protected $description = 'Send weekly or monthly progress reports as in-app + email notifications.';

    public function handle(): int
    {
        $period = (string) $this->argument('period');

        if (! in_array($period, ['weekly', 'monthly'], true)) {
            $this->error('Period must be weekly or monthly.');

            return self::FAILURE;
        }

        $timezone = (string) config('app.timezone', 'Asia/Riyadh');
        $now = $this->resolveNow($timezone);
        [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($period, $now);
        $rangeLabel = $this->buildRangeLabel($startDate, $endDate);

        $cacheTtl = $period === 'monthly'
            ? $now->copy()->addDays(90)
            : $now->copy()->addDays(21);

        $sent = 0;
        $failed = 0;
        $enabledColumn = $period === 'monthly'
            ? 'monthly_reports_enabled'
            : 'weekly_reports_enabled';

        User::query()
            ->select(['id', 'name', 'email', $enabledColumn])
            ->where($enabledColumn, true)
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use (
                $period,
                $periodLabel,
                $rangeLabel,
                $startDate,
                $endDate,
                $cacheTtl,
                &$sent,
                &$failed,
            ): void {
                $metricsByUser = $this->buildMetricsForUsers($users, $startDate, $endDate, $period);

                foreach ($users as $user) {
                    $idempotencyKey = sprintf(
                        'periodic-report:%s:%s:%s:user:%d',
                        $period,
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                        (int) $user->id,
                    );

                    if (! Cache::add($idempotencyKey, 'processing', now()->addMinutes(15))) {
                        continue;
                    }

                    try {
                        $metrics = $metricsByUser[(int) $user->id] ?? $this->emptyMetrics($period);
                        $notification = new PeriodicProgressReportNotification(
                            period: $period,
                            periodLabel: $periodLabel,
                            rangeLabel: $rangeLabel,
                            metrics: $metrics,
                            dashboardUrl: url('/app'),
                        );

                        // Send mail first; if it fails we avoid persisting duplicate in-app notifications on retries.
                        NotificationFacade::sendNow($user, $notification, ['mail']);
                        NotificationFacade::sendNow($user, $notification, ['database']);

                        Cache::put($idempotencyKey, 'sent', $cacheTtl);
                        $sent++;
                    } catch (Throwable $exception) {
                        Cache::forget($idempotencyKey);
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info("Periodic {$period} reports sent: {$sent}, failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveDateRange(string $period, Carbon $now): array
    {
        if ($period === 'monthly') {
            $month = $now->copy()->subMonthNoOverflow();
            $start = $month->copy()->startOfMonth()->startOfDay();
            $end = $month->copy()->endOfMonth()->startOfDay();

            return [$start, $end, 'الشهر الماضي'];
        }

        $end = $now->copy()->startOfDay();
        $start = $end->copy()->subDays(6);

        return [$start, $end, 'الأسبوع الماضي'];
    }

    private function buildRangeLabel(Carbon $startDate, Carbon $endDate): string
    {
        return sprintf(
            '%s - %s',
            $startDate->translatedFormat('j F Y'),
            $endDate->translatedFormat('j F Y'),
        );
    }

    /**
     * @return array{
     *     pages_completed: int,
     *     done_days: int,
     *     missed_days: int,
     *     commitment_rate: float,
     *     completed_khatmas: int,
     *     active_khatmas: int,
     *     has_progress: bool,
     *     encouragement_message: string
     * }
     */
    private function emptyMetrics(string $period): array
    {
        return [
            'pages_completed' => 0,
            'done_days' => 0,
            'missed_days' => 0,
            'commitment_rate' => 0.0,
            'completed_khatmas' => 0,
            'active_khatmas' => 0,
            'has_progress' => false,
            'encouragement_message' => $this->encouragementMessage($period),
        ];
    }

    /**
     * @return array<int, array{
     *     pages_completed: int,
     *     done_days: int,
     *     missed_days: int,
     *     commitment_rate: float,
     *     completed_khatmas: int,
     *     active_khatmas: int,
     *     has_progress: bool,
     *     encouragement_message: string
     * }>
     */
    private function buildMetricsForUsers(Collection $users, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $userIds = $users
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        $startDateString = $startDate->toDateString();
        $endDateString = $endDate->toDateString();

        $recordStatsByUser = DailyRecord::query()
            ->whereIn('user_id', $userIds)
            ->where('is_completed', true)
            ->whereBetween('date', [$startDateString, $endDateString])
            ->selectRaw('user_id, COALESCE(SUM(pages_count), 0) as pages_completed, COUNT(DISTINCT date) as done_days')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $khatmas = Khatma::query()
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'status', 'start_date']);

        $khatmasByUser = $khatmas->groupBy('user_id');

        $completedKhatmasByUser = DailyRecord::query()
            ->from('daily_records as dr')
            ->join('khatmas as k', function ($join): void {
                $join->on('k.id', '=', 'dr.khatma_id')
                    ->on('k.user_id', '=', 'dr.user_id');
            })
            ->whereIn('dr.user_id', $userIds)
            ->where('dr.is_completed', true)
            ->whereBetween('dr.date', [$startDateString, $endDateString])
            ->where('k.status', KhatmaStatus::Completed)
            ->selectRaw('dr.user_id as user_id, COUNT(DISTINCT dr.khatma_id) as completed_khatmas')
            ->groupBy('dr.user_id')
            ->pluck('completed_khatmas', 'user_id')
            ->all();

        $completedKhatmaIds = $khatmas
            ->filter(static fn (Khatma $khatma): bool => $khatma->status === KhatmaStatus::Completed)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $completedActivityByKhatmaId = [];

        if ($completedKhatmaIds !== []) {
            $completedActivityByKhatmaId = DailyRecord::query()
                ->whereIn('khatma_id', $completedKhatmaIds)
                ->where('is_completed', true)
                ->whereBetween('date', [$startDateString, $endDateString])
                ->selectRaw('khatma_id, MIN(date) as first_completed_date, MAX(date) as last_completed_date')
                ->groupBy('khatma_id')
                ->get()
                ->mapWithKeys(static function (DailyRecord $record): array {
                    return [
                        (int) $record->khatma_id => [
                            'first' => (string) $record->first_completed_date,
                            'last' => (string) $record->last_completed_date,
                        ],
                    ];
                })
                ->all();
        }

        $metricsByUser = [];

        foreach ($userIds as $userId) {
            $recordStats = $recordStatsByUser->get($userId);
            $pagesCompleted = (int) ($recordStats->pages_completed ?? 0);
            $doneDays = (int) ($recordStats->done_days ?? 0);

            /** @var Collection<int, Khatma> $userKhatmas */
            $userKhatmas = $khatmasByUser->get($userId, collect());
            $activeCount = (int) $userKhatmas
                ->filter(static fn (Khatma $khatma): bool => $khatma->status === KhatmaStatus::Active)
                ->count();

            $completedKhatmas = (int) ($completedKhatmasByUser[$userId] ?? 0);
            $expectedDays = $this->calculateExpectedDaysForUser(
                $userKhatmas,
                $startDate,
                $endDate,
                $completedActivityByKhatmaId,
            );

            $missedDays = max($expectedDays - $doneDays, 0);
            $commitmentRate = $expectedDays > 0
                ? round(($doneDays / $expectedDays) * 100, 1)
                : 0.0;

            $hasProgress = $pagesCompleted > 0 || $doneDays > 0;

            $metricsByUser[$userId] = [
                'pages_completed' => $pagesCompleted,
                'done_days' => $doneDays,
                'missed_days' => $missedDays,
                'commitment_rate' => $commitmentRate,
                'completed_khatmas' => $completedKhatmas,
                'active_khatmas' => $activeCount,
                'has_progress' => $hasProgress,
                'encouragement_message' => $this->encouragementMessage($period),
            ];
        }

        return $metricsByUser;
    }

    /**
     * @param  Collection<int, Khatma>  $userKhatmas
     * @param  array<int, array{first: string, last: string}>  $completedActivityByKhatmaId
     */
    private function calculateExpectedDaysForUser(
        Collection $userKhatmas,
        Carbon $startDate,
        Carbon $endDate,
        array $completedActivityByKhatmaId,
    ): int {
        $intervals = [];

        foreach ($userKhatmas as $khatma) {
            if (! $khatma instanceof Khatma) {
                continue;
            }

            if (! in_array($khatma->status, [KhatmaStatus::Active, KhatmaStatus::Completed], true)) {
                continue;
            }

            $khatmaStart = $khatma->start_date instanceof Carbon
                ? $khatma->start_date->copy()->startOfDay()
                : $startDate->copy();

            if ($khatmaStart->gt($endDate)) {
                continue;
            }

            $intervalStart = $khatmaStart->gt($startDate)
                ? $khatmaStart
                : $startDate->copy();

            $intervalEnd = $endDate->copy();

            if ($khatma->status === KhatmaStatus::Completed) {
                $activity = $completedActivityByKhatmaId[(int) $khatma->id] ?? null;

                if (
                    ! is_array($activity)
                    || ! is_string($activity['first'] ?? null)
                    || ! is_string($activity['last'] ?? null)
                    || trim((string) $activity['first']) === ''
                    || trim((string) $activity['last']) === ''
                ) {
                    continue;
                }

                $firstCompletedDate = Carbon::parse((string) $activity['first'])->startOfDay();
                $lastCompletedDate = Carbon::parse((string) $activity['last'])->startOfDay();

                if ($firstCompletedDate->gt($intervalStart)) {
                    $intervalStart = $firstCompletedDate;
                }

                if ($lastCompletedDate->lt($intervalStart)) {
                    continue;
                }

                if ($lastCompletedDate->lt($intervalEnd)) {
                    $intervalEnd = $lastCompletedDate;
                }
            }

            if ($intervalEnd->lt($intervalStart)) {
                continue;
            }

            $intervals[] = [
                'start' => $intervalStart,
                'end' => $intervalEnd,
            ];
        }

        if ($intervals === []) {
            return 0;
        }

        usort($intervals, static function (array $left, array $right): int {
            /** @var Carbon $leftStart */
            $leftStart = $left['start'];
            /** @var Carbon $rightStart */
            $rightStart = $right['start'];

            return $leftStart->getTimestamp() <=> $rightStart->getTimestamp();
        });

        /** @var Carbon $currentStart */
        $currentStart = $intervals[0]['start']->copy();
        /** @var Carbon $currentEnd */
        $currentEnd = $intervals[0]['end']->copy();
        $totalDays = 0;

        foreach (array_slice($intervals, 1) as $interval) {
            /** @var Carbon $nextStart */
            $nextStart = $interval['start'];
            /** @var Carbon $nextEnd */
            $nextEnd = $interval['end'];

            if ($nextStart->lte($currentEnd->copy()->addDay())) {
                if ($nextEnd->gt($currentEnd)) {
                    $currentEnd = $nextEnd->copy();
                }

                continue;
            }

            $totalDays += $currentStart->diffInDays($currentEnd) + 1;
            $currentStart = $nextStart->copy();
            $currentEnd = $nextEnd->copy();
        }

        $totalDays += $currentStart->diffInDays($currentEnd) + 1;

        return $totalDays;
    }

    private function encouragementMessage(string $period): string
    {
        return $period === 'monthly'
            ? 'شهر جديد فرصة جديدة، ابدأ بخطة خفيفة وثابتة وتدرّج.'
            : 'أسبوع هادئ، البداية ممكنة اليوم. صفحة واحدة يوميًا تصنع فرقًا كبيرًا.';
    }

    private function resolveNow(string $timezone): Carbon
    {
        $dateOption = $this->option('date');

        if (! is_string($dateOption) || trim($dateOption) === '') {
            return now($timezone);
        }

        try {
            return Carbon::parse(trim($dateOption), $timezone);
        } catch (Throwable) {
            return now($timezone);
        }
    }
}
