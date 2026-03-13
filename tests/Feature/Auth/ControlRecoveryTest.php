<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ControlRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_route_is_hidden_when_disabled(): void
    {
        config()->set('security.control_recovery.enabled', false);
        config()->set('security.control_recovery.email', 'admin@example.com');
        config()->set('security.control_recovery.token', 'secret-token');

        $this->get('/control/recovery')->assertNotFound();
        $this->post('/control/recovery', [])->assertNotFound();
    }

    public function test_recovery_can_reset_existing_user_and_grant_admin_access(): void
    {
        config()->set('security.control_recovery.enabled', true);
        config()->set('security.control_recovery.email', 'admin@example.com');
        config()->set('security.control_recovery.token', 'secret-token');
        config()->set('security.control_recovery.create_if_missing', true);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $response = $this->post('/control/recovery', [
            'email' => 'admin@example.com',
            'token' => 'secret-token',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ]);

        $response->assertRedirect('/control/dashboard');
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertTrue((bool) $user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('NewSecurePass123', (string) $user->password));
    }

    public function test_recovery_can_create_admin_when_user_does_not_exist(): void
    {
        config()->set('security.control_recovery.enabled', true);
        config()->set('security.control_recovery.email', 'admin2@example.com');
        config()->set('security.control_recovery.token', 'secret-token-2');
        config()->set('security.control_recovery.create_if_missing', true);
        config()->set('security.control_recovery.name', 'Emergency Admin');

        $response = $this->post('/control/recovery', [
            'email' => 'admin2@example.com',
            'token' => 'secret-token-2',
            'password' => 'AnotherSecurePass123',
            'password_confirmation' => 'AnotherSecurePass123',
        ]);

        $response->assertRedirect('/control/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'admin2@example.com',
            'name' => 'Emergency Admin',
            'is_admin' => true,
        ]);
    }

    public function test_recovery_cannot_create_admin_when_create_if_missing_is_disabled(): void
    {
        config()->set('security.control_recovery.enabled', true);
        config()->set('security.control_recovery.email', 'admin3@example.com');
        config()->set('security.control_recovery.token', 'secret-token-3');
        config()->set('security.control_recovery.create_if_missing', false);

        $response = $this->post('/control/recovery', [
            'email' => 'admin3@example.com',
            'token' => 'secret-token-3',
            'password' => 'AnotherSecurePass123',
            'password_confirmation' => 'AnotherSecurePass123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('users', [
            'email' => 'admin3@example.com',
        ]);
    }

    public function test_recovery_route_is_hidden_when_request_ip_is_not_allowed(): void
    {
        config()->set('security.control_recovery.enabled', true);
        config()->set('security.control_recovery.email', 'admin@example.com');
        config()->set('security.control_recovery.token', 'secret-token');
        config()->set('security.control_recovery.allowed_ips', ['203.0.113.10']);

        $this->get('/control/recovery')->assertNotFound();
        $this->post('/control/recovery', [])->assertNotFound();
    }
}
