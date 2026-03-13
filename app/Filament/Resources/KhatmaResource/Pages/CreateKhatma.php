<?php

namespace App\Filament\Resources\KhatmaResource\Pages;

use App\Enums\KhatmaDirection;
use App\Enums\PlanningMethod;
use App\Filament\Resources\KhatmaResource;
use App\Support\AppSettings;
use App\Support\SmartKhatmaPlanner;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateKhatma extends CreateRecord
{
    protected static string $resource = KhatmaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $startPage = (int) ($data['start_page'] ?? 1);
        $endPage = (int) ($data['end_page'] ?? 604);
        $planningMethod = static::stateValue($data['planning_method'] ?? null);

        $data['auto_compensate_missed_days'] = (bool) (
            $data['auto_compensate_missed_days']
                ?? $user?->default_auto_compensate_missed_days
                ?? AppSettings::get(AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE, true)
        );

        if ($endPage < $startPage) {
            throw ValidationException::withMessages([
                'end_page' => 'صفحة النهاية يجب أن تكون أكبر من أو تساوي صفحة البداية.',
            ]);
        }

        $data['total_pages'] = $endPage - $startPage + 1;

        if ($planningMethod === PlanningMethod::ByDuration->value) {
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['expected_end_date'] ?? null;

            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->startOfDay();

                if ($end->lt($start)) {
                    throw ValidationException::withMessages([
                        'expected_end_date' => 'تاريخ الختم يجب أن يكون في نفس تاريخ البداية أو بعده.',
                    ]);
                }

                $days = $start->diffInDays($end) + 1;
                $data['daily_pages'] = SmartKhatmaPlanner::calculateRoundedDailyTarget(
                    (int) $data['total_pages'],
                    $days,
                );
            }
        }

        if ($planningMethod === PlanningMethod::ByWird->value) {
            $dailyPages = (int) (
                $data['daily_pages']
                ?? $user?->default_daily_pages
                ?? AppSettings::get(AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES, 5)
            );
            $startDate = $data['start_date'] ?? null;

            if ($dailyPages > 0 && $startDate) {
                $days = (int) ceil($data['total_pages'] / $dailyPages);
                $data['expected_end_date'] = Carbon::parse($startDate)->addDays(max($days - 1, 0))->format('Y-m-d');
            }

            $data['daily_pages'] = max(min($dailyPages, 604), 1);
        }

        $data = $this->normalizeReminderSettings($data);

        $direction = static::stateValue($data['direction'] ?? KhatmaDirection::Forward->value);

        $data['user_id'] = auth()->id();
        $data['smart_extension_days_used'] = 0;
        $data['current_page'] = $direction === KhatmaDirection::Backward->value
            ? $endPage
            : $startPage;
        $data['completed_pages'] = 0;

        return $data;
    }

    protected static function stateValue(mixed $state): mixed
    {
        return $state instanceof \BackedEnum ? $state->value : $state;
    }

    private function normalizeReminderSettings(array $data): array
    {
        $useCustom = (bool) ($data['use_custom_reminder_settings'] ?? false);
        $data['use_custom_reminder_settings'] = $useCustom;

        if (! $useCustom) {
            $data['reminder_enabled'] = null;
            $data['reminder_time'] = null;

            return $data;
        }

        $enabled = (bool) ($data['reminder_enabled'] ?? true);
        $data['reminder_enabled'] = $enabled;

        if (! $enabled) {
            $data['reminder_time'] = null;

            return $data;
        }

        $rawTime = $data['reminder_time'] ?? null;

        if (! is_string($rawTime) || trim($rawTime) === '') {
            throw ValidationException::withMessages([
                'reminder_time' => 'وقت التذكير مطلوب عند تفعيل تذكير الختمة.',
            ]);
        }

        $normalizedTime = self::normalizeReminderTimeValue($rawTime);

        if ($normalizedTime === null) {
            throw ValidationException::withMessages([
                'reminder_time' => 'وقت التذكير يجب أن يكون بصيغة ساعة:دقيقة.',
            ]);
        }

        $data['reminder_time'] = $normalizedTime;

        return $data;
    }

    private static function normalizeReminderTimeValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) ? (int) $matches[3] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }
}
