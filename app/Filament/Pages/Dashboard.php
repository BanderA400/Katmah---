<?php

namespace App\Filament\Pages;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaStatus;
use App\Enums\PlanningMethod;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\Surah;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Dashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $title = 'لوحة التحكم';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.dashboard';

    protected static ?string $slug = 'dashboard';

    public array $partialPages = [];

    public string $calendarMonth = '';

    public function mount(): void
    {
        $this->calendarMonth = now()->startOfMonth()->toDateString();
    }

    /**
     * بيانات الويدجتات
     */
    public function getWidgetsData(): array
    {
        $userId = auth()->id();

        $activeKhatmas = Khatma::where('user_id', $userId)
            ->where('status', KhatmaStatus::Active)
            ->get();

        // الصفحات المنجزة والمتبقية
        $totalCompleted = $activeKhatmas->sum('completed_pages');
        $totalPages = $activeKhatmas->sum('total_pages');
        $totalRemaining = $totalPages - $totalCompleted;

        // الـ Streak
        $streak = $this->calculateStreak($userId);

        // نسبة الالتزام
        $commitmentRate = $this->calculateCommitmentRate($userId);

        $nearestEndDateModel = $activeKhatmas
            ->filter(fn (Khatma $khatma): bool => $khatma->expected_end_date !== null)
            ->sortBy(fn (Khatma $khatma) => $khatma->expected_end_date?->toDateString())
            ->first();

        return [
            'total_completed' => $totalCompleted,
            'total_remaining' => $totalRemaining,
            'total_pages' => $totalPages,
            'streak' => $streak,
            'commitment_rate' => $commitmentRate,
            'active_count' => $activeKhatmas->count(),
            'nearest_end_date' => $nearestEndDateModel?->expected_end_date?->translatedFormat('j F Y') ?? '—',
            'nearest_end_date_iso' => $nearestEndDateModel?->expected_end_date?->toDateString(),
        ];
    }

    /**
     * بيانات ورد اليوم
     */
    public function getTodayWirds(): array
    {
        $userId = auth()->id();
        $today = Carbon::today();

        $activeKhatmas = Khatma::where('user_id', $userId)
            ->where('status', KhatmaStatus::Active)
            ->get();
        $todayCompletedByKhatma = DailyRecord::whereIn('khatma_id', $activeKhatmas->pluck('id'))
            ->whereDate('date', $today)
            ->selectRaw('khatma_id, SUM(pages_count) as total_pages')
            ->groupBy('khatma_id')
            ->pluck('total_pages', 'khatma_id');
        $surahs = Surah::query()
            ->orderBy('start_page')
            ->get(['start_page', 'end_page', 'name_arabic']);

        $wirds = [];

        foreach ($activeKhatmas as $khatma) {
            $todayCompletedPages = (int) ($todayCompletedByKhatma[$khatma->id] ?? 0);
            $plan = $this->calculateTodayPlan($khatma, $today, $todayCompletedPages);

            $fromPage = $khatma->current_page;
            [$toPage] = $this->resolveSegmentFromCurrentPage(
                $khatma,
                max($plan['today_remaining_pages'], 1),
            );

            $wirds[] = [
                'id' => $khatma->id,
                'name' => $khatma->name,
                'type' => $khatma->type,
                'from_page' => $fromPage,
                'to_page' => $toPage,
                'daily_pages' => $khatma->daily_pages,
                'surah_name' => $this->resolveSurahNameFromPage($surahs, $fromPage),
                'progress' => $khatma->progress_percentage,
                'completed_pages' => $khatma->completed_pages,
                'total_pages' => $khatma->total_pages,
                'remaining_pages_total' => $plan['remaining_total_pages'],
                'expected_end_date' => $khatma->expected_end_date?->translatedFormat('j F Y') ?? '—',
                'is_started' => $plan['is_started'],
                'today_target_pages' => $plan['today_target_pages'],
                'today_done_pages' => $todayCompletedPages,
                'today_remaining_pages' => $plan['today_remaining_pages'],
                'backlog_pages' => $plan['backlog_pages'],
                'is_done_today' => $plan['today_target_pages'] > 0 && $plan['today_remaining_pages'] === 0,
                'is_rest_day' => $plan['today_target_pages'] === 0 && $plan['is_started'] && $plan['remaining_total_pages'] > 0,
            ];
        }

        return $wirds;
    }

    /**
     * الختمات المتوقفة
     */
    public function getPausedKhatmas(): array
    {
        $paused = Khatma::where('user_id', auth()->id())
            ->where('status', KhatmaStatus::Paused)
            ->orderByDesc('updated_at')
            ->get();

        return $paused->map(fn (Khatma $khatma): array => [
            'id' => $khatma->id,
            'name' => $khatma->name,
            'type' => $khatma->type,
            'progress' => $khatma->progress_percentage,
            'completed_pages' => $khatma->completed_pages,
            'total_pages' => $khatma->total_pages,
        ])->all();
    }

    public function previousCalendarMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth)
            ->startOfMonth()
            ->subMonth()
            ->toDateString();
    }

    public function nextCalendarMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth)
            ->startOfMonth()
            ->addMonth()
            ->toDateString();
    }

    public function getMonthlyCommitmentCalendar(): array
    {
        $monthStart = Carbon::parse($this->calendarMonth)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $today = Carbon::today();
        $userId = (int) auth()->id();

        $completedDates = DailyRecord::query()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->whereDate('date', '>=', $gridStart)
            ->whereDate('date', '<=', $gridEnd)
            ->distinct()
            ->pluck('date')
            ->mapWithKeys(fn ($date): array => [Carbon::parse($date)->toDateString() => true]);

        $firstPlanDateRaw = Khatma::query()
            ->where('user_id', $userId)
            ->min('start_date');
        $firstPlanDate = $firstPlanDateRaw ? Carbon::parse($firstPlanDateRaw)->startOfDay() : null;

        $weeks = [];
        $currentWeek = [];
        $doneDays = 0;
        $missedDays = 0;
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $inMonth = $cursor->month === $monthStart->month && $cursor->year === $monthStart->year;
            $dateKey = $cursor->toDateString();

            $status = 'outside';

            if ($inMonth) {
                if (! $firstPlanDate) {
                    $status = $cursor->gt($today) ? 'future' : 'inactive';
                } elseif ($cursor->lt($firstPlanDate)) {
                    $status = 'inactive';
                } elseif ($cursor->gt($today)) {
                    $status = 'future';
                } elseif (isset($completedDates[$dateKey])) {
                    $status = 'done';
                    $doneDays++;
                } else {
                    $status = 'missed';
                    $missedDays++;
                }
            }

            $currentWeek[] = [
                'day' => $cursor->day,
                'date' => $dateKey,
                'status' => $status,
                'is_today' => $cursor->isSameDay($today),
                'in_month' => $inMonth,
            ];

            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }

            $cursor->addDay();
        }

        $trackedDays = $doneDays + $missedDays;
        $monthCommitment = $trackedDays > 0 ? round(($doneDays / $trackedDays) * 100, 1) : 0;

        return [
            'month_label' => $monthStart->translatedFormat('F Y'),
            'weekdays' => ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'],
            'weeks' => $weeks,
            'done_days' => $doneDays,
            'missed_days' => $missedDays,
            'month_commitment' => $monthCommitment,
        ];
    }

    public function getBadgesData(): array
    {
        $userId = (int) auth()->id();

        $totalPages = (int) DailyRecord::query()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->sum('pages_count');

        $completedKhatmas = (int) Khatma::query()
            ->where('user_id', $userId)
            ->where('status', KhatmaStatus::Completed)
            ->count();

        $activeKhatmas = (int) Khatma::query()
            ->where('user_id', $userId)
            ->where('status', KhatmaStatus::Active)
            ->count();

        $streak = $this->calculateStreak($userId);
        $commitmentRate = $this->calculateCommitmentRate($userId);

        return [
            [
                'title' => 'خطوة البداية',
                'description' => 'تسجيل أول إنجاز في سجل الختمات',
                'is_unlocked' => $totalPages >= 1,
            ],
            [
                'title' => 'التزام أسبوع',
                'description' => 'الحفاظ على 7 أيام متتالية',
                'is_unlocked' => $streak >= 7,
            ],
            [
                'title' => 'توازن ممتاز',
                'description' => 'نسبة التزام 70% أو أكثر',
                'is_unlocked' => $commitmentRate >= 70,
            ],
            [
                'title' => 'خاتم',
                'description' => 'إكمال ختمة واحدة على الأقل',
                'is_unlocked' => $completedKhatmas >= 1,
            ],
            [
                'title' => 'إدارة متعددة',
                'description' => 'تشغيل 3 ختمات نشطة في نفس الوقت',
                'is_unlocked' => $activeKhatmas >= 3,
            ],
        ];
    }

    /**
     * تسجيل كامل ورد اليوم المتبقي
     */
    public function completeWird(int $khatmaId): void
    {
        $this->recordProgress($khatmaId, null);
    }

    /**
     * تسجيل إنجاز جزئي
     */
    public function completePartialWird(int $khatmaId): void
    {
        $requestedPages = (int) ($this->partialPages[$khatmaId] ?? 0);
        $this->recordProgress($khatmaId, $requestedPages);
    }

    /**
     * إيقاف ختمة
     */
    public function pauseKhatma(int $khatmaId): void
    {
        $khatma = Khatma::where('id', $khatmaId)
            ->where('user_id', auth()->id())
            ->where('status', KhatmaStatus::Active)
            ->first();

        if (!$khatma) {
            Notification::make()
                ->title('خطأ')
                ->body('الختمة غير متاحة للإيقاف')
                ->warning()
                ->send();

            return;
        }

        $khatma->update(['status' => KhatmaStatus::Paused]);

        Notification::make()
            ->title('تم الإيقاف')
            ->body("تم إيقاف ختمة \"{$khatma->name}\"")
            ->success()
            ->send();
    }

    /**
     * استئناف ختمة
     */
    public function resumeKhatma(int $khatmaId): void
    {
        $khatma = Khatma::where('id', $khatmaId)
            ->where('user_id', auth()->id())
            ->where('status', KhatmaStatus::Paused)
            ->first();

        if (!$khatma) {
            Notification::make()
                ->title('خطأ')
                ->body('الختمة غير متاحة للاستئناف')
                ->warning()
                ->send();

            return;
        }

        $khatma->update(['status' => KhatmaStatus::Active]);

        Notification::make()
            ->title('تم الاستئناف')
            ->body("تم استئناف ختمة \"{$khatma->name}\"")
            ->success()
            ->send();
    }

    public function rebalanceKhatma(int $khatmaId): void
    {
        $today = Carbon::today();

        $result = DB::transaction(function () use ($khatmaId, $today): array {
            $khatma = Khatma::query()
                ->where('id', $khatmaId)
                ->where('user_id', auth()->id())
                ->where('status', KhatmaStatus::Active)
                ->lockForUpdate()
                ->first();

            if (! $khatma) {
                return ['type' => 'warning', 'message' => 'الختمة غير متاحة لإعادة الموازنة'];
            }

            if ($khatma->start_date && $today->lt($khatma->start_date->copy()->startOfDay())) {
                return ['type' => 'warning', 'message' => 'لا يمكن إعادة الموازنة قبل تاريخ بداية الختمة'];
            }

            $remainingPages = max((int) $khatma->total_pages - (int) $khatma->completed_pages, 0);

            if ($remainingPages <= 0) {
                return ['type' => 'warning', 'message' => 'الختمة مكتملة بالفعل'];
            }

            if ($khatma->planning_method === PlanningMethod::ByDuration) {
                $endDate = $khatma->expected_end_date?->copy()->startOfDay() ?? $today->copy();

                if ($endDate->lt($today)) {
                    $endDate = $today->copy();
                }

                $daysLeft = $today->diffInDays($endDate) + 1;
                $newDailyPages = max((int) ceil($remainingPages / max($daysLeft, 1)), 1);

                $khatma->update([
                    'daily_pages' => $newDailyPages,
                    'expected_end_date' => $endDate,
                ]);

                return [
                    'type' => 'success',
                    'message' => "تمت إعادة الموازنة: {$remainingPages} صفحة على {$daysLeft} يوم.",
                ];
            }

            $dailyPages = max((int) $khatma->daily_pages, 1);
            $daysNeeded = (int) ceil($remainingPages / $dailyPages);
            $newEndDate = $today->copy()->addDays(max($daysNeeded - 1, 0));

            $khatma->update([
                'expected_end_date' => $newEndDate,
            ]);

            return [
                'type' => 'success',
                'message' => "تم تحديث تاريخ الختم المتوقع إلى {$newEndDate->translatedFormat('j F Y')}.",
            ];
        });

        $notification = Notification::make()
            ->title($result['type'] === 'success' ? 'تمت إعادة الموازنة' : 'تنبيه')
            ->body($result['message']);

        if ($result['type'] === 'success') {
            $notification->success()->send();

            return;
        }

        $notification->warning()->send();
    }

    /**
     * حفظ التقدم (كامل أو جزئي)
     */
    private function recordProgress(int $khatmaId, ?int $requestedPages): void
    {
        $today = Carbon::today();

        $result = DB::transaction(function () use ($khatmaId, $requestedPages, $today): array {
            $khatma = Khatma::where('id', $khatmaId)
                ->where('user_id', auth()->id())
                ->where('status', KhatmaStatus::Active)
                ->lockForUpdate()
                ->first();

            if (!$khatma) {
                return ['type' => 'warning', 'message' => 'الختمة غير موجودة أو غير نشطة'];
            }

            if ($khatma->start_date && $today->lt($khatma->start_date->copy()->startOfDay())) {
                return ['type' => 'warning', 'message' => 'الختمة لم تبدأ بعد'];
            }

            $todayCompletedPages = $this->getTodayCompletedPages($khatma->id, $today);
            $plan = $this->calculateTodayPlan($khatma, $today, $todayCompletedPages);
            $remainingToday = $plan['today_remaining_pages'];
            $remainingTotal = max($khatma->total_pages - $khatma->completed_pages, 0);

            if ($remainingTotal === 0) {
                return ['type' => 'warning', 'message' => 'الختمة مكتملة بالفعل'];
            }

            if ($requestedPages === null) {
                if ($remainingToday <= 0) {
                    return ['type' => 'warning', 'message' => 'تم إنجاز ورد اليوم بالفعل'];
                }

                $pagesToRecord = $remainingToday;
            } else {
                if ($requestedPages <= 0) {
                    return ['type' => 'warning', 'message' => 'أدخل عدد صفحات صحيح للإنجاز الجزئي'];
                }

                if ($remainingToday <= 0) {
                    return ['type' => 'warning', 'message' => 'لا يوجد متبقٍ من ورد اليوم'];
                }

                if ($requestedPages > $remainingToday) {
                    return ['type' => 'warning', 'message' => "الحد الأقصى المتبقي اليوم هو {$remainingToday} صفحة"];
                }

                $pagesToRecord = $requestedPages;
            }

            $pagesToRecord = min($pagesToRecord, $remainingTotal);
            [$toPage, $pagesCount] = $this->resolveSegmentFromCurrentPage($khatma, $pagesToRecord);

            if ($pagesCount <= 0) {
                return ['type' => 'warning', 'message' => 'تعذر تسجيل الإنجاز الحالي'];
            }

            DailyRecord::create([
                'khatma_id' => $khatma->id,
                'user_id' => auth()->id(),
                'date' => $today,
                'from_page' => $khatma->current_page,
                'to_page' => $toPage,
                'pages_count' => $pagesCount,
                'is_completed' => true,
                'completed_at' => now(),
            ]);

            $newCompletedPages = min($khatma->completed_pages + $pagesCount, $khatma->total_pages);
            $newCurrentPage = $this->resolveNextCurrentPage($khatma, $toPage);
            $isCompleted = $newCompletedPages >= $khatma->total_pages;

            $khatma->update([
                'current_page' => $newCurrentPage,
                'completed_pages' => $newCompletedPages,
                'status' => $isCompleted ? KhatmaStatus::Completed : KhatmaStatus::Active,
            ]);

            return [
                'type' => 'success',
                'message' => $isCompleted
                    ? "ختمة \"{$khatma->name}\" اكتملت بحمد الله"
                    : "تم تسجيل {$pagesCount} صفحات",
                'title' => $isCompleted ? 'مبارك' : 'تم التسجيل',
            ];
        });

        unset($this->partialPages[$khatmaId]);

        $notification = Notification::make()->title($result['title'] ?? 'تنبيه')->body($result['message']);

        if (($result['type'] ?? 'warning') === 'success') {
            $notification->success()->send();
        } else {
            $notification->warning()->send();
        }
    }

    private function getTodayCompletedPages(int $khatmaId, Carbon $today): int
    {
        return (int) DailyRecord::where('khatma_id', $khatmaId)
            ->whereDate('date', $today)
            ->sum('pages_count');
    }

    private function calculateTodayPlan(Khatma $khatma, Carbon $today, int $todayCompletedPages): array
    {
        $startDate = $khatma->start_date?->copy()->startOfDay();
        $endDate = $khatma->expected_end_date?->copy()->startOfDay();

        $totalPages = max((int) $khatma->total_pages, 0);
        $completedPages = max((int) $khatma->completed_pages, 0);
        $completedBeforeToday = max($completedPages - $todayCompletedPages, 0);
        $remainingTotalPages = max($totalPages - $completedPages, 0);

        $isStarted = !$startDate || !$today->lt($startDate);

        if (!$isStarted || $remainingTotalPages === 0) {
            return [
                'today_target_pages' => 0,
                'today_remaining_pages' => 0,
                'remaining_total_pages' => $remainingTotalPages,
                'backlog_pages' => 0,
                'is_started' => $isStarted,
            ];
        }

        if ($khatma->auto_compensate_missed_days) {
            $daysLeft = $this->calculateDaysLeftInclusive($today, $endDate);
            $remainingBeforeToday = max($totalPages - $completedBeforeToday, 0);
            $todayTargetPages = $daysLeft > 0
                ? (int) ceil($remainingBeforeToday / $daysLeft)
                : $remainingBeforeToday;
        } else {
            $todayTargetPages = $this->calculateBaseTargetForToday($khatma, $today, $startDate, $endDate);
        }

        $todayTargetPages = max(
            min($todayTargetPages, max($totalPages - $completedBeforeToday, 0)),
            0,
        );
        $todayRemainingPages = max(min($todayTargetPages - $todayCompletedPages, $remainingTotalPages), 0);

        $plannedCumulativePages = $this->calculatePlannedCumulativePages($khatma, $today, $startDate, $endDate);
        $backlogPages = max($plannedCumulativePages - $completedPages, 0);

        return [
            'today_target_pages' => $todayTargetPages,
            'today_remaining_pages' => $todayRemainingPages,
            'remaining_total_pages' => $remainingTotalPages,
            'backlog_pages' => $backlogPages,
            'is_started' => $isStarted,
        ];
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

    private function calculatePlannedCumulativePages(
        Khatma $khatma,
        Carbon $today,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): int {
        if (!$startDate || $today->lt($startDate)) {
            return 0;
        }

        $totalPages = (int) $khatma->total_pages;

        if ($khatma->planning_method === PlanningMethod::ByDuration && $endDate) {
            if ($today->gte($endDate)) {
                return $totalPages;
            }

            $totalDays = $startDate->diffInDays($endDate) + 1;
            $dayIndex = $startDate->diffInDays($today) + 1;

            return (int) floor(($totalPages * $dayIndex) / $totalDays);
        }

        $daysElapsed = $startDate->diffInDays($today) + 1;

        return min((int) ($daysElapsed * $khatma->daily_pages), $totalPages);
    }

    private function calculateDaysLeftInclusive(Carbon $today, ?Carbon $endDate): int
    {
        if (!$endDate) {
            return 1;
        }

        if ($today->gt($endDate)) {
            return 1;
        }

        return $today->diffInDays($endDate) + 1;
    }

    private function resolveSegmentFromCurrentPage(Khatma $khatma, int $pagesToRecord): array
    {
        $fromPage = $khatma->current_page;

        if ($pagesToRecord <= 0) {
            return [$fromPage, 0];
        }

        if ($khatma->direction === KhatmaDirection::Backward) {
            $toPage = max($fromPage - $pagesToRecord + 1, $khatma->start_page);
            $pagesCount = max($fromPage - $toPage + 1, 0);

            return [$toPage, $pagesCount];
        }

        $toPage = min($fromPage + $pagesToRecord - 1, $khatma->end_page);
        $pagesCount = max($toPage - $fromPage + 1, 0);

        return [$toPage, $pagesCount];
    }

    private function resolveNextCurrentPage(Khatma $khatma, int $toPage): int
    {
        if ($khatma->direction === KhatmaDirection::Backward) {
            return max($toPage - 1, $khatma->start_page);
        }

        return min($toPage + 1, $khatma->end_page);
    }

    private function resolveSurahNameFromPage(Collection $surahs, int $page): string
    {
        foreach ($surahs as $surah) {
            if ($page >= (int) $surah->start_page && $page <= (int) $surah->end_page) {
                return $surah->name_arabic;
            }
        }

        return '—';
    }

    /**
     * حساب الـ Streak (أيام الالتزام المتتالية)
     */
    private function calculateStreak(int $userId): int
    {
        $completedDates = DailyRecord::where('user_id', $userId)
            ->where('is_completed', true)
            ->distinct()
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [Carbon::parse($date)->toDateString() => true]);

        if ($completedDates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $date = Carbon::today();
        $todayKey = $date->toDateString();

        // إذا ما سجّل اليوم، ابدأ من أمس
        if (!isset($completedDates[$todayKey])) {
            $date = $date->subDay();
        }

        while (true) {
            $hasRecord = isset($completedDates[$date->toDateString()]);

            if (!$hasRecord) break;

            $streak++;
            $date = $date->subDay();
        }

        return $streak;
    }

    /**
     * حساب نسبة الالتزام
     */
    private function calculateCommitmentRate(int $userId): float
    {
        $firstStartDate = Khatma::where('user_id', $userId)->min('start_date');

        if (!$firstStartDate) {
            return 0;
        }

        $today = Carbon::today();
        $start = Carbon::parse($firstStartDate)->startOfDay();

        if ($start->gt($today)) {
            return 0;
        }

        $totalDays = $start->diffInDays($today) + 1;

        if ($totalDays <= 0) {
            return 0;
        }

        $completedDays = DailyRecord::where('user_id', $userId)
            ->where('is_completed', true)
            ->distinct('date')
            ->count('date');

        return round(($completedDays / $totalDays) * 100, 1);
    }
}
