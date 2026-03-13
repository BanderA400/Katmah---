<?php

namespace App\Console\Commands;

use App\Enums\KhatmaStatus;
use App\Enums\PlanningMethod;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use App\Notifications\DelayedWirdReminderNotification;
use App\Support\SmartKhatmaPlanner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendWirdRemindersCommand extends Command
{
    protected $signature = 'khatma:send-wird-reminders {--time=}';

    protected $description = 'Send delayed wird reminders as in-app and email notifications.';

    public function handle(): int
    {
        $timezone = (string) config('app.timezone', 'Asia/Riyadh');
        $now = $this->resolveNow($timezone);
        $today = $now->copy()->startOfDay();
        $reminderSlot = $now->format('Y-m-d-H:i');
        $reminderTime = $now->format('H:i:00');

        $dueKhatmasByUser = $this->resolveDueKhatmasByUser($reminderTime);

        if ($dueKhatmasByUser === []) {
            $this->info("No khatmas matched reminder time {$reminderTime}.");

            return self::SUCCESS;
        }

        $sentUsers = 0;
        $sentItems = 0;
        $failedUsers = 0;

        foreach ($dueKhatmasByUser as $entry) {
            /** @var User $user */
            $user = $entry['user'];
            /** @var Collection<int, Khatma> $khatmas */
            $khatmas = $entry['khatmas'];
            $payload = $this->buildDelayedPayloadForKhatmas($khatmas, $today);

            if ($payload === []) {
                continue;
            }

            $idempotencyKey = "wird-reminder:{$reminderSlot}:user:{$user->id}";
            $processingExpiry = $now->copy()->addMinutes(10);

            // Reserve key briefly while sending, then finalize only on success.
            if (! Cache::add($idempotencyKey, 'processing', $processingExpiry)) {
                continue;
            }

            try {
                $user->notify(new DelayedWirdReminderNotification(
                    items: $payload,
                    dashboardUrl: url('/app'),
                ));

                Cache::put($idempotencyKey, 'sent', $today->copy()->addDay());
                $sentUsers++;
                $sentItems += count($payload);
            } catch (Throwable $exception) {
                Cache::forget($idempotencyKey);
                report($exception);
                $failedUsers++;
            }
        }

        $this->info("Sent reminders to {$sentUsers} users for {$sentItems} delayed khatmas. Failed users: {$failedUsers}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{user: User, khatmas: Collection<int, Khatma>}>
     */
    private function resolveDueKhatmasByUser(string $reminderTime): array
    {
        $activeKhatmas = Khatma::query()
            ->where('status', KhatmaStatus::Active)
            ->where(function ($query) use ($reminderTime): void {
                $query
                    ->where(function ($customQuery) use ($reminderTime): void {
                        $customQuery
                            ->where('use_custom_reminder_settings', true)
                            ->where('reminder_enabled', true)
                            ->whereTime('reminder_time', '=', $reminderTime);
                    })
                    ->orWhere(function ($defaultQuery) use ($reminderTime): void {
                        $defaultQuery
                            ->where(function ($customFlagQuery): void {
                                $customFlagQuery
                                    ->where('use_custom_reminder_settings', false)
                                    ->orWhereNull('use_custom_reminder_settings');
                            })
                            ->whereHas('user', function ($userQuery) use ($reminderTime): void {
                                $userQuery
                                    ->where('wird_reminders_enabled', true)
                                    ->whereTime('wird_reminders_time', '=', $reminderTime);
                            });
                    });
            })
            ->with([
                'user:id,name,email,wird_reminders_enabled,wird_reminders_time',
            ])
            ->get();

        $grouped = [];

        foreach ($activeKhatmas as $khatma) {
            $user = $khatma->user;

            if (! $user instanceof User) {
                continue;
            }

            if (! isset($grouped[$user->id])) {
                $grouped[$user->id] = [
                    'user' => $user,
                    'khatmas' => collect(),
                ];
            }

            $grouped[$user->id]['khatmas']->push($khatma);
        }

        return $grouped;
    }

    /**
     * @return array<int, array{name: string, remaining_today: int, remaining_total: int}>
     */
    private function buildDelayedPayloadForKhatmas(Collection $khatmas, Carbon $today): array
    {
        if ($khatmas->isEmpty()) {
            return [];
        }

        $todayDate = $today->toDateString();
        $tomorrowDate = $today->copy()->addDay()->toDateString();

        $todayCompletedByKhatma = DailyRecord::query()
            ->whereIn('khatma_id', $khatmas->pluck('id'))
            ->where('date', '>=', $todayDate)
            ->where('date', '<', $tomorrowDate)
            ->selectRaw('khatma_id, SUM(pages_count) as total_pages')
            ->groupBy('khatma_id')
            ->pluck('total_pages', 'khatma_id');

        $items = [];

        foreach ($khatmas as $khatma) {
            $todayCompletedPages = (int) ($todayCompletedByKhatma[$khatma->id] ?? 0);
            $plan = $this->calculateReminderPlan($khatma, $today, $todayCompletedPages);

            if ((int) ($plan['today_remaining_pages'] ?? 0) <= 0) {
                continue;
            }

            $items[] = [
                'name' => (string) $khatma->name,
                'remaining_today' => (int) $plan['today_remaining_pages'],
                'remaining_total' => (int) $plan['remaining_total_pages'],
            ];
        }

        return $items;
    }

    /**
     * @return array{today_remaining_pages: int, remaining_total_pages: int}
     */
    private function calculateReminderPlan(Khatma $khatma, Carbon $today, int $todayCompletedPages): array
    {
        $startDate = $khatma->start_date?->copy()->startOfDay();
        $endDate = $khatma->expected_end_date?->copy()->startOfDay();

        $totalPages = max((int) $khatma->total_pages, 0);
        $completedPages = max((int) $khatma->completed_pages, 0);
        $completedBeforeToday = max($completedPages - $todayCompletedPages, 0);
        $remainingTotalPages = max($totalPages - $completedPages, 0);

        $isStarted = ! $startDate || ! $today->lt($startDate);

        if (! $isStarted || $remainingTotalPages === 0) {
            return [
                'today_remaining_pages' => 0,
                'remaining_total_pages' => $remainingTotalPages,
            ];
        }

        if ($khatma->auto_compensate_missed_days) {
            $daysLeft = $this->calculateDaysLeftInclusive($today, $endDate);
            $remainingBeforeToday = max($totalPages - $completedBeforeToday, 0);

            if ($khatma->planning_method === PlanningMethod::ByDuration) {
                $smartTarget = $this->resolveSmartDurationTarget($khatma, $today, $remainingBeforeToday, $daysLeft);
                $todayTargetPages = $smartTarget > 0
                    ? $smartTarget
                    : SmartKhatmaPlanner::roundDailyTarget(
                        SmartKhatmaPlanner::calculateRawDailyTarget($remainingBeforeToday, $daysLeft),
                        $remainingBeforeToday,
                    );
            } else {
                $todayTargetPages = $daysLeft > 0
                    ? (int) ceil($remainingBeforeToday / $daysLeft)
                    : $remainingBeforeToday;
            }
        } else {
            $todayTargetPages = $this->calculateBaseTargetForToday($khatma, $today, $startDate, $endDate);
        }

        $todayTargetPages = max(min($todayTargetPages, max($totalPages - $completedBeforeToday, 0)), 0);
        $todayRemainingPages = max(min($todayTargetPages - $todayCompletedPages, $remainingTotalPages), 0);

        return [
            'today_remaining_pages' => $todayRemainingPages,
            'remaining_total_pages' => $remainingTotalPages,
        ];
    }

    private function resolveSmartDurationTarget(
        Khatma $khatma,
        Carbon $today,
        int $remainingBeforeToday,
        int $daysLeft,
    ): int {
        $resolution = SmartKhatmaPlanner::resolveAutoExtension(
            $remainingBeforeToday,
            max($daysLeft, 1),
            (int) ($khatma->smart_extension_days_used ?? 0),
        );

        if ((bool) $resolution['needs_higher_daily_pages']) {
            return (int) $resolution['suggested_daily_pages'];
        }

        $effectiveDays = max((int) ($resolution['days_after_extension'] ?? $daysLeft), 1);

        return SmartKhatmaPlanner::roundDailyTarget(
            SmartKhatmaPlanner::calculateRawDailyTarget($remainingBeforeToday, $effectiveDays),
            $remainingBeforeToday,
        );
    }

    private function calculateBaseTargetForToday(
        Khatma $khatma,
        Carbon $today,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): int {
        if ($khatma->planning_method === PlanningMethod::ByDuration && $startDate && $endDate) {
            if ($today->lt($startDate)) {
                return 0;
            }

            if ($today->gt($endDate)) {
                return max((int) $khatma->daily_pages, 0);
            }

            $totalDays = $startDate->diffInDays($endDate) + 1;
            $dayIndex = $startDate->diffInDays($today) + 1;
            $totalPages = (int) $khatma->total_pages;

            $cumulativeToday = (int) floor(($totalPages * $dayIndex) / $totalDays);
            $cumulativeYesterday = (int) floor(($totalPages * max($dayIndex - 1, 0)) / $totalDays);

            return max($cumulativeToday - $cumulativeYesterday, 0);
        }

        return max((int) $khatma->daily_pages, 0);
    }

    private function calculateDaysLeftInclusive(Carbon $today, ?Carbon $endDate): int
    {
        if (! $endDate || $today->gt($endDate)) {
            return 1;
        }

        return $today->diffInDays($endDate) + 1;
    }

    private function resolveNow(string $timezone): Carbon
    {
        $timeOption = $this->option('time');
        $base = now($timezone);

        if (! is_string($timeOption) || trim($timeOption) === '') {
            return $base;
        }

        $parts = explode(':', trim($timeOption));

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return $base;
        }

        $hour = max(min((int) $parts[0], 23), 0);
        $minute = max(min((int) $parts[1], 59), 0);

        return $base->copy()->setTime($hour, $minute, 0);
    }
}
