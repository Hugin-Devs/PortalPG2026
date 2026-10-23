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

    /**
     * Regresión (F6, revisión 2026-10-23): el constraint de PHP debe ser ^8.3.
     * PHP 8.1/8.2 están EOL (31-dic-2025 / 31-dic-2026) y no reciben parches
     * de seguridad; fijar ^8.3 garantiza que `composer install` falle en
     * cualquier runtime sin soporte en lugar de desplegar en silencio.
     */
    public function test_composer_requires_supported_php_version(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            '^8.3',
            $composer['require']['php'],
            'composer.json debe exigir PHP ^8.3 (8.1/8.2 EOL). Actualizar README/DOCUMENTACION al cambiarlo.'
        );
    }
}
