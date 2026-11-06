<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /extranet/* con la intranet despublicada (scripts/intranet.sh off):
 * la ruta responde 503 con la vista de mantenimiento en vez del 404 anterior.
 * /intranet_jubilados/* queda fuera de alcance y sigue devolviendo 404.
 */
class ExtranetMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const MANTENIMIENTO = 'Sitio en mantenimiento';

    public function test_extranet_urls_get_maintenance_view_with_503(): void
    {
        $urls = [
            '/extranet/login.php',
            '/extranet/usuarioslist.php',
            '/extranet',
            '/extranet/',
            '/extranet/bootstrap3/css/bootstrap.css',
            '/extranet/ruta/profunda/archivo.php',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);

            $response->assertStatus(503);
            $response->assertSee(self::MANTENIMIENTO);
            $response->assertSee('Ir al inicio');
        }
    }

    public function test_post_without_csrf_token_still_gets_maintenance_view(): void
    {
        // El login antiguo del sistema hacía POST relativo a login.php; sin token
        // CSRF (excluido en la ruta) debe llegar a la vista, no a un 419.
        $response = $this->post('/extranet/login.php', ['user' => 'x', 'pass' => 'y']);

        $response->assertStatus(503);
        $response->assertSee(self::MANTENIMIENTO);
    }

    public function test_head_request_gets_maintenance_status(): void
    {
        $this->call('HEAD', '/extranet/login.php')->assertStatus(503);
    }

    public function test_maintenance_response_carries_retry_after_and_security_headers(): void
    {
        $response = $this->get('/extranet/login.php');

        $response->assertStatus(503);
        $response->assertHeader('Retry-After', '3600');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_maintenance_view_is_standalone_with_working_actions(): void
    {
        $body = $this->get('/extranet/login.php')->getContent();

        // Standalone: sin layout público (navbar/footer) ni assets de Vite —
        // renderiza aunque el sitio degrada, igual que las vistas 500/503.
        $this->assertStringNotContainsString('<nav class="navbar', $body);
        $this->assertStringNotContainsString('<footer', $body);
        $this->assertStringNotContainsString('/build/', $body);
        // Los botones del parcial _standalone llevan su JS inline con nonce.
        $this->assertStringContainsString('data-action="reload"', $body);
        $this->assertMatchesRegularExpression('/<script nonce="[A-Za-z0-9+\/=]{24}">/', $body);
    }

    public function test_out_of_scope_jubilados_still_returns_404(): void
    {
        $this->get('/intranet_jubilados/login.php')->assertNotFound();
    }

    public function test_normal_404_routes_are_untouched(): void
    {
        $this->get('/pagina-que-no-existe-xyz')->assertNotFound();
    }

    public function test_portal_pages_still_work(): void
    {
        $this->get('/')->assertOk();
        $this->get('/noticias')->assertOk();
    }
}
