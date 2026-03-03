<?php

namespace App\Filament\Control\Resources;

use App\Enums\KhatmaType;
use App\Filament\Control\Resources\DailyRecordResource\Pages;
use App\Models\DailyRecord;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyRecordResource extends Resource
{
    protected static ?string $model = DailyRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'سجل الإنجاز';

    protected static ?string $modelLabel = 'سجل إنجاز';

    protected static ?string $pluralModelLabel = 'سجل الإنجاز';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المنصة';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('j M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('khatma.name')
                    ->label('الختمة')
                    ->searchable(),

                Tables\Columns\TextColumn::make('khatma.type')
                    ->label('نوع الختمة')
                    ->badge(),

                Tables\Columns\TextColumn::make('from_page')
                    ->label('من')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('to_page')
                    ->label('إلى')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('pages_count')
                    ->label('عدد الصفحات')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_completed')
                    ->label('مكتمل')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('وقت الإكمال')
                    ->dateTime('j M Y - H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_completed')
                    ->label('الحالة')
                    ->options([
                        '1' => 'مكتمل',
                        '0' => 'غير مكتمل',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('khatma_type')
                    ->label('نوع الختمة')
                    ->options(KhatmaType::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['value'] ?? null;

                        if (! is_string($type) || $type === '') {
                            return $query;
                        }

                        return $query->whereHas('khatma', fn (Builder $khatmaQuery): Builder => $khatmaQuery->where('type', $type));
                    }),

                Tables\Filters\Filter::make('date_range')
                    ->label('نطاق تاريخ')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('من')->native(false),
                        Forms\Components\DatePicker::make('to')->label('إلى')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['from'] ?? null) {
                            $query->whereDate('date', '>=', $data['from']);
                        }

                        if ($data['to'] ?? null) {
                            $query->whereDate('date', '<=', $data['to']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                \Filament\Actions\DeleteAction::make()
                    ->label('حذف'),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد سجلات')
            ->emptyStateDescription('عند تسجيل الإنجاز ستظهر السجلات هنا.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyRecords::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user:id,name',
                'khatma:id,name,type',
            ]);
    }
}
