<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_default_planning_settings(): void
    {
        $user = User::factory()->create([
            'default_auto_compensate_missed_days' => false,
            'default_daily_pages' => 5,
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
            'weekly_reports_enabled' => true,
            'monthly_reports_enabled' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('defaultAutoCompensateMissedDays', true)
            ->set('defaultDailyPages', 11)
            ->set('wirdRemindersEnabled', false)
            ->set('wirdRemindersTime', '21:30')
            ->set('weeklyReportsEnabled', false)
            ->set('monthlyReportsEnabled', true)
            ->call('saveDefaults');

        $user->refresh();

        $this->assertTrue($user->default_auto_compensate_missed_days);
        $this->assertSame(11, $user->default_daily_pages);
        $this->assertFalse($user->wird_reminders_enabled);
        $this->assertSame('21:30:00', $user->wird_reminders_time);
        $this->assertFalse($user->weekly_reports_enabled);
        $this->assertTrue($user->monthly_reports_enabled);
    }
}
