<?php

namespace App\Filament\Pages;

use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\Surah;
use App\Support\AppSettings;
use App\Support\HistoryRecordFilters;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class History extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'سجل الإنجاز';

    protected static ?string $title = 'سجل الإنجاز';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.history';

    protected static ?string $slug = 'history';

    public string $recordsView = '30_days';

    public string $typeFilter = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->recordsView = $this->resolveDefaultRecordsView();
    }

    /**
     * إحصائيات عامة
     */
    public function getStatsData(): array
    {
        $userId = auth()->id();

        $statsRecords = $this->buildRecordsQuery()
            ->get(['id', 'date', 'pages_count']);

        $totalRecords = $statsRecords->count();
        $totalPages = (int) $statsRecords->sum('pages_count');

        $completedKhatmas = Khatma::where('user_id', $userId)
            ->where('status', KhatmaStatus::Completed)
            ->count();

        $activeKhatmas = Khatma::where('user_id', $userId)
            ->where('status', KhatmaStatus::Active)
            ->count();

        $bestDayGrouped = $statsRecords
            ->groupBy(fn (DailyRecord $record): string => $record->date->toDateString())
            ->map(fn ($records): int => (int) $records->sum('pages_count'))
            ->sortDesc();

        $bestDayDateKey = $bestDayGrouped->keys()->first();
        $bestDayPages = (int) ($bestDayGrouped->first() ?? 0);

        $firstRecord = $statsRecords
            ->sortBy(fn (DailyRecord $record): string => $record->date->toDateString())
            ->first();
        $avgPages = 0;
        if ($firstRecord) {
            $totalDays = Carbon::parse($firstRecord->date)->diffInDays(Carbon::today()) + 1;
            if ($totalDays > 0) {
                $avgPages = round($totalPages / $totalDays, 1);
            }
        }

        return [
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'completed_khatmas' => $completedKhatmas,
            'active_khatmas' => $activeKhatmas,
            'best_day_pages' => $bestDayPages,
            'best_day_date' => $bestDayDateKey ? Carbon::parse($bestDayDateKey)->translatedFormat('j F Y') : '—',
            'avg_pages' => $avgPages,
        ];
    }

    /**
     * تبديل وضع عرض السجل
     */
    public function setRecordsView(string $view): void
    {
        if (!in_array($view, ['7_days', '30_days', '100_records', 'custom_range'], true)) {
            return;
        }

        $this->recordsView = $view;

        if ($view !== 'custom_range') {
            $this->dateFrom = null;
            $this->dateTo = null;
        }
    }

    public function setTypeFilter(string $type): void
    {
        $allowedTypes = array_map(
            static fn (KhatmaType $case): string => $case->value,
            KhatmaType::cases(),
        );

        if ($type !== 'all' && !in_array($type, $allowedTypes, true)) {
            return;
        }

        $this->typeFilter = $type;
    }

    public function applyCustomRange(): void
    {
        $rawFrom = HistoryRecordFilters::normalizeDate($this->dateFrom);
        $rawTo = HistoryRecordFilters::normalizeDate($this->dateTo);

        if (! $rawFrom || ! $rawTo) {
            Notification::make()
                ->title('النطاق غير مكتمل')
                ->body('اختر تاريخ البداية والنهاية قبل تطبيق النطاق المخصص.')
                ->warning()
                ->send();

            return;
        }

        [$from, $to] = HistoryRecordFilters::resolveDateRange($rawFrom, $rawTo);

        if (! $from || ! $to) {
            Notification::make()
                ->title('النطاق غير مكتمل')
                ->body('تعذر قراءة النطاق المخصص. تأكد من صحة التاريخين.')
                ->warning()
                ->send();

            return;
        }

        $this->dateFrom = $from;
        $this->dateTo = $to;
        $this->recordsView = 'custom_range';
    }

    public function clearCustomRange(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->recordsView = $this->resolveDefaultRecordsView();
    }

    public function getExportQueryParams(): array
    {
        return array_filter([
            'records_view' => $this->recordsView,
            'type_filter' => $this->typeFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    public function getTypeFilterOptions(): array
    {
        $options = ['all' => 'كل الأنواع'];

        foreach (KhatmaType::cases() as $case) {
            $options[$case->value] = (string) $case->getLabel();
        }

        return $options;
    }

    /**
     * سجل الإنجاز اليومي
     */
    public function getRecords(): array
    {
        $records = $this->buildRecordsQuery()
            ->with('khatma')
            ->get();
        $surahs = Surah::query()
            ->orderBy('start_page')
            ->get(['start_page', 'end_page', 'name_arabic']);

        $grouped = [];

        foreach ($records as $record) {
            $dateKey = $record->date->format('Y-m-d');
            $dateLabel = $record->date->translatedFormat('l j F Y');

            if ($record->date->isToday()) {
                $dateLabel = 'اليوم — ' . $dateLabel;
            } elseif ($record->date->isYesterday()) {
                $dateLabel = 'أمس — ' . $dateLabel;
            }

            $grouped[$dateKey]['label'] = $dateLabel;
            $grouped[$dateKey]['records'][] = [
                'khatma_name' => $record->khatma?->name ?? '—',
                'khatma_type' => $record->khatma?->type,
                'from_page' => $record->from_page,
                'to_page' => $record->to_page,
                'pages_count' => $record->pages_count,
                'surah_name' => $this->resolveSurahNameFromPage($surahs, (int) $record->from_page),
                'completed_at' => $record->completed_at?->format('H:i') ?? '—',
            ];
        }

        return $grouped;
    }

    public function getWeeklyChartData(): array
    {
        $userId = auth()->id();
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        $query = DailyRecord::where('user_id', $userId)
            ->where('is_completed', true)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate);

        if ($this->typeFilter !== 'all') {
            $query->whereHas('khatma', function ($khatmaQuery): void {
                $khatmaQuery->where('type', $this->typeFilter);
            });
        }

        $totalsByDate = $query
            ->selectRaw('date, SUM(pages_count) as total_pages')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                Carbon::parse($row->date)->toDateString() => (int) $row->total_pages,
            ]);

        $days = [];
        $maxPages = 0;
        $totalPages = 0;

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $startDate->copy()->addDays($offset);
            $dateKey = $date->toDateString();
            $pages = (int) ($totalsByDate[$dateKey] ?? 0);

            $days[] = [
                'date' => $dateKey,
                'label' => $date->format('d/m'),
                'pages' => $pages,
                'is_today' => $date->isToday(),
            ];

            $maxPages = max($maxPages, $pages);
            $totalPages += $pages;
        }

        $maxPages = max($maxPages, 1);

        foreach ($days as &$day) {
            $day['height_percent'] = max((int) round(($day['pages'] / $maxPages) * 100), 8);
        }
        unset($day);

        return [
            'days' => $days,
            'max_pages' => $maxPages,
            'total_pages' => $totalPages,
        ];
    }

    public function getWeeklyComparisonData(): array
    {
        $userId = (int) auth()->id();
        $today = Carbon::today();
        $currentStart = $today->copy()->subDays(6);
        $previousStart = $today->copy()->subDays(13);
        $previousEnd = $today->copy()->subDays(7);

        $baseQuery = DailyRecord::query()
            ->where('user_id', $userId)
            ->where('is_completed', true);

        if ($this->typeFilter !== 'all') {
            $baseQuery->whereHas('khatma', function (Builder $khatmaQuery): void {
                $khatmaQuery->where('type', $this->typeFilter);
            });
        }

        $currentTotal = (int) (clone $baseQuery)
            ->whereDate('date', '>=', $currentStart)
            ->whereDate('date', '<=', $today)
            ->sum('pages_count');

        $previousTotal = (int) (clone $baseQuery)
            ->whereDate('date', '>=', $previousStart)
            ->whereDate('date', '<=', $previousEnd)
            ->sum('pages_count');

        $difference = $currentTotal - $previousTotal;
        $changePercent = $previousTotal > 0
            ? round(($difference / $previousTotal) * 100, 1)
            : ($currentTotal > 0 ? 100.0 : 0.0);

        return [
            'current_total' => $currentTotal,
            'previous_total' => $previousTotal,
            'difference' => $difference,
            'change_percent' => $changePercent,
            'is_positive' => $difference >= 0,
        ];
    }

    public function getAverageByTypeData(): array
    {
        $userId = (int) auth()->id();
        $query = DailyRecord::query()
            ->where('daily_records.user_id', $userId)
            ->where('daily_records.is_completed', true)
            ->join('khatmas', 'khatmas.id', '=', 'daily_records.khatma_id')
            ->selectRaw('khatmas.type as khatma_type, SUM(daily_records.pages_count) as total_pages, COUNT(DISTINCT daily_records.date) as days_count')
            ->groupBy('khatmas.type');

        [$from, $to] = HistoryRecordFilters::resolveDateRange($this->dateFrom, $this->dateTo);

        if ($from && $to) {
            $query->whereDate('daily_records.date', '>=', $from)
                ->whereDate('daily_records.date', '<=', $to);
        } elseif ($this->recordsView === '7_days') {
            $query->whereDate('daily_records.date', '>=', Carbon::today()->subDays(6));
        } elseif ($this->recordsView === '30_days') {
            $query->whereDate('daily_records.date', '>=', Carbon::today()->subDays(29));
        }

        $rows = $query->get()->keyBy('khatma_type');
        $result = [];

        foreach (KhatmaType::cases() as $typeCase) {
            $row = $rows->get($typeCase->value);
            $totalPages = (int) ($row->total_pages ?? 0);
            $daysCount = (int) ($row->days_count ?? 0);

            $result[] = [
                'type' => $typeCase,
                'total_pages' => $totalPages,
                'days_count' => $daysCount,
                'avg_pages' => $daysCount > 0 ? round($totalPages / $daysCount, 1) : 0,
            ];
        }

        return $result;
    }

    private function buildRecordsQuery(): Builder
    {
        $query = DailyRecord::query()
            ->where('user_id', auth()->id())
            ->where('is_completed', true)
            ->orderByDesc('date')
            ->orderByDesc('completed_at');

        return HistoryRecordFilters::apply(
            $query,
            $this->recordsView,
            $this->typeFilter,
            $this->dateFrom,
            $this->dateTo,
        );
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

    private function resolveDefaultRecordsView(): string
    {
        $value = (string) AppSettings::get(
            AppSettings::KEY_HISTORY_DEFAULT_RECORDS_VIEW,
            '30_days',
        );

        return in_array($value, ['7_days', '30_days', '100_records'], true)
            ? $value
            : '30_days';
    }
}
