<?php

namespace App\Support;

use App\Enums\KhatmaType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class HistoryRecordFilters
{
    public static function normalizeRecordsView(?string $view): string
    {
        return in_array($view, ['7_days', '30_days', '100_records', 'custom_range'], true)
            ? (string) $view
            : '30_days';
    }

    public static function normalizeTypeFilter(?string $type): string
    {
        $allowedTypes = array_map(
            static fn (KhatmaType $case): string => $case->value,
            KhatmaType::cases(),
        );

        if ($type !== null && in_array($type, $allowedTypes, true)) {
            return $type;
        }

        return 'all';
    }

    public static function normalizeDate(?string $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = static::normalizeDate($dateFrom);
        $to = static::normalizeDate($dateTo);

        if (! $from || ! $to) {
            return [null, null];
        }

        if ($from && $to && Carbon::parse($from)->gt(Carbon::parse($to))) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    public static function apply(
        Builder $query,
        string $recordsView,
        string $typeFilter,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): Builder {
        $recordsView = static::normalizeRecordsView($recordsView);
        $typeFilter = static::normalizeTypeFilter($typeFilter);
        [$from, $to] = static::resolveDateRange($dateFrom, $dateTo);

        if ($typeFilter !== 'all') {
            $query->whereHas('khatma', function (Builder $khatmaQuery) use ($typeFilter): void {
                $khatmaQuery->where('type', $typeFilter);
            });
        }

        if ($recordsView === 'custom_range' && ! ($from && $to)) {
            $query->whereDate('date', '>=', Carbon::today()->subDays(29));

            return $query;
        }

        if ($from && $to) {
            $query->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to);

            return $query;
        }

        if ($recordsView === '7_days') {
            $query->whereDate('date', '>=', Carbon::today()->subDays(6));
        } elseif ($recordsView === '30_days') {
            $query->whereDate('date', '>=', Carbon::today()->subDays(29));
        } elseif ($recordsView === '100_records') {
            $query->limit(100);
        }

        return $query;
    }
}
