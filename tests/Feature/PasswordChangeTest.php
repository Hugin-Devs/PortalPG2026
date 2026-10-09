<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_null_password_changed_at_is_redirected_to_change_password(): void
    {
        $user = User::factory()->create(['password_changed_at' => null]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/cambiar-contrasena');
    }

    public function test_user_can_access_admin_after_changing_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'password_changed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/admin/cambiar-contrasena', [
            'current_password' => 'old-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_changed_at);

        // Tras el cambio, el panel debe ser accesible sin redirección
        $this->get('/admin')->assertOk();
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'password_changed_at' => null,
        ]);

        $response = $this->actingAs($user)->from('/admin/cambiar-contrasena')->post('/admin/cambiar-contrasena', [
            'current_password' => 'wrong-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ]);

        $response->assertRedirect('/admin/cambiar-contrasena');
        $response->assertSessionHasErrors('current_password');
    }

    public function test_password_change_requires_different_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'password_changed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/admin/cambiar-contrasena', [
            'current_password' => 'old-password',
            'password' => 'old-password',
            'password_confirmation' => 'old-password',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
