<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_429_after_too_many_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_lockout_message_is_shown_when_attempts_are_exhausted(): void
    {
        $user = User::factory()->create();

        // Simula 5 fallos previos registrados en el rate limiter del controlador
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('login:127.0.0.1', 60);
        }

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
    }

    public function test_successful_login_clears_the_lockout_counter(): void
    {
        $user = User::factory()->create();

        RateLimiter::hit('login:127.0.0.1', 60);
        RateLimiter::hit('login:127.0.0.1', 60);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertSame(0, RateLimiter::attempts('login:127.0.0.1'));
    }

    public function test_lockout_threshold_comes_from_config(): void
    {
        config(['security.login_lockout.attempts' => 2]);

        $user = User::factory()->create();

        // 2 fallos = umbral configurado; el 3.º debe quedar bloqueado con el
        // mensaje de lockout (no con "Credenciales inválidas").
        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
    }
}
