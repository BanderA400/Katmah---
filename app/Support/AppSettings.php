<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;

class AppSettings
{
    public const KEY_GLOBAL_DEFAULT_DAILY_PAGES = 'global_default_daily_pages';
    public const KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE = 'global_default_auto_compensate_missed_days';
    public const KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT = 'control_dashboard_activity_limit';
    public const KEY_HISTORY_DEFAULT_RECORDS_VIEW = 'history_default_records_view';
    public const KEY_LANDING_CONTACT_EMAIL = 'landing_contact_email';
    public const KEY_LANDING_X_URL = 'landing_x_url';
    public const KEY_LANDING_SHOW_VISIT_COUNTER = 'landing_show_visit_counter';

    public const DEFAULTS = [
        self::KEY_GLOBAL_DEFAULT_DAILY_PAGES => 5,
        self::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE => false,
        self::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT => 12,
        self::KEY_HISTORY_DEFAULT_RECORDS_VIEW => '30_days',
        self::KEY_LANDING_CONTACT_EMAIL => 'contact@khatma.app',
        self::KEY_LANDING_X_URL => 'https://x.com/khatma_app',
        self::KEY_LANDING_SHOW_VISIT_COUNTER => true,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = AppSetting::query()->find($key);
        } catch (QueryException) {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }

        if (! $setting) {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }

        return self::decode($setting->value, $setting->type);
    }

    public static function getMany(array $defaults = []): array
    {
        $mergedDefaults = array_replace(self::DEFAULTS, $defaults);
        $keys = array_keys($mergedDefaults);

        try {
            $stored = AppSetting::query()
                ->whereIn('key', $keys)
                ->get(['key', 'value', 'type'])
                ->keyBy('key');
        } catch (QueryException) {
            return $mergedDefaults;
        }

        $values = [];

        foreach ($mergedDefaults as $key => $fallback) {
            $row = $stored->get($key);

            if (! $row) {
                $values[$key] = $fallback;
                continue;
            }

            $values[$key] = self::decode($row->value, $row->type);
        }

        return $values;
    }

    public static function setMany(array $values, ?int $updatedBy = null): void
    {
        try {
            foreach ($values as $key => $value) {
                [$rawValue, $type] = self::encode($value);

                AppSetting::query()->updateOrCreate(
                    ['key' => (string) $key],
                    [
                        'value' => $rawValue,
                        'type' => $type,
                        'updated_by' => $updatedBy,
                    ],
                );
            }
        } catch (QueryException) {
            // الجدول قد لا يكون موجودًا أثناء الإقلاع المبكر أو بعض الاختبارات.
        }
    }

    private static function encode(mixed $value): array
    {
        if (is_bool($value)) {
            return [$value ? '1' : '0', 'bool'];
        }

        if (is_int($value)) {
            return [(string) $value, 'int'];
        }

        if (is_float($value)) {
            return [(string) $value, 'float'];
        }

        if (is_array($value)) {
            return [json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'json'];
        }

        if ($value === null) {
            return [null, 'string'];
        }

        return [(string) $value, 'string'];
    }

    private static function decode(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) ((int) ($value ?? 0)),
            'int' => (int) ($value ?? 0),
            'float' => (float) ($value ?? 0),
            'json' => $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [],
            default => $value,
        };
    }
}
