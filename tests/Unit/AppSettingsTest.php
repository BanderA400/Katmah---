<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_can_be_saved_and_loaded_with_correct_types(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES => 9,
            AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE => true,
            AppSettings::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT => 20,
            AppSettings::KEY_HISTORY_DEFAULT_RECORDS_VIEW => '100_records',
            AppSettings::KEY_LANDING_CONTACT_EMAIL => 'team@example.com',
            AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER => false,
        ]);

        $values = AppSettings::getMany();

        $this->assertSame(9, $values[AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES]);
        $this->assertTrue($values[AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE]);
        $this->assertSame(20, $values[AppSettings::KEY_CONTROL_DASHBOARD_ACTIVITY_LIMIT]);
        $this->assertSame('100_records', $values[AppSettings::KEY_HISTORY_DEFAULT_RECORDS_VIEW]);
        $this->assertSame('team@example.com', $values[AppSettings::KEY_LANDING_CONTACT_EMAIL]);
        $this->assertFalse($values[AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER]);
    }

    public function test_new_user_uses_global_defaults_when_user_defaults_are_not_provided(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES => 13,
            AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE => true,
        ]);

        $user = User::query()->create([
            'name' => 'Settings User',
            'email' => 'settings-user@example.com',
            'password' => 'password',
            'default_auto_compensate_missed_days' => null,
            'default_daily_pages' => null,
            'is_admin' => false,
        ]);

        $this->assertTrue($user->default_auto_compensate_missed_days);
        $this->assertSame(13, $user->default_daily_pages);
    }
}
