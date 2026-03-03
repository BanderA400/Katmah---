<?php

namespace App\Filament\Resources;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Filament\Resources\KhatmaResource\Pages;
use App\Models\Khatma;
use App\Models\Surah;
use App\Support\AppSettings;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KhatmaResource extends Resource
{
    protected static ?string $model = Khatma::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'ختماتي';

    protected static ?string $modelLabel = 'ختمة';

    protected static ?string $pluralModelLabel = 'ختماتي';

    protected static string|\UnitEnum|null $navigationGroup = 'القرآن الكريم';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('الخطوة 1: معلومات أساسية')
                        ->icon('heroicon-o-book-open')
                        ->description('اسم الختمة، النوع، وموعد البداية')
                        ->schema([
                            Section::make('معلومات الختمة')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('اسم الختمة')
                                        ->placeholder('مثال: ختمة رمضان')
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\Select::make('type')
                                        ->label('نوع الختمة')
                                        ->options(KhatmaType::class)
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('status')
                                        ->label('الحالة')
                                        ->options(KhatmaStatus::class)
                                        ->default(KhatmaStatus::Active->value)
                                        ->required()
                                        ->native(false)
                                        ->visibleOn('edit'),

                                    Forms\Components\DatePicker::make('start_date')
                                        ->label('تاريخ البداية')
                                        ->default(now())
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set): void {
                                            static::applyTemplatePreset($get, $set);
                                            static::recalculate($get, $set);
                                        }),

                                    Forms\Components\Select::make('template_days')
                                        ->label('قالب سريع')
                                        ->options([
                                            'manual' => 'يدوي',
                                            '30' => 'ختمة 30 يوم',
                                            '60' => 'ختمة 60 يوم',
                                            '90' => 'ختمة 90 يوم',
                                        ])
                                        ->default('manual')
                                        ->dehydrated(false)
                                        ->native(false)
                                        ->live()
                                        ->helperText('اختيار قالب يضبط طريقة التخطيط إلى "بالمدة" تلقائيًا.')
                                        ->visibleOn('create')
                                        ->afterStateUpdated(function ($get, $set): void {
                                            static::applyTemplatePreset($get, $set);
                                            static::recalculate($get, $set);
                                        }),
                                ])
                                ->columns(2),
                        ]),

                    Step::make('الخطوة 2: النطاق')
                        ->icon('heroicon-o-document-text')
                        ->description('حدد الصفحات أو السور المطلوبة')
                        ->schema([
                            Section::make('نطاق الختمة')
                                ->schema([
                                    Forms\Components\Select::make('scope')
                                        ->label('النطاق')
                                        ->options(KhatmaScope::class)
                                        ->default(KhatmaScope::Full->value)
                                        ->required()
                                        ->disabled(fn (?Khatma $record): bool => (int) ($record?->completed_pages ?? 0) > 0)
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set, $state) {
                                            if (static::stateValue($state) === KhatmaScope::Full->value) {
                                                $set('start_page', 1);
                                                $set('end_page', 604);
                                                $set('total_pages', 604);
                                                static::recalculate($get, $set);
                                            }
                                        }),

                                    Forms\Components\Select::make('direction')
                                        ->label('الاتجاه')
                                        ->options(KhatmaDirection::class)
                                        ->default(KhatmaDirection::Forward->value)
                                        ->required()
                                        ->disabled(fn (?Khatma $record): bool => (int) ($record?->completed_pages ?? 0) > 0)
                                        ->native(false),

                                    Forms\Components\Select::make('start_surah')
                                        ->label('من سورة')
                                        ->options(fn () => Surah::orderBy('number')->pluck('name_arabic', 'number')->toArray())
                                        ->searchable()
                                        ->disabled(fn (?Khatma $record): bool => (int) ($record?->completed_pages ?? 0) > 0)
                                        ->native(false)
                                        ->visible(fn ($get) => static::stateValue($get('scope')) === KhatmaScope::Custom->value)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set, $state) {
                                            if ($state) {
                                                $surah = Surah::where('number', $state)->first();
                                                if ($surah) {
                                                    $set('start_page', $surah->start_page);
                                                    static::calculateTotalPages($get, $set);
                                                }
                                            }
                                        }),

                                    Forms\Components\Select::make('end_surah')
                                        ->label('إلى سورة')
                                        ->options(fn () => Surah::orderBy('number')->pluck('name_arabic', 'number')->toArray())
                                        ->searchable()
                                        ->disabled(fn (?Khatma $record): bool => (int) ($record?->completed_pages ?? 0) > 0)
                                        ->native(false)
                                        ->visible(fn ($get) => static::stateValue($get('scope')) === KhatmaScope::Custom->value)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set, $state) {
                                            if ($state) {
                                                $surah = Surah::where('number', $state)->first();
                                                if ($surah) {
                                                    $set('end_page', $surah->end_page);
                                                    static::calculateTotalPages($get, $set);
                                                }
                                            }
                                        }),

                                    Forms\Components\TextInput::make('start_page')
                                        ->label('صفحة البداية')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->maxValue(604)
                                        ->required()
                                        ->lte('end_page')
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(fn ($get, $set) => static::calculateTotalPages($get, $set))
                                        ->disabled(fn ($get, ?Khatma $record): bool =>
                                            static::stateValue($get('scope')) === KhatmaScope::Full->value
                                            || (int) ($record?->completed_pages ?? 0) > 0
                                        ),

                                    Forms\Components\TextInput::make('end_page')
                                        ->label('صفحة النهاية')
                                        ->numeric()
                                        ->default(604)
                                        ->minValue(1)
                                        ->maxValue(604)
                                        ->required()
                                        ->gte('start_page')
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(fn ($get, $set) => static::calculateTotalPages($get, $set))
                                        ->disabled(fn ($get, ?Khatma $record): bool =>
                                            static::stateValue($get('scope')) === KhatmaScope::Full->value
                                            || (int) ($record?->completed_pages ?? 0) > 0
                                        ),

                                    Forms\Components\TextInput::make('total_pages')
                                        ->label('إجمالي الصفحات')
                                        ->numeric()
                                        ->default(604)
                                        ->required()
                                        ->minValue(1)
                                        ->disabled()
                                        ->dehydrated(),

                                    Forms\Components\Placeholder::make('progress_lock_notice')
                                        ->hiddenLabel()
                                        ->content('بعد بدء التقدم، يتم قفل الاتجاه والنطاق والبداية/النهاية لحماية سلامة التقدم.')
                                        ->visible(fn (?Khatma $record): bool => (int) ($record?->completed_pages ?? 0) > 0),
                                ])
                                ->columns(2),
                        ]),

                    Step::make('الخطوة 3: التخطيط')
                        ->icon('heroicon-o-calendar-days')
                        ->description('طريقة توزيع الورد اليومي')
                        ->schema([
                            Section::make('التخطيط')
                                ->schema([
                                    Forms\Components\Select::make('planning_method')
                                        ->label('طريقة التخطيط')
                                        ->options(PlanningMethod::class)
                                        ->default(PlanningMethod::ByWird->value)
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set, $state) {
                                            if (static::stateValue($state) === PlanningMethod::ByDuration->value) {
                                                $set('daily_pages', null);
                                            } else {
                                                $set('expected_end_date', null);
                                            }
                                        }),

                                    Forms\Components\Toggle::make('auto_compensate_missed_days')
                                        ->label('تعويض تلقائي عند فوات الأيام')
                                        ->helperText('عند التفعيل: يعاد توزيع المتبقي تلقائيًا على الأيام القادمة.')
                                        ->default(fn (): bool => static::defaultCompensationForUser())
                                        ->inline(false),

                                    Forms\Components\DatePicker::make('expected_end_date')
                                        ->label('تاريخ الختم المتوقع')
                                        ->native(false)
                                        ->minDate(fn ($get) => $get('start_date'))
                                        ->required(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByDuration->value)
                                        ->visible(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByDuration->value)
                                        ->live()
                                        ->afterStateUpdated(function ($get, $set) {
                                            if (static::stateValue($get('planning_method')) !== PlanningMethod::ByDuration->value) {
                                                return;
                                            }

                                            $startDate = $get('start_date');
                                            $endDate = $get('expected_end_date');
                                            $totalPages = (int) $get('total_pages');

                                            if ($startDate && $endDate && $totalPages > 0) {
                                                $days = static::calculateInclusiveDays($startDate, $endDate);

                                                if ($days !== null) {
                                                    $set('daily_pages', (int) ceil($totalPages / $days));
                                                }
                                            }
                                        }),

                                    Forms\Components\TextInput::make('daily_pages')
                                        ->label('الورد اليومي (عدد الصفحات)')
                                        ->numeric()
                                        ->default(fn (): int => static::defaultDailyPagesForUser())
                                        ->minValue(1)
                                        ->maxValue(604)
                                        ->required(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByWird->value)
                                        ->visible(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByWird->value)
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(function ($get, $set) {
                                            if (static::stateValue($get('planning_method')) !== PlanningMethod::ByWird->value) {
                                                return;
                                            }

                                            $startDate = $get('start_date');
                                            $dailyPages = (int) $get('daily_pages');
                                            $totalPages = (int) $get('total_pages');

                                            if ($startDate && $dailyPages > 0 && $totalPages > 0) {
                                                $days = (int) ceil($totalPages / $dailyPages);
                                                $set('expected_end_date', Carbon::parse($startDate)->addDays(max($days - 1, 0))->format('Y-m-d'));
                                            }
                                        }),

                                    Forms\Components\Placeholder::make('calculated_daily')
                                        ->label('📖 الورد اليومي المحسوب')
                                        ->content(function ($get) {
                                            $startDate = $get('start_date');
                                            $endDate = $get('expected_end_date');
                                            $totalPages = (int) $get('total_pages');

                                            $summary = static::buildDurationDailySummary(
                                                is_string($startDate) ? $startDate : null,
                                                is_string($endDate) ? $endDate : null,
                                                $totalPages,
                                            );

                                            return $summary ?? '—';
                                        })
                                        ->visible(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByDuration->value),

                                    Forms\Components\Placeholder::make('calculated_end')
                                        ->label('📅 تاريخ الختم المحسوب')
                                        ->content(function ($get) {
                                            $date = $get('expected_end_date');

                                            return $date ? Carbon::parse($date)->translatedFormat('j F Y') : '—';
                                        })
                                        ->visible(fn ($get) => static::stateValue($get('planning_method')) === PlanningMethod::ByWird->value),
                                ])
                                ->columns(2),
                        ]),
                ])
                    ->columnSpanFull(),

                // حقول مخفية
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),

                Forms\Components\Hidden::make('current_page')
                    ->default(fn ($get) => static::resolveCurrentPageFromDirection(
                        $get('direction'),
                        (int) ($get('start_page') ?? 1),
                        (int) ($get('end_page') ?? 604),
                    )),

                Forms\Components\Hidden::make('completed_pages')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الختمة')
                    ->searchable()
                    ->weight('bold')
                    ->icon(fn (Khatma $record): string => $record->type->getIcon())
                    ->iconColor(fn (Khatma $record): string => $record->type->getColor()),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (Khatma $record): string => $record->type->getColor()),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),

                Tables\Columns\TextColumn::make('daily_pages')
                    ->label('الورد اليومي')
                    ->formatStateUsing(function (Khatma $record): string {
                        if ($record->planning_method === PlanningMethod::ByDuration) {
                            $summary = static::buildDurationDailySummary(
                                $record->start_date?->toDateString(),
                                $record->expected_end_date?->toDateString(),
                                (int) $record->total_pages,
                            );

                            return $summary ?? 'متغير';
                        }

                        return "{$record->daily_pages} صفحة";
                    })
                    ->alignCenter(),

                Tables\Columns\ViewColumn::make('progress')
                    ->label('التقدم')
                    ->view('filament.tables.columns.khatma-progress')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('completed_pages')
                    ->label('المنجز')
                    ->formatStateUsing(fn (Khatma $record): string => "{$record->completed_pages} / {$record->total_pages}")
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('expected_end_date')
                    ->label('تاريخ الختم')
                    ->date('j M Y')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البداية')
                    ->date('j M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(KhatmaType::class),

                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(KhatmaStatus::class),
            ])
            ->actions([
                \Filament\Actions\Action::make('rebalance_plan')
                    ->label('إعادة موازنة')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('primary')
                    ->visible(fn (Khatma $record): bool => $record->status === KhatmaStatus::Active)
                    ->action(function (Khatma $record): void {
                        $today = Carbon::today();
                        $remainingPages = max((int) $record->total_pages - (int) $record->completed_pages, 0);

                        if ($remainingPages <= 0) {
                            Notification::make()
                                ->title('لا يوجد متبقٍ')
                                ->body('هذه الختمة مكتملة بالفعل.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($record->planning_method === PlanningMethod::ByDuration) {
                            $endDate = $record->expected_end_date?->copy()->startOfDay() ?? $today->copy();

                            if ($endDate->lt($today)) {
                                $endDate = $today->copy();
                            }

                            $daysLeft = $today->diffInDays($endDate) + 1;
                            $newDailyPages = max((int) ceil($remainingPages / max($daysLeft, 1)), 1);

                            $record->update([
                                'daily_pages' => $newDailyPages,
                                'expected_end_date' => $endDate,
                            ]);

                            Notification::make()
                                ->title('تمت إعادة الموازنة')
                                ->body("المتبقي {$remainingPages} صفحة على {$daysLeft} يوم.")
                                ->success()
                                ->send();

                            return;
                        }

                        $dailyPages = max((int) $record->daily_pages, 1);
                        $daysNeeded = (int) ceil($remainingPages / $dailyPages);
                        $newEndDate = $today->copy()->addDays(max($daysNeeded - 1, 0));

                        $record->update([
                            'expected_end_date' => $newEndDate,
                        ]);

                        Notification::make()
                            ->title('تمت إعادة الموازنة')
                            ->body('تم تحديث تاريخ الختم المتوقع بناءً على الورد الحالي.')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('toggle_status')
                    ->label(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'إيقاف' : 'استئناف')
                    ->icon(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'gray' : 'success')
                    ->visible(fn (Khatma $record): bool => in_array($record->status, [KhatmaStatus::Active, KhatmaStatus::Paused], true))
                    ->action(function (Khatma $record): void {
                        $newStatus = $record->status === KhatmaStatus::Active
                            ? KhatmaStatus::Paused
                            : KhatmaStatus::Active;

                        $record->update(['status' => $newStatus]);

                        Notification::make()
                            ->title($newStatus === KhatmaStatus::Paused ? 'تم الإيقاف' : 'تم الاستئناف')
                            ->body("تم تحديث حالة ختمة \"{$record->name}\"")
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\EditAction::make()
                    ->label('تعديل'),
                \Filament\Actions\DeleteAction::make()
                    ->label('حذف'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد ختمات')
            ->emptyStateDescription('ابدأ رحلتك مع القرآن بإنشاء ختمة جديدة')
            ->emptyStateIcon('heroicon-o-book-open');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKhatmas::route('/'),
            'create' => Pages\CreateKhatma::route('/create'),
            'edit' => Pages\EditKhatma::route('/{record}/edit'),
        ];
    }

    /**
     * تصفية الختمات حسب المستخدم الحالي
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    // ==================
    // دوال الحساب
    // ==================

    protected static function calculateTotalPages($get, $set): void
    {
        $startPage = (int) $get('start_page');
        $endPage = (int) $get('end_page');

        if ($startPage <= 0 || $endPage <= 0) {
            $set('total_pages', null);

            return;
        }

        if ($endPage < $startPage) {
            $set('total_pages', null);

            return;
        }

        $set('total_pages', $endPage - $startPage + 1);
        static::recalculate($get, $set);
    }

    protected static function recalculate($get, $set): void
    {
        $method = static::stateValue($get('planning_method'));
        $totalPages = (int) $get('total_pages');
        $startDate = $get('start_date');

        if (! $method || $totalPages <= 0 || blank($startDate)) {
            return;
        }

        if ($method === PlanningMethod::ByDuration->value) {
            $endDate = $get('expected_end_date');

            if (blank($endDate)) {
                return;
            }

            $days = static::calculateInclusiveDays($startDate, $endDate);

            if ($days !== null) {
                $set('daily_pages', (int) ceil($totalPages / $days));
            }
        } elseif ($method === PlanningMethod::ByWird->value) {
            $dailyPages = (int) $get('daily_pages');
            if ($dailyPages > 0) {
                $days = (int) ceil($totalPages / $dailyPages);
                $set('expected_end_date', Carbon::parse($startDate)->addDays(max($days - 1, 0))->format('Y-m-d'));
            }
        }
    }

    protected static function calculateInclusiveDays(string $startDate, string $endDate): ?int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return null;
        }

        return $start->diffInDays($end) + 1;
    }

    protected static function buildDurationDailySummary(?string $startDate, ?string $endDate, int $totalPages): ?string
    {
        if (blank($startDate) || blank($endDate) || $totalPages <= 0) {
            return null;
        }

        $days = static::calculateInclusiveDays($startDate, $endDate);

        if ($days === null || $days <= 0) {
            return null;
        }

        $average = $totalPages / $days;
        $minPages = (int) floor($average);
        $maxPages = (int) ceil($average);

        if ($minPages === $maxPages) {
            return "{$maxPages} صفحة يوميًا";
        }

        $averageLabel = rtrim(rtrim(number_format($average, 1), '0'), '.');

        return "متغير {$minPages}–{$maxPages} صفحة (متوسط {$averageLabel})";
    }

    protected static function applyTemplatePreset($get, $set): void
    {
        $templateDaysState = $get('template_days');

        if (! is_string($templateDaysState) || ! ctype_digit($templateDaysState)) {
            return;
        }

        $templateDays = (int) $templateDaysState;

        if ($templateDays <= 0) {
            return;
        }

        $startDate = $get('start_date');

        if (blank($startDate)) {
            return;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $set('planning_method', PlanningMethod::ByDuration->value);
        $set('expected_end_date', $start->copy()->addDays(max($templateDays - 1, 0))->toDateString());
    }

    protected static function defaultCompensationForUser(): bool
    {
        $userDefault = auth()->user()?->default_auto_compensate_missed_days;

        if ($userDefault !== null) {
            return (bool) $userDefault;
        }

        return (bool) AppSettings::get(
            AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE,
            false,
        );
    }

    protected static function defaultDailyPagesForUser(): int
    {
        $userDefault = auth()->user()?->default_daily_pages;

        $value = $userDefault !== null
            ? (int) $userDefault
            : (int) AppSettings::get(AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES, 5);

        return max(min($value, 604), 1);
    }

    protected static function resolveCurrentPageFromDirection(mixed $direction, int $startPage, int $endPage): int
    {
        return static::stateValue($direction) === KhatmaDirection::Backward->value ? $endPage : $startPage;
    }

    protected static function stateValue(mixed $state): mixed
    {
        return $state instanceof \BackedEnum ? $state->value : $state;
    }
}
