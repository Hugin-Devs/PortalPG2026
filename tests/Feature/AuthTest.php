<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_successful_login_authenticates_and_records_last_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('Credenciales inválidas.', session('errors')->first('email'));
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_login_and_sees_generic_message(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('Credenciales inválidas.', session('errors')->first('email'));
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_logout_destroys_the_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_guest_on_permission_route_is_redirected_to_login(): void
    {
        // CheckPermission debe fallar cerrado (redirect a login) cuando no hay
        // sesión, antes de evaluar el permiso.
        $this->get('/admin/entes')->assertRedirect('/admin/login');
    }
}
