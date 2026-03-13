<?php

namespace App\Filament\Control\Resources;

use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Filament\Control\Resources\KhatmaResource\Pages;
use App\Models\Khatma;
use App\Support\SmartKhatmaPlanner;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KhatmaResource extends Resource
{
    protected static ?string $model = Khatma::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'الختمات';

    protected static ?string $modelLabel = 'ختمة';

    protected static ?string $pluralModelLabel = 'الختمات';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المنصة';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الختمة')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('المستخدم')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('اسم الختمة')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options(KhatmaType::class)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options(KhatmaStatus::class)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('التخطيط')
                    ->schema([
                        Forms\Components\Select::make('planning_method')
                            ->label('طريقة التخطيط')
                            ->options(PlanningMethod::class)
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('auto_compensate_missed_days')
                            ->label('تعويض تلقائي عند فوات الأيام')
                            ->inline(false)
                            ->default(false),

                        Forms\Components\TextInput::make('daily_pages')
                            ->label('الورد اليومي')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(604)
                            ->required(),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('تاريخ البداية')
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('expected_end_date')
                            ->label('تاريخ الختم المتوقع')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الختمة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (Khatma $record): string => $record->type->getIcon())
                    ->iconColor(fn (Khatma $record): string => $record->type->getColor()),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),

                Tables\Columns\TextColumn::make('daily_pages')
                    ->label('الورد اليومي')
                    ->alignCenter()
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
                    }),

                Tables\Columns\ViewColumn::make('progress')
                    ->label('التقدم')
                    ->view('filament.tables.columns.khatma-progress')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('expected_end_date')
                    ->label('الختم المتوقع')
                    ->date('j M Y')
                    ->color(fn (Khatma $record): string =>
                        $record->status === KhatmaStatus::Active
                        && $record->expected_end_date
                        && $record->expected_end_date->isPast()
                            ? 'danger'
                            : 'gray'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(KhatmaType::class),

                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(KhatmaStatus::class),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('overdue')
                    ->label('متأخرة')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', KhatmaStatus::Active)
                        ->whereDate('expected_end_date', '<', Carbon::today())
                    ),
            ])
            ->actions([
                Action::make('toggle_status')
                    ->label(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'إيقاف' : 'استئناف')
                    ->icon(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Khatma $record): string => $record->status === KhatmaStatus::Active ? 'gray' : 'success')
                    ->visible(fn (Khatma $record): bool => in_array($record->status, [KhatmaStatus::Active, KhatmaStatus::Paused], true))
                    ->action(function (Khatma $record): void {
                        $newStatus = $record->status === KhatmaStatus::Active
                            ? KhatmaStatus::Paused
                            : KhatmaStatus::Active;

                        $record->update([
                            'status' => $newStatus,
                        ]);

                        Notification::make()
                            ->title($newStatus === KhatmaStatus::Paused ? 'تم الإيقاف' : 'تم الاستئناف')
                            ->success()
                            ->send();
                    }),

                Action::make('rebalance_plan')
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
                            $resolution = SmartKhatmaPlanner::resolveAutoExtension(
                                $remainingPages,
                                $daysLeft,
                                (int) ($record->smart_extension_days_used ?? 0),
                            );

                            $appliedExtension = (int) $resolution['applied_extension_days'];
                            if ($appliedExtension > 0) {
                                $endDate = $endDate->copy()->addDays($appliedExtension);
                                $daysLeft += $appliedExtension;
                            }

                            $newDailyPages = SmartKhatmaPlanner::calculateRoundedDailyTarget($remainingPages, $daysLeft);

                            $record->update([
                                'daily_pages' => $newDailyPages,
                                'expected_end_date' => $endDate,
                                'smart_extension_days_used' => (int) $resolution['extension_days_used_after'],
                            ]);

                            if ((bool) $resolution['needs_higher_daily_pages']) {
                                Notification::make()
                                    ->title('يلزم رفع الورد')
                                    ->body("تم استهلاك التمديد المتاح. المقترح {$resolution['suggested_daily_pages']} صفحة يوميًا.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $extensionText = $appliedExtension > 0
                                ? " بعد تمديد {$appliedExtension} يوم."
                                : '.';

                            Notification::make()
                                ->title('تمت إعادة الموازنة')
                                ->body("المتبقي {$remainingPages} صفحة على {$daysLeft} يوم{$extensionText}")
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
            ->emptyStateDescription('ستظهر كل ختمات المستخدمين هنا.')
            ->emptyStateIcon('heroicon-o-book-open');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKhatmas::route('/'),
            'edit' => Pages\EditKhatma::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user:id,name']);
    }

    protected static function buildDurationDailySummary(?string $startDate, ?string $endDate, int $totalPages): ?string
    {
        if (blank($startDate) || blank($endDate) || $totalPages <= 0) {
            return null;
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return null;
        }

        $days = $start->diffInDays($end) + 1;
        if ($days <= 0) {
            return null;
        }

        $average = $totalPages / $days;
        $minPages = (int) floor($average);
        $maxPages = (int) ceil($average);

        if ($minPages === $maxPages) {
            return "{$maxPages} صفحة يوميًا";
        }

        return "متغير {$minPages}–{$maxPages} صفحة";
    }
}
