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
        ]);

        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('defaultAutoCompensateMissedDays', true)
            ->set('defaultDailyPages', 11)
            ->call('saveDefaults');

        $user->refresh();

        $this->assertTrue($user->default_auto_compensate_missed_days);
        $this->assertSame(11, $user->default_daily_pages);
    }
}
