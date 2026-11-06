<?php

namespace Tests\Feature;

use App\Models\DailyVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalVisitsTest extends TestCase
{
    use RefreshDatabase;

    /** UA de navegador real: el middleware descarta los bots conocidos. */
    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private function visits(): int
    {
        return DailyVisit::total();
    }

    public function test_a_public_visit_counts_once_per_session_and_day(): void
    {
        $this->withHeader('User-Agent', self::BROWSER)->get('/')->assertOk();
        $this->assertSame(1, $this->visits());

        // La misma sesión ya está marcada: refrescar la portada no vuelve a sumar.
        $this->get('/')->assertOk();
        $this->assertSame(1, $this->visits());
    }

    public function test_admin_post_errors_head_and_bots_are_not_counted(): void
    {
        $this->withHeader('User-Agent', self::BROWSER)->get('/admin/login')->assertOk();
        $this->post('/contacto', []);
        $this->get('/ruta-inexistente')->assertNotFound();
        $this->head('/')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->get('/');

        $this->assertSame(0, $this->visits());
    }

    public function test_home_renders_the_widget_in_the_sidebar_and_in_the_mobile_footer(): void
    {
        // Primera visita humana: deja la sesión marcada y suma 1.
        $this->withHeader('User-Agent', self::BROWSER)->get('/')->assertOk();
        DailyVisit::create(['day' => now()->subDay()->toDateString(), 'count' => 1233]);

        $response = $this->get('/')->assertOk();
        $response->assertViewHas('visitsTotal', 1234);

        $html = $response->getContent();
        $this->assertStringContainsString('VISITAS AL PORTAL', $html);
        $this->assertStringContainsString('1.234', $html);
        // Sidebar (lg+) y footer móvil: nunca visibles a la vez (breakpoints).
        $this->assertStringContainsString('d-none d-lg-block', $html);
        $this->assertStringContainsString('d-lg-none', $html);

        // En el resto de páginas públicas el footer no lleva el contador.
        $other = $this->get('/noticias')->assertOk()->getContent();
        $this->assertStringNotContainsString('VISITAS AL PORTAL', $other);
    }
}
