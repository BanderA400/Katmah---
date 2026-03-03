<?php

namespace App\Http\Controllers;

use App\Enums\KhatmaType;
use App\Models\DailyRecord;
use App\Models\Surah;
use App\Support\HistoryRecordFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $records = $this->buildFilteredQuery($request)
            ->with('khatma')
            ->get();

        $surahs = Surah::query()
            ->orderBy('start_page')
            ->get(['start_page', 'end_page', 'name_arabic']);

        $filename = 'khatma-history-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($records, $surahs): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'التاريخ',
                'الختمة',
                'النوع',
                'من صفحة',
                'إلى صفحة',
                'عدد الصفحات',
                'السورة',
                'وقت الإنجاز',
            ]);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->date?->toDateString() ?? '',
                    $record->khatma?->name ?? '—',
                    $record->khatma?->type instanceof KhatmaType ? $record->khatma->type->getLabel() : '—',
                    $record->from_page,
                    $record->to_page,
                    $record->pages_count,
                    $this->resolveSurahNameFromPage($surahs, (int) $record->from_page),
                    $record->completed_at?->format('H:i') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request)
    {
        $records = $this->buildFilteredQuery($request)
            ->with('khatma')
            ->get();

        $surahs = Surah::query()
            ->orderBy('start_page')
            ->get(['start_page', 'end_page', 'name_arabic']);

        $rows = $records->map(function (DailyRecord $record) use ($surahs): array {
            return [
                'date' => $record->date?->translatedFormat('j F Y') ?? '—',
                'khatma_name' => $record->khatma?->name ?? '—',
                'khatma_type' => $record->khatma?->type instanceof KhatmaType ? $record->khatma->type->getLabel() : '—',
                'from_page' => $record->from_page,
                'to_page' => $record->to_page,
                'pages_count' => $record->pages_count,
                'surah_name' => $this->resolveSurahNameFromPage($surahs, (int) $record->from_page),
                'completed_at' => $record->completed_at?->format('H:i') ?? '—',
            ];
        });

        $filters = [
            'records_view' => HistoryRecordFilters::normalizeRecordsView($request->string('records_view')->toString()),
            'type_filter' => HistoryRecordFilters::normalizeTypeFilter($request->string('type_filter')->toString()),
            'date_from' => HistoryRecordFilters::normalizeDate($request->string('date_from')->toString()),
            'date_to' => HistoryRecordFilters::normalizeDate($request->string('date_to')->toString()),
        ];

        return view('history.print', [
            'rows' => $rows,
            'totalPages' => (int) $rows->sum('pages_count'),
            'totalRecords' => (int) $rows->count(),
            'filters' => $filters,
            'generatedAt' => Carbon::now()->translatedFormat('j F Y - H:i'),
        ]);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $recordsView = HistoryRecordFilters::normalizeRecordsView($request->string('records_view')->toString());
        $typeFilter = HistoryRecordFilters::normalizeTypeFilter($request->string('type_filter')->toString());
        $dateFrom = HistoryRecordFilters::normalizeDate($request->string('date_from')->toString());
        $dateTo = HistoryRecordFilters::normalizeDate($request->string('date_to')->toString());

        $query = DailyRecord::query()
            ->where('user_id', $request->user()->id)
            ->where('is_completed', true)
            ->orderByDesc('date')
            ->orderByDesc('completed_at');

        return HistoryRecordFilters::apply($query, $recordsView, $typeFilter, $dateFrom, $dateTo);
    }

    private function resolveSurahNameFromPage($surahs, int $page): string
    {
        foreach ($surahs as $surah) {
            if ($page >= (int) $surah->start_page && $page <= (int) $surah->end_page) {
                return $surah->name_arabic;
            }
        }

        return '—';
    }
}
