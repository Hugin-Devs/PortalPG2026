<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SecurityConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Garantiza que los tests usen la configuración del entorno de PHPUnit
        // (session=array, etc.) y no un config cacheado de otro entorno.
        Artisan::call('config:clear');
    }

    public function test_rate_limiter_uses_config_values(): void
    {
        config(['security.rate_limits.login' => 2]);

        $user = User::factory()->create();

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_security_config_is_exposed_and_typesafe(): void
    {
        $this->assertIsInt(config('security.rate_limits.login'));
        $this->assertIsInt(config('security.rate_limits.contact'));
        $this->assertIsInt(config('security.rate_limits.search'));
        $this->assertIsString(config('security.trusted_proxies'));
        $this->assertIsString(config('security.trusted_hosts'));
    }
}
