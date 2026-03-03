<?php

namespace Tests\Feature\Filament;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Filament\Pages\History;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryRecordsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_defaults_to_last_30_days_and_can_switch_to_100_records(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة السجل',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 60,
            'total_pages' => 60,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 6,
            'start_date' => Carbon::today()->subDays(60),
            'expected_end_date' => Carbon::today()->addDays(30),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::today()->subDays(40),
            'from_page' => 1,
            'to_page' => 2,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now()->subDays(40),
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 3,
            'to_page' => 5,
            'pages_count' => 3,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $todayKey = Carbon::today()->format('Y-m-d');
        $oldKey = Carbon::today()->subDays(40)->format('Y-m-d');

        $this->actingAs($user);

        $component = Livewire::test(History::class);
        $recordsIn30Days = $component->instance()->getRecords();

        $this->assertArrayHasKey($todayKey, $recordsIn30Days);
        $this->assertArrayNotHasKey($oldKey, $recordsIn30Days);

        $component->call('setRecordsView', '100_records');
        $recordsIn100 = $component->instance()->getRecords();

        $this->assertArrayHasKey($todayKey, $recordsIn100);
        $this->assertArrayHasKey($oldKey, $recordsIn100);
    }

    public function test_history_can_filter_records_by_khatma_type(): void
    {
        $user = User::factory()->create();

        $tilawa = Khatma::create([
            'user_id' => $user->id,
            'name' => 'تلاوة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(9),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $review = Khatma::create([
            'user_id' => $user->id,
            'name' => 'مراجعة',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => Carbon::today(),
            'expected_end_date' => Carbon::today()->addDays(9),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $tilawa->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 1,
            'to_page' => 2,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DailyRecord::create([
            'khatma_id' => $review->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 3,
            'to_page' => 4,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $todayKey = Carbon::today()->format('Y-m-d');

        $this->actingAs($user);

        $component = Livewire::test(History::class);
        $allRecords = $component->instance()->getRecords();
        $this->assertCount(2, $allRecords[$todayKey]['records']);

        $component->call('setTypeFilter', KhatmaType::Tilawa->value);
        $filtered = $component->instance()->getRecords();

        $this->assertCount(1, $filtered[$todayKey]['records']);
        $this->assertSame(KhatmaType::Tilawa, $filtered[$todayKey]['records'][0]['khatma_type']);
    }

    public function test_weekly_chart_returns_seven_days_and_respects_type_filter(): void
    {
        $user = User::factory()->create();

        $tilawa = Khatma::create([
            'user_id' => $user->id,
            'name' => 'تلاوة أسبوعية',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 50,
            'total_pages' => 50,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today()->subDays(7),
            'expected_end_date' => Carbon::today()->addDays(7),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $review = Khatma::create([
            'user_id' => $user->id,
            'name' => 'مراجعة أسبوعية',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 50,
            'total_pages' => 50,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => Carbon::today()->subDays(7),
            'expected_end_date' => Carbon::today()->addDays(7),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $tilawa->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 1,
            'to_page' => 4,
            'pages_count' => 4,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DailyRecord::create([
            'khatma_id' => $review->id,
            'user_id' => $user->id,
            'date' => Carbon::today()->subDay(),
            'from_page' => 5,
            'to_page' => 7,
            'pages_count' => 3,
            'is_completed' => true,
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test(History::class);
        $weekly = $component->instance()->getWeeklyChartData();

        $this->assertCount(7, $weekly['days']);
        $this->assertSame(7, $weekly['total_pages']);

        $component->call('setTypeFilter', KhatmaType::Tilawa->value);
        $filteredWeekly = $component->instance()->getWeeklyChartData();

        $this->assertSame(4, $filteredWeekly['total_pages']);
    }

    public function test_history_can_apply_custom_date_range(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة نطاق مخصص',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => Carbon::today()->subDays(30),
            'expected_end_date' => Carbon::today()->addDays(10),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-01',
            'from_page' => 1,
            'to_page' => 2,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now()->subDays(4),
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-20',
            'from_page' => 3,
            'to_page' => 4,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now()->subDays(1),
        ]);

        $this->actingAs($user);

        $component = Livewire::test(History::class)
            ->set('dateFrom', '2026-03-18')
            ->set('dateTo', '2026-03-25')
            ->call('applyCustomRange');

        $records = $component->instance()->getRecords();

        $this->assertArrayHasKey('2026-03-20', $records);
        $this->assertArrayNotHasKey('2026-03-01', $records);
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test(History::class)
            ->set('dateFrom', '2026-03-01')
            ->set('dateTo', null)
            ->call('applyCustomRange');

        $this->assertSame('30_days', $component->get('recordsView'));
    }

    public function test_stats_respect_hundred_records_view_limit(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة 100 سجل',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 604,
            'total_pages' => 604,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 1,
            'start_date' => Carbon::today()->subDays(200),
            'expected_end_date' => Carbon::today()->addDays(200),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        for ($i = 0; $i < 120; $i++) {
            $date = Carbon::today()->subDays($i);

            DailyRecord::create([
                'khatma_id' => $khatma->id,
                'user_id' => $user->id,
                'date' => $date,
                'from_page' => 1,
                'to_page' => 1,
                'pages_count' => 1,
                'is_completed' => true,
                'completed_at' => $date->copy()->setTime(6, 0),
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(History::class)
            ->call('setRecordsView', '100_records');

        $stats = $component->instance()->getStatsData();

        $this->assertSame(100, $stats['total_records']);
        $this->assertSame(100, $stats['total_pages']);
    }
}
