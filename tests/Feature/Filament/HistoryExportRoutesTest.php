<?php

namespace Tests\Feature\Filament;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryExportRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_csv_export_requires_authentication(): void
    {
        $response = $this->get(route('history.export.csv'));

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_export_history_as_csv(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة تصدير',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => now()->subDays(4),
            'expected_end_date' => now()->addDays(6),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'from_page' => 1,
            'to_page' => 2,
            'pages_count' => 2,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('history.export.csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');
    }

    public function test_authenticated_user_can_open_printable_history_view(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة الطباعة',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 20,
            'total_pages' => 20,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 2,
            'start_date' => now()->subDays(4),
            'expected_end_date' => now()->addDays(6),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'from_page' => 1,
            'to_page' => 3,
            'pages_count' => 3,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('history.export.print'));

        $response->assertOk();
        $response->assertSee('تقرير سجل الإنجاز');
        $response->assertSee('طباعة / حفظ PDF');
    }
}
