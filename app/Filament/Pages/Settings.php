<?php

namespace App\Filament\Pages;

use App\Support\AppSettings;
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

    public bool $defaultAutoCompensateMissedDays = true;

    public int|string|null $defaultDailyPages = 5;

    public bool $wirdRemindersEnabled = true;

    public string $wirdRemindersTime = '20:00';

    public bool $weeklyReportsEnabled = true;

    public bool $monthlyReportsEnabled = true;

    public function mount(): void
    {
        $user = auth()->user();

        $this->defaultAutoCompensateMissedDays = (bool) (
            $user?->default_auto_compensate_missed_days
            ?? AppSettings::get(AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE, true)
        );
        $this->defaultDailyPages = (int) ($user?->default_daily_pages ?? 5);
        $this->wirdRemindersEnabled = (bool) ($user?->wird_reminders_enabled ?? true);
        $reminderTime = (string) ($user?->wird_reminders_time ?? '20:00:00');
        $this->wirdRemindersTime = mb_substr($reminderTime, 0, 5);
        $this->weeklyReportsEnabled = (bool) ($user?->weekly_reports_enabled ?? true);
        $this->monthlyReportsEnabled = (bool) ($user?->monthly_reports_enabled ?? true);
    }

    public function saveDefaults(): void
    {
        $data = validator(
            [
                'defaultAutoCompensateMissedDays' => $this->defaultAutoCompensateMissedDays,
                'defaultDailyPages' => $this->defaultDailyPages,
                'wirdRemindersEnabled' => $this->wirdRemindersEnabled,
                'wirdRemindersTime' => $this->wirdRemindersTime,
                'weeklyReportsEnabled' => $this->weeklyReportsEnabled,
                'monthlyReportsEnabled' => $this->monthlyReportsEnabled,
            ],
            [
                'defaultAutoCompensateMissedDays' => ['required', 'boolean'],
                'defaultDailyPages' => ['required', 'integer', 'min:1', 'max:604'],
                'wirdRemindersEnabled' => ['required', 'boolean'],
                'wirdRemindersTime' => ['required', 'date_format:H:i'],
                'weeklyReportsEnabled' => ['required', 'boolean'],
                'monthlyReportsEnabled' => ['required', 'boolean'],
            ],
            [
                'defaultDailyPages.min' => 'الورد اليومي الافتراضي يجب أن يكون 1 صفحة على الأقل.',
                'defaultDailyPages.max' => 'الورد اليومي الافتراضي لا يمكن أن يتجاوز 604 صفحة.',
                'wirdRemindersTime.date_format' => 'وقت التذكير يجب أن يكون بصيغة ساعة:دقيقة.',
            ],
        )->validate();

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $user->update([
            'default_auto_compensate_missed_days' => (bool) $data['defaultAutoCompensateMissedDays'],
            'default_daily_pages' => (int) $data['defaultDailyPages'],
            'wird_reminders_enabled' => (bool) $data['wirdRemindersEnabled'],
            'wird_reminders_time' => "{$data['wirdRemindersTime']}:00",
            'weekly_reports_enabled' => (bool) $data['weeklyReportsEnabled'],
            'monthly_reports_enabled' => (bool) $data['monthlyReportsEnabled'],
        ]);

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->body('تم حفظ إعدادات الخطة والتذكيرات والتقارير بنجاح.')
            ->success()
            ->send();
    }
}
