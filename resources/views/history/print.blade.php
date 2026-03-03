<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير سجل الإنجاز</title>
    <style>
        body {
            font-family: "Tajawal", sans-serif;
            margin: 1.5rem;
            color: #1f2937;
            background: #fff;
        }
        h1, h2 {
            margin: 0 0 0.5rem;
        }
        .meta {
            margin-bottom: 1rem;
            color: #4b5563;
            font-size: 0.9rem;
        }
        .chips {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .chip {
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 0.2rem 0.6rem;
            font-size: 0.78rem;
            color: #374151;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 0.45rem;
            font-size: 0.8rem;
            text-align: center;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .summary {
            display: flex;
            gap: 1rem;
            margin-top: 0.8rem;
            color: #111827;
            font-size: 0.9rem;
            font-weight: 700;
        }
        @media print {
            body {
                margin: 0.8rem;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 1rem;">
        <button onclick="window.print()" style="border: none; border-radius: 8px; background: #111827; color: #fff; padding: 0.45rem 0.8rem; cursor: pointer;">
            طباعة / حفظ PDF
        </button>
    </div>

    <h1>تقرير سجل الإنجاز</h1>
    <div class="meta">تاريخ التوليد: {{ $generatedAt }}</div>

    <div class="chips">
        <span class="chip">الفترة: {{ $filters['records_view'] }}</span>
        <span class="chip">النوع: {{ $filters['type_filter'] }}</span>
        @if($filters['date_from'] && $filters['date_to'])
            <span class="chip">من {{ $filters['date_from'] }} إلى {{ $filters['date_to'] }}</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الختمة</th>
                <th>النوع</th>
                <th>من</th>
                <th>إلى</th>
                <th>الصفحات</th>
                <th>السورة</th>
                <th>وقت الإنجاز</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['khatma_name'] }}</td>
                    <td>{{ $row['khatma_type'] }}</td>
                    <td>{{ $row['from_page'] }}</td>
                    <td>{{ $row['to_page'] }}</td>
                    <td>{{ $row['pages_count'] }}</td>
                    <td>{{ $row['surah_name'] }}</td>
                    <td>{{ $row['completed_at'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">لا توجد سجلات مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <span>إجمالي السجلات: {{ $totalRecords }}</span>
        <span>إجمالي الصفحات: {{ $totalPages }}</span>
    </div>
</body>
</html>
