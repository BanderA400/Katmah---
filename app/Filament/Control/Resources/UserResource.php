<?php

namespace App\Filament\Control\Resources;

use App\Enums\KhatmaStatus;
use App\Filament\Control\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AppSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المنصة';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الحساب')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_admin')
                            ->label('صلاحية الأدمن')
                            ->helperText('يستطيع الدخول إلى /control وإدارة المنصة بالكامل.')
                            ->default(false)
                            ->inline(false),
                    ])
                    ->columns(2),

                Section::make('الإعدادات الافتراضية للمستخدم')
                    ->schema([
                        Forms\Components\Toggle::make('default_auto_compensate_missed_days')
                            ->label('تعويض تلقائي افتراضي عند فوات الأيام')
                            ->default(fn (): bool => (bool) AppSettings::get(
                                AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE,
                                false,
                            ))
                            ->inline(false),

                        Forms\Components\TextInput::make('default_daily_pages')
                            ->label('الورد اليومي الافتراضي (صفحات)')
                            ->numeric()
                            ->default(fn (): int => max(min((int) AppSettings::get(
                                AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES,
                                5,
                            ), 604), 1))
                            ->minValue(1)
                            ->maxValue(604)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('أدمن')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('التفعيل')
                    ->boolean()
                    ->state(fn (User $record): bool => $record->email_verified_at !== null)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('khatmas_count')
                    ->label('الختمات')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('active_khatmas_count')
                    ->label('نشطة')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_completed_at')
                    ->label('آخر إنجاز')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime('j M Y - H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_admin')
                    ->label('الدور')
                    ->options([
                        '1' => 'أدمن',
                        '0' => 'مستخدم',
                    ]),

                Tables\Filters\SelectFilter::make('email_verified')
                    ->label('التحقق')
                    ->options([
                        'yes' => 'مفعل البريد',
                        'no' => 'غير مفعل',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'yes' => $query->whereNotNull('email_verified_at'),
                            'no' => $query->whereNull('email_verified_at'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('toggle_admin')
                    ->label(fn (User $record): string => $record->is_admin ? 'سحب صلاحية الأدمن' : 'ترقية لأدمن')
                    ->icon(fn (User $record): string => $record->is_admin ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check')
                    ->color(fn (User $record): string => $record->is_admin ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $authUser = auth()->user();

                        if ($record->is_admin && $authUser && (int) $record->id === (int) $authUser->id) {
                            Notification::make()
                                ->title('إجراء مرفوض')
                                ->body('لا يمكنك سحب صلاحية الأدمن من حسابك الحالي.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->is_admin) {
                            $adminsCount = User::query()->where('is_admin', true)->count();

                            if ($adminsCount <= 1) {
                                Notification::make()
                                    ->title('إجراء مرفوض')
                                    ->body('لا يمكن إزالة آخر حساب أدمن في النظام.')
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $record->update([
                            'is_admin' => ! $record->is_admin,
                        ]);

                        Notification::make()
                            ->title('تم تحديث الصلاحية')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make()
                    ->label('تعديل'),

                \Filament\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (User $record): bool => (int) $record->id !== (int) auth()->id()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا يوجد مستخدمون')
            ->emptyStateDescription('عند التسجيل سيظهر المستخدمون هنا.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('khatmas')
            ->withCount([
                'khatmas as active_khatmas_count' => fn (Builder $query): Builder => $query->where('status', KhatmaStatus::Active),
            ])
            ->withMax([
                'dailyRecords as last_completed_at' => fn (Builder $query): Builder => $query->where('is_completed', true),
            ], 'completed_at');
    }
}
