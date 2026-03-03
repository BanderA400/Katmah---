<?php

namespace Tests\Feature\Filament;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Filament\Resources\KhatmaResource;
use App\Filament\Resources\KhatmaResource\Pages\CreateKhatma;
use App\Filament\Resources\KhatmaResource\Pages\EditKhatma;
use App\Models\Khatma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KhatmaCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_route_redirects_guest_to_admin_login(): void
    {
        $response = $this->get(KhatmaResource::getUrl('create'));

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_authenticated_user_can_render_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(KhatmaResource::getUrl('create'));

        $response->assertOk();
        $response->assertSeeText('اسم الختمة');
        $response->assertSeeText('نطاق الختمة');
    }

    public function test_authenticated_user_can_create_khatma_from_create_page(): void
    {
        $user = User::factory()->create();
        $startDate = '2026-03-01';

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة اختبار',
                'type' => KhatmaType::Tilawa,
                'scope' => KhatmaScope::Full,
                'direction' => KhatmaDirection::Forward,
                'start_page' => 1,
                'end_page' => 604,
                'start_date' => $startDate,
                'planning_method' => PlanningMethod::ByWird,
                'daily_pages' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('khatmas', [
            'user_id' => $user->id,
            'name' => 'ختمة اختبار',
            'type' => KhatmaType::Tilawa->value,
            'scope' => KhatmaScope::Full->value,
            'direction' => KhatmaDirection::Forward->value,
            'planning_method' => PlanningMethod::ByWird->value,
            'start_page' => 1,
            'end_page' => 604,
            'total_pages' => 604,
            'daily_pages' => 10,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        $khatma = Khatma::query()->firstOrFail();
        $expectedEndDate = Carbon::parse($startDate)->addDays((int) ceil(604 / 10) - 1)->toDateString();

        $this->assertSame($expectedEndDate, $khatma->expected_end_date?->toDateString());
    }

    public function test_create_page_shows_validation_error_when_end_page_is_before_start_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة غير صالحة',
                'type' => KhatmaType::Hifz,
                'scope' => KhatmaScope::Custom,
                'direction' => KhatmaDirection::Forward,
                'start_page' => 500,
                'end_page' => 200,
                'start_date' => '2026-03-01',
                'planning_method' => PlanningMethod::ByWird,
                'daily_pages' => 5,
            ])
            ->call('create')
            ->assertHasFormErrors(['end_page']);

        $this->assertDatabaseCount('khatmas', 0);
    }

    public function test_create_with_backward_direction_starts_from_end_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة عكسية',
                'type' => KhatmaType::Review,
                'scope' => KhatmaScope::Full,
                'direction' => KhatmaDirection::Backward,
                'start_page' => 1,
                'end_page' => 604,
                'start_date' => '2026-03-01',
                'planning_method' => PlanningMethod::ByWird->value,
                'daily_pages' => 8,
                'expected_end_date' => '2026-05-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $khatma = Khatma::query()->firstOrFail();

        $this->assertSame(604, $khatma->current_page);
    }

    public function test_create_by_duration_calculates_daily_pages_when_only_end_date_is_provided(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة بالمدة',
                'type' => KhatmaType::Tilawa,
                'scope' => KhatmaScope::Full,
                'direction' => KhatmaDirection::Forward,
                'start_page' => 1,
                'end_page' => 604,
                'start_date' => '2026-03-01',
                'planning_method' => PlanningMethod::ByDuration,
                'expected_end_date' => '2026-04-30',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $khatma = Khatma::query()->firstOrFail();

        $this->assertGreaterThan(0, $khatma->daily_pages);
    }

    public function test_edit_keeps_direction_unchanged_when_progress_exists(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة متقدمة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Full,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 604,
            'total_pages' => 604,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 10,
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-04-30',
            'status' => \App\Enums\KhatmaStatus::Active,
            'current_page' => 51,
            'completed_pages' => 50,
        ]);

        $this->actingAs($user);

        Livewire::test(EditKhatma::class, ['record' => $khatma->getRouteKey()])
            ->fillForm([
                'direction' => KhatmaDirection::Backward,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(KhatmaDirection::Forward, $khatma->fresh()->direction);
    }

    public function test_edit_keeps_scope_and_pages_unchanged_when_progress_exists(): void
    {
        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة نطاق ثابت',
            'type' => KhatmaType::Review,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 100,
            'end_page' => 200,
            'total_pages' => 101,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-04-30',
            'status' => \App\Enums\KhatmaStatus::Active,
            'current_page' => 120,
            'completed_pages' => 20,
        ]);

        $this->actingAs($user);

        Livewire::test(EditKhatma::class, ['record' => $khatma->getRouteKey()])
            ->fillForm([
                'scope' => KhatmaScope::Full,
                'start_page' => 1,
                'end_page' => 604,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $khatma->fresh();

        $this->assertSame(KhatmaScope::Custom, $fresh->scope);
        $this->assertSame(100, $fresh->start_page);
        $this->assertSame(200, $fresh->end_page);
        $this->assertSame(101, $fresh->total_pages);
    }

    public function test_create_uses_user_defaults_for_daily_pages_and_compensation(): void
    {
        $user = User::factory()->create([
            'default_daily_pages' => 12,
            'default_auto_compensate_missed_days' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة بالافتراضيات',
                'type' => KhatmaType::Tilawa,
                'scope' => KhatmaScope::Full,
                'direction' => KhatmaDirection::Forward,
                'start_page' => 1,
                'end_page' => 604,
                'start_date' => '2026-03-01',
                'planning_method' => PlanningMethod::ByWird,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $khatma = Khatma::query()->firstOrFail();

        $this->assertSame(12, $khatma->daily_pages);
        $this->assertTrue($khatma->auto_compensate_missed_days);
    }

    public function test_template_selection_does_not_override_manual_by_wird_choice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateKhatma::class)
            ->fillForm([
                'name' => 'ختمة بالقالب',
                'type' => KhatmaType::Review,
                'scope' => KhatmaScope::Full,
                'direction' => KhatmaDirection::Forward,
                'start_page' => 1,
                'end_page' => 604,
                'start_date' => '2026-03-01',
                'planning_method' => PlanningMethod::ByDuration,
                'expected_end_date' => '2026-03-30',
                'daily_pages' => 5,
            ])
            ->set('data.template_days', '30')
            ->set('data.planning_method', PlanningMethod::ByWird->value)
            ->set('data.daily_pages', 5)
            ->call('create')
            ->assertHasNoFormErrors();

        $khatma = Khatma::query()->firstOrFail();

        $this->assertSame(PlanningMethod::ByWird, $khatma->planning_method);
        $this->assertSame('2026-06-29', $khatma->expected_end_date?->toDateString());
    }
}
