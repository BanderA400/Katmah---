<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_control_login_when_accessing_control_panel(): void
    {
        $response = $this->get('/control/dashboard');

        $response->assertRedirect('/control/login');
    }

    public function test_non_admin_user_cannot_access_control_panel(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/control/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_user_can_access_control_panel(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/control/dashboard');

        $response->assertOk();
        $response->assertSeeText('مركز التحكم');
    }

    public function test_admin_user_can_access_control_management_resources(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($user)
            ->get('/control/users')
            ->assertOk()
            ->assertSeeText('المستخدمون');

        $this
            ->actingAs($user)
            ->get('/control/khatmas')
            ->assertOk()
            ->assertSeeText('الختمات');

        $this
            ->actingAs($user)
            ->get('/control/daily-records')
            ->assertOk()
            ->assertSeeText('سجل الإنجاز');

        $this
            ->actingAs($user)
            ->get('/control/settings')
            ->assertOk()
            ->assertSeeText('إعدادات النظام');
    }

    public function test_non_admin_user_cannot_access_control_management_resources(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this
            ->actingAs($user)
            ->get('/control/users')
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->get('/control/khatmas')
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->get('/control/daily-records')
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->get('/control/settings')
            ->assertForbidden();
    }
}
