<?php

namespace App\Filament\Control\Pages;

use App\Support\AppSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'إعدادات النظام';

    protected static ?string $title = 'إعدادات النظام';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المنصة';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.control.pages.settings';

    protected static ?string $slug = 'settings';

    public int|string|null $globalDefaultDailyPages = 5;

    public bool $globalDefaultAutoCompensateMissedDays = false;

    public int|string|null $controlDashboardActivityLimit = 12;

    public string $historyDefaultRecordsView = '30_days';

    public ?string $landingContactEmail = null;

    public ?string $landingXUrl = null;

    public bool $landingShowVisitCounter = true;

    public function mount(): void
    {
        $settings = AppSettings::getMany();

        $this->globalDefaultDailyPages = (int) ($settings[AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES] ?? 5);
        $this->globalDefaultAutoCompensateMissedDays = (bool) ($settings[AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE] ?? false);
        $this->controlDashboardActivityLimit = (int) ($settings[AppSettings::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT] ?? 12);
        $this->historyDefaultRecordsView = (string) ($settings[AppSettings::KEY_HISTORY_DEFAULT_RECORDS_VIEW] ?? '30_days');
        $this->landingContactEmail = $this->normalizeNullableString($settings[AppSettings::KEY_LANDING_CONTACT_EMAIL] ?? null);
        $this->landingXUrl = $this->normalizeNullableString($settings[AppSettings::KEY_LANDING_X_URL] ?? null);
        $this->landingShowVisitCounter = (bool) ($settings[AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER] ?? true);
    }

    public function saveSettings(): void
    {
        $data = validator(
            [
                'globalDefaultDailyPages' => $this->globalDefaultDailyPages,
                'globalDefaultAutoCompensateMissedDays' => $this->globalDefaultAutoCompensateMissedDays,
                'controlDashboardActivityLimit' => $this->controlDashboardActivityLimit,
                'historyDefaultRecordsView' => $this->historyDefaultRecordsView,
                'landingContactEmail' => $this->landingContactEmail,
                'landingXUrl' => $this->landingXUrl,
                'landingShowVisitCounter' => $this->landingShowVisitCounter,
            ],
            [
                'globalDefaultDailyPages' => ['required', 'integer', 'min:1', 'max:604'],
                'globalDefaultAutoCompensateMissedDays' => ['required', 'boolean'],
                'controlDashboardActivityLimit' => ['required', 'integer', 'min:5', 'max:100'],
                'historyDefaultRecordsView' => ['required', 'in:7_days,30_days,100_records'],
                'landingContactEmail' => ['nullable', 'email'],
                'landingXUrl' => ['nullable', 'url'],
                'landingShowVisitCounter' => ['required', 'boolean'],
            ],
            [
                'globalDefaultDailyPages.min' => 'الورد اليومي الافتراضي يجب أن يكون 1 صفحة على الأقل.',
                'globalDefaultDailyPages.max' => 'الورد اليومي الافتراضي لا يمكن أن يتجاوز 604 صفحة.',
                'controlDashboardActivityLimit.min' => 'حد نشاطات لوحة التحكم لا يمكن أن يقل عن 5.',
                'controlDashboardActivityLimit.max' => 'حد نشاطات لوحة التحكم لا يمكن أن يتجاوز 100.',
                'landingContactEmail.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
                'landingXUrl.url' => 'أدخل رابطًا صحيحًا يبدأ بـ http أو https.',
            ],
        )->validate();

        AppSettings::setMany([
            AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES => (int) $data['globalDefaultDailyPages'],
            AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE => (bool) $data['globalDefaultAutoCompensateMissedDays'],
            AppSettings::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT => (int) $data['controlDashboardActivityLimit'],
            AppSettings::KEY_HISTORY_DEFAULT_RECORDS_VIEW => (string) $data['historyDefaultRecordsView'],
            AppSettings::KEY_LANDING_CONTACT_EMAIL => $this->normalizeNullableString($data['landingContactEmail'] ?? null),
            AppSettings::KEY_LANDING_X_URL => $this->normalizeNullableString($data['landingXUrl'] ?? null),
            AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER => (bool) $data['landingShowVisitCounter'],
        ], auth()->id());

        Notification::make()
            ->title('تم حفظ إعدادات النظام')
            ->body('تم تطبيق القيم الجديدة على مستوى المنصة.')
            ->success()
            ->send();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
