<?php

namespace Tests\Feature\Filament;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Filament\Pages\Dashboard;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use App\Notifications\DailyWirdCompletedNotification;
use App\Notifications\KhatmaCompletedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_partial_progress_can_be_recorded_multiple_times_in_same_day(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة يومية',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(3),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->set("partialPages.{$khatma->id}", 2)
            ->call('completePartialWird', $khatma->id)
            ->set("partialPages.{$khatma->id}", 3)
            ->call('completePartialWird', $khatma->id);

        $this->assertSame(
            2,
            DailyRecord::where('khatma_id', $khatma->id)->whereDate('date', Carbon::today())->count(),
        );
        $this->assertSame(
            5,
            (int) DailyRecord::where('khatma_id', $khatma->id)->whereDate('date', Carbon::today())->sum('pages_count'),
        );

        $khatma->refresh();

        $this->assertSame(5, $khatma->completed_pages);
        $this->assertSame(6, $khatma->current_page);
    }

    public function test_commitment_rate_is_calculated_from_first_khatma_start_date(): void
    {
        $user = User::factory()->create();
        $startDate = Carbon::today()->subDays(9);

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة التزام',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 30,
            'total_pages' => 30,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 3,
            'start_date' => $startDate,
            'expected_end_date' => Carbon::today(),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 1,
            'to_page' => 3,
            'pages_count' => 3,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::yesterday(),
            'from_page' => 4,
            'to_page' => 6,
            'pages_count' => 3,
            'is_completed' => true,
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        $widgets = Livewire::test(Dashboard::class)->instance()->getWidgetsData();

        $this->assertSame(20.0, $widgets['commitment_rate']);
    }

    public function test_user_can_pause_and_resume_khatma_from_dashboard(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة قابلة للإيقاف',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 40,
            'total_pages' => 40,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 4,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(9),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('pauseKhatma', $khatma->id);

        $this->assertSame(KhatmaStatus::Paused, $khatma->fresh()->status);

        Livewire::test(Dashboard::class)
            ->call('resumeKhatma', $khatma->id);

        $this->assertSame(KhatmaStatus::Active, $khatma->fresh()->status);
    }

    public function test_widgets_include_total_remaining_and_nearest_expected_end_date_for_active_khatmas(): void
    {
        Carbon::setTestNow('2026-03-05 10:00:00');

        $user = User::factory()->create();

        Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة ١',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 100,
            'total_pages' => 100,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today()->subDays(5),
            'expected_end_date' => Carbon::today()->addDays(10),
            'status' => KhatmaStatus::Active,
            'current_page' => 21,
            'completed_pages' => 20,
        ]);

        Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة ٢',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 60,
            'total_pages' => 60,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 4,
            'start_date' => Carbon::today()->subDays(3),
            'expected_end_date' => Carbon::today()->addDays(5),
            'status' => KhatmaStatus::Active,
            'current_page' => 11,
            'completed_pages' => 10,
        ]);

        Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة متوقفة',
            'type' => KhatmaType::Hifz,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => Carbon::today()->subDays(2),
            'expected_end_date' => Carbon::today()->addDay(),
            'status' => KhatmaStatus::Paused,
            'current_page' => 6,
            'completed_pages' => 5,
        ]);

        $this->actingAs($user);

        $widgets = Livewire::test(Dashboard::class)->instance()->getWidgetsData();

        $this->assertSame(130, $widgets['total_remaining']);
        $this->assertSame(Carbon::today()->addDays(5)->toDateString(), $widgets['nearest_end_date_iso']);
    }

    public function test_rest_day_card_shows_no_today_page_segment(): void
    {
        Carbon::setTestNow('2026-03-05 10:00:00');

        $user = User::factory()->create();

        Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة يوم راحة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 1,
            'total_pages' => 1,
            'planning_method' => PlanningMethod::ByDuration,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 1,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(2),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('لا يوجد ورد مطلوب اليوم حسب الخطة')
            ->assertSee('لا يوجد نطاق صفحات لليوم')
            ->assertDontSee('صفحة 1 — 1');
    }

    public function test_rebalance_updates_expected_end_date_for_by_wird_khatma(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');

        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة تحتاج موازنة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 100,
            'total_pages' => 100,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 10,
            'start_date' => Carbon::today()->subDays(9),
            'expected_end_date' => Carbon::today()->addDays(1),
            'status' => KhatmaStatus::Active,
            'current_page' => 31,
            'completed_pages' => 30,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('rebalanceKhatma', $khatma->id);

        $khatma->refresh();

        $this->assertSame(Carbon::today()->addDays(6)->toDateString(), $khatma->expected_end_date?->toDateString());
    }

    public function test_rebalance_by_duration_applies_smart_extension_before_raising_daily_target(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');

        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة ذكية',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 300,
            'total_pages' => 300,
            'planning_method' => PlanningMethod::ByDuration,
            'auto_compensate_missed_days' => true,
            'daily_pages' => 30,
            'start_date' => Carbon::today()->subDays(5),
            'expected_end_date' => Carbon::today()->addDays(9),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('rebalanceKhatma', $khatma->id);

        $khatma->refresh();

        $this->assertSame(Carbon::today()->addDays(11)->toDateString(), $khatma->expected_end_date?->toDateString());
        $this->assertSame(25, (int) $khatma->daily_pages);
        $this->assertSame(2, (int) $khatma->smart_extension_days_used);
    }

    public function test_daily_wird_completion_sends_congrats_email(): void
    {
        Carbon::setTestNow('2026-03-12 09:00:00');

        $user = User::factory()->create();
        Notification::fake();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة يومية',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(3),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('completeWird', $khatma->id);

        Notification::assertSentTo($user, DailyWirdCompletedNotification::class);
        Notification::assertNotSentTo($user, KhatmaCompletedNotification::class);
    }

    public function test_khatma_completion_sends_final_congrats_email(): void
    {
        Carbon::setTestNow('2026-03-12 09:00:00');

        $user = User::factory()->create();
        Notification::fake();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة مكتملة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 5,
            'total_pages' => 5,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today(),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('completeWird', $khatma->id);

        $this->assertSame(KhatmaStatus::Completed, $khatma->fresh()->status);
        Notification::assertSentTo($user, KhatmaCompletedNotification::class);
        Notification::assertNotSentTo($user, DailyWirdCompletedNotification::class);
    }

    public function test_monthly_commitment_calendar_counts_done_and_missed_days(): void
    {
        Carbon::setTestNow('2026-03-12 09:00:00');

        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة تقويم',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 50,
            'total_pages' => 50,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-03-20',
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-10',
            'from_page' => 1,
            'to_page' => 5,
            'pages_count' => 5,
            'is_completed' => true,
            'completed_at' => now()->subDays(2),
        ]);

        $this->actingAs($user);

        $calendar = Livewire::test(Dashboard::class)->instance()->getMonthlyCommitmentCalendar();

        $this->assertGreaterThanOrEqual(1, $calendar['done_days']);
        $this->assertGreaterThanOrEqual(1, $calendar['missed_days']);
    }

    public function test_monthly_commitment_calendar_for_new_user_has_no_missed_days(): void
    {
        Carbon::setTestNow('2026-03-12 09:00:00');

        $user = User::factory()->create();

        $this->actingAs($user);

        $calendar = Livewire::test(Dashboard::class)->instance()->getMonthlyCommitmentCalendar();

        $this->assertSame(0, $calendar['done_days']);
        $this->assertSame(0, $calendar['missed_days']);
        $this->assertSame(0.0, (float) $calendar['month_commitment']);
    }
}
