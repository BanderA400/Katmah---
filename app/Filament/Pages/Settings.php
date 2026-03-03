<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'الإعدادات';

    protected static ?string $title = 'إعدادات الحساب';

    protected static string|\UnitEnum|null $navigationGroup = 'الحساب';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.settings';

    protected static ?string $slug = 'settings';

    public bool $defaultAutoCompensateMissedDays = false;

    public int|string|null $defaultDailyPages = 5;

    public function mount(): void
    {
        $user = auth()->user();

        $this->defaultAutoCompensateMissedDays = (bool) ($user?->default_auto_compensate_missed_days ?? false);
        $this->defaultDailyPages = (int) ($user?->default_daily_pages ?? 5);
    }

    public function saveDefaults(): void
    {
        $data = validator(
            [
                'defaultAutoCompensateMissedDays' => $this->defaultAutoCompensateMissedDays,
                'defaultDailyPages' => $this->defaultDailyPages,
            ],
            [
                'defaultAutoCompensateMissedDays' => ['required', 'boolean'],
                'defaultDailyPages' => ['required', 'integer', 'min:1', 'max:604'],
            ],
            [
                'defaultDailyPages.min' => 'الورد اليومي الافتراضي يجب أن يكون 1 صفحة على الأقل.',
                'defaultDailyPages.max' => 'الورد اليومي الافتراضي لا يمكن أن يتجاوز 604 صفحة.',
            ],
        )->validate();

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $user->update([
            'default_auto_compensate_missed_days' => (bool) $data['defaultAutoCompensateMissedDays'],
            'default_daily_pages' => (int) $data['defaultDailyPages'],
        ]);

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->body('سيتم تطبيق القيم الافتراضية الجديدة عند إنشاء أي ختمة جديدة.')
            ->success()
            ->send();
    }
}
