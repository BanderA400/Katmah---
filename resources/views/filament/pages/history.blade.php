<x-filament-panels::page>

    @php
        $stats = $this->getStatsData();
        $grouped = $this->getRecords();
        $weekly = $this->getWeeklyChartData();
        $weeklyComparison = $this->getWeeklyComparisonData();
        $avgByType = $this->getAverageByTypeData();
        $typeOptions = $this->getTypeFilterOptions();
        $exportParams = $this->getExportQueryParams();
    @endphp

    {{-- === إحصائيات عامة === --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div style="background: var(--khatma-hifz-bg); border-radius: 16px; padding: 1.5rem; text-align: center; border: 1px solid var(--khatma-border);">
            <div style="font-size: 0.85rem; color: var(--khatma-muted); margin-bottom: 0.5rem;">إجمالي الصفحات</div>
            <div style="font-family: 'Amiri', serif; font-size: 2rem; font-weight: 700; color: var(--khatma-hifz-text);">
                {{ $stats['total_pages'] }}
            </div>
        </div>

        <div style="background: var(--khatma-review-bg); border-radius: 16px; padding: 1.5rem; text-align: center; border: 1px solid var(--khatma-border);">
            <div style="font-size: 0.85rem; color: var(--khatma-muted); margin-bottom: 0.5rem;">ختمات مكتملة</div>
            <div style="font-family: 'Amiri', serif; font-size: 2rem; font-weight: 700; color: var(--khatma-review-text);">
                {{ $stats['completed_khatmas'] }}
            </div>
        </div>

        <div style="background: var(--khatma-tilawa-bg); border-radius: 16px; padding: 1.5rem; text-align: center; border: 1px solid var(--khatma-border);">
            <div style="font-size: 0.85rem; color: var(--khatma-muted); margin-bottom: 0.5rem;">أفضل يوم</div>
            <div style="font-family: 'Amiri', serif; font-size: 2rem; font-weight: 700; color: var(--khatma-tilawa-text);">
                {{ $stats['best_day_pages'] }}
            </div>
            <div style="font-size: 0.75rem; color: var(--khatma-muted-soft);">{{ $stats['best_day_date'] }}</div>
        </div>

        <div style="background: var(--khatma-surface-soft); border-radius: 16px; padding: 1.5rem; text-align: center; border: 1px solid var(--khatma-border);">
            <div style="font-size: 0.85rem; color: var(--khatma-muted); margin-bottom: 0.5rem;">متوسط يومي</div>
            <div style="font-family: 'Amiri', serif; font-size: 2rem; font-weight: 700; color: var(--khatma-text);">
                {{ $stats['avg_pages'] }}
            </div>
            <div style="font-size: 0.75rem; color: var(--khatma-muted-soft);">صفحة / يوم</div>
        </div>
    </div>

    {{-- === مقارنة أسبوعية + متوسط حسب النوع === --}}
    <div style="margin-top: 1.1rem;" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div style="background: var(--khatma-surface); border-radius: 14px; padding: 1rem; border: 1px solid var(--khatma-border); box-shadow: var(--khatma-shadow);">
            <div style="font-size: 0.9rem; font-weight: 700; color: var(--khatma-text); margin-bottom: 0.6rem;">
                مقارنة هذا الأسبوع بالأسبوع السابق
            </div>
            <div style="display: flex; gap: 0.7rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                <span style="font-size: 0.8rem; color: var(--khatma-muted);">هذا الأسبوع: <strong style="color: var(--khatma-text);">{{ $weeklyComparison['current_total'] }}</strong></span>
                <span style="font-size: 0.8rem; color: var(--khatma-muted);">السابق: <strong style="color: var(--khatma-text);">{{ $weeklyComparison['previous_total'] }}</strong></span>
            </div>
            <div style="font-size: 0.86rem; font-weight: 700; color: {{ $weeklyComparison['is_positive'] ? 'var(--khatma-success-soft-text)' : '#b91c1c' }};">
                {{ $weeklyComparison['is_positive'] ? '▲' : '▼' }}
                {{ abs($weeklyComparison['difference']) }} صفحة
                ({{ abs($weeklyComparison['change_percent']) }}%)
            </div>
        </div>

        <div style="background: var(--khatma-surface); border-radius: 14px; padding: 1rem; border: 1px solid var(--khatma-border); box-shadow: var(--khatma-shadow);">
            <div style="font-size: 0.9rem; font-weight: 700; color: var(--khatma-text); margin-bottom: 0.6rem;">
                متوسط الصفحات حسب نوع الختمة
            </div>
            <div style="display: grid; gap: 0.4rem;">
                @foreach($avgByType as $row)
                    <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.8rem; color: var(--khatma-muted);">
                        <span style="font-weight: 700; color: var(--khatma-text);">{{ $row['type']->getLabel() }}</span>
                        <span>{{ $row['avg_pages'] }} صفحة / يوم</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- === مخطط أسبوعي === --}}
    <div style="margin-top: 1.5rem; background: var(--khatma-surface); border-radius: 16px; padding: 1.25rem; box-shadow: var(--khatma-shadow); border: 1px solid var(--khatma-border);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.85rem;">
            <div style="font-family: 'Amiri', serif; font-size: 1.2rem; font-weight: 700; color: var(--khatma-title);">
                📈 أداء آخر 7 أيام
            </div>
            <div style="font-size: 0.85rem; color: var(--khatma-muted);">
                إجمالي الأسبوع: <span style="font-weight: 700; color: var(--khatma-text);">{{ $weekly['total_pages'] }}</span> صفحة
            </div>
        </div>

        <div style="height: 190px; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.6rem; align-items: end;">
            @foreach($weekly['days'] as $day)
                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.45rem;">
                    <div style="font-size: 0.75rem; color: {{ $day['is_today'] ? 'var(--khatma-title)' : 'var(--khatma-muted)' }}; font-weight: {{ $day['is_today'] ? '700' : '600' }};">
                        {{ $day['pages'] }} ص
                    </div>
                    <div style="height: 130px; width: 100%; max-width: 44px; border-radius: 10px; background: var(--khatma-surface-soft); border: 1px solid var(--khatma-border); display: flex; align-items: flex-end; overflow: hidden;">
                        <div style="width: 100%; height: {{ $day['height_percent'] }}%; background: linear-gradient(180deg, var(--khatma-hifz-from), var(--khatma-review-from)); border-radius: 10px 10px 0 0;"></div>
                    </div>
                    <div style="font-size: 0.74rem; color: var(--khatma-muted-soft); font-weight: 600;">
                        {{ $day['label'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- === السجل اليومي === --}}
    <div style="margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.7rem; margin-bottom: 1rem;">
            <h2 style="font-family: 'Amiri', serif; font-size: 1.5rem; font-weight: 700; color: var(--khatma-title); margin: 0;">
                📋 السجل اليومي
            </h2>
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <button
                        wire:click="setRecordsView('7_days')"
                        style="padding: 0.4rem 0.8rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.8rem; font-weight: 700; {{ $this->recordsView === '7_days' ? 'background: var(--khatma-title); color: #fff;' : 'background: var(--khatma-surface-soft); color: var(--khatma-muted);' }}"
                    >
                        آخر 7 أيام
                    </button>
                    <button
                        wire:click="setRecordsView('30_days')"
                        style="padding: 0.4rem 0.8rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.8rem; font-weight: 700; {{ $this->recordsView === '30_days' ? 'background: var(--khatma-title); color: #fff;' : 'background: var(--khatma-surface-soft); color: var(--khatma-muted);' }}"
                    >
                        آخر 30 يوم
                    </button>
                    <button
                        wire:click="setRecordsView('100_records')"
                        style="padding: 0.4rem 0.8rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.8rem; font-weight: 700; {{ $this->recordsView === '100_records' ? 'background: var(--khatma-title); color: #fff;' : 'background: var(--khatma-surface-soft); color: var(--khatma-muted);' }}"
                    >
                        آخر 100 سجل
                    </button>
                </div>

                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    @foreach($typeOptions as $typeValue => $typeLabel)
                        <button
                            wire:click="setTypeFilter('{{ $typeValue }}')"
                            style="padding: 0.34rem 0.72rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.75rem; font-weight: 700; {{ $this->typeFilter === $typeValue ? 'background: var(--khatma-text); color: #fff;' : 'background: var(--khatma-surface-soft); color: var(--khatma-muted);' }}"
                        >
                            {{ $typeLabel }}
                        </button>
                    @endforeach
                </div>

                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: end;">
                    <label style="display: grid; gap: 0.2rem;">
                        <span style="font-size: 0.7rem; color: var(--khatma-muted);">من</span>
                        <input type="date" wire:model.defer="dateFrom" style="border: 1px solid var(--khatma-border); border-radius: 8px; padding: 0.35rem 0.45rem; font-size: 0.75rem; background: var(--khatma-surface); color: var(--khatma-text);">
                    </label>
                    <label style="display: grid; gap: 0.2rem;">
                        <span style="font-size: 0.7rem; color: var(--khatma-muted);">إلى</span>
                        <input type="date" wire:model.defer="dateTo" style="border: 1px solid var(--khatma-border); border-radius: 8px; padding: 0.35rem 0.45rem; font-size: 0.75rem; background: var(--khatma-surface); color: var(--khatma-text);">
                    </label>
                    <button
                        wire:click="applyCustomRange"
                        style="padding: 0.38rem 0.68rem; border-radius: 8px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.75rem; font-weight: 700; background: var(--khatma-title); color: #fff;"
                    >
                        تطبيق النطاق
                    </button>
                    <button
                        wire:click="clearCustomRange"
                        style="padding: 0.38rem 0.68rem; border-radius: 8px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.75rem; font-weight: 700; background: var(--khatma-surface-soft); color: var(--khatma-text);"
                    >
                        إلغاء النطاق
                    </button>
                </div>

                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a
                        href="{{ route('history.export.csv', $exportParams) }}"
                        style="padding: 0.34rem 0.7rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.75rem; font-weight: 700; background: var(--khatma-surface-soft); color: var(--khatma-text); text-decoration: none;"
                    >
                        تصدير CSV
                    </a>
                    <a
                        href="{{ route('history.export.print', $exportParams) }}"
                        target="_blank"
                        rel="noopener"
                        style="padding: 0.34rem 0.7rem; border-radius: 999px; border: 1px solid var(--khatma-border); cursor: pointer; font-size: 0.75rem; font-weight: 700; background: var(--khatma-surface-soft); color: var(--khatma-text); text-decoration: none;"
                    >
                        PDF (نسخة طباعة)
                    </a>
                </div>
            </div>
        </div>

        @if(count($grouped) === 0)
            <div style="background: var(--khatma-surface); border-radius: 16px; padding: 3rem; text-align: center; box-shadow: var(--khatma-shadow); border: 1px solid var(--khatma-border);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                <div style="font-size: 1.1rem; color: var(--khatma-text); font-weight: 600; margin-bottom: 0.5rem;">لا يوجد سجل بعد</div>
                <div style="color: var(--khatma-muted);">ابدأ بتسجيل ورد اليوم من لوحة التحكم</div>
            </div>
        @else
            @foreach($grouped as $dateKey => $day)
                <div style="margin-bottom: 1.5rem;">
                    {{-- تاريخ اليوم --}}
                    <div style="font-weight: 700; color: var(--khatma-text); font-size: 1rem; margin-bottom: 0.8rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--khatma-border);">
                        {{ $day['label'] }}
                    </div>

                    {{-- سجلات اليوم --}}
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($day['records'] as $record)
                            @php
                                $typeColors = match($record['khatma_type']) {
                                    \App\Enums\KhatmaType::Hifz => [
                                        'badge_bg' => 'var(--khatma-hifz-bg)', 'badge_text' => 'var(--khatma-hifz-text)',
                                        'border' => 'var(--khatma-hifz-from)', 'label' => '📖 حفظ',
                                    ],
                                    \App\Enums\KhatmaType::Review => [
                                        'badge_bg' => 'var(--khatma-review-bg)', 'badge_text' => 'var(--khatma-review-text)',
                                        'border' => 'var(--khatma-review-from)', 'label' => '🔄 مراجعة',
                                    ],
                                    \App\Enums\KhatmaType::Tilawa => [
                                        'badge_bg' => 'var(--khatma-tilawa-bg)', 'badge_text' => 'var(--khatma-tilawa-text)',
                                        'border' => 'var(--khatma-tilawa-from)', 'label' => '📿 تلاوة',
                                    ],
                                    default => [
                                        'badge_bg' => 'var(--khatma-surface-soft)', 'badge_text' => 'var(--khatma-muted)',
                                        'border' => 'var(--khatma-border)', 'label' => '—',
                                    ],
                                };
                            @endphp

                            <div style="background: var(--khatma-surface); border-radius: 12px; padding: 1rem 1.2rem; box-shadow: var(--khatma-shadow); border-right: 4px solid {{ $typeColors['border'] }}; border-top: 1px solid var(--khatma-border); border-bottom: 1px solid var(--khatma-border); border-left: 1px solid var(--khatma-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                                    {{-- بادج النوع --}}
                                    <span style="background: {{ $typeColors['badge_bg'] }}; color: {{ $typeColors['badge_text'] }}; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.75rem; font-weight: 600;">
                                        {{ $typeColors['label'] }}
                                    </span>

                                    {{-- اسم الختمة --}}
                                    <span style="font-weight: 600; color: var(--khatma-text);">{{ $record['khatma_name'] }}</span>

                                    {{-- السورة --}}
                                    <span style="color: var(--khatma-muted); font-size: 0.9rem;">{{ $record['surah_name'] }}</span>
                                </div>

                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    {{-- الصفحات --}}
                                    <span style="font-family: 'Amiri', serif; font-weight: 700; color: {{ $typeColors['badge_text'] }};">
                                        ص{{ $record['from_page'] }}–{{ $record['to_page'] }}
                                    </span>

                                    {{-- عدد الصفحات --}}
                                    <span style="background: var(--khatma-surface-soft); color: var(--khatma-muted); padding: 0.2rem 0.6rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--khatma-border);">
                                        {{ $record['pages_count'] }} صفحة
                                    </span>

                                    {{-- الوقت --}}
                                    <span style="color: var(--khatma-muted-soft); font-size: 0.8rem;">
                                        {{ $record['completed_at'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

</x-filament-panels::page>
