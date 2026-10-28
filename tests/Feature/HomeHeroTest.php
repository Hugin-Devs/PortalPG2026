<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hero del home: el bloque de texto se ajusta en móvil desde app.scss
 * (media query ≤767.98px) atacando la clase `hero-box` y limitando
 * `display-4`/`lead` con clamp() + line-clamp. Estos tests fijan los ganchos
 * de Blade y que el extracto NO se trunca en el servidor (el corte a 4
 * líneas es CSS y solo en pantallas pequeñas; en desktop se ve completo).
 */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_EXCERPT = 'Sosteniendo con firmeza las banderas de la prevención, seguimos caminando juntas por el futuro y la paz en este Mes Rosa, con actividades en escuelas, barrios y centros de salud de todo el Distrito Capital durante toda la jornada.';

    public function test_hero_boxes_expose_the_css_hook_in_every_branch(): void
    {
        // Rama "empty" (sin destacados publicados).
        $this->get('/')->assertOk()->assertSee('hero-box', false);

        // Rama con destacado (imagen de portada).
        $this->crearDestacado();

        $this->get('/')->assertOk()->assertSee('hero-box', false);
    }

    public function test_hero_keeps_the_full_excerpt_for_desktop(): void
    {
        $this->crearDestacado();

        // El extracto debe superar el límite móvil (4 líneas ≈ 160 caracteres)
        // para que la aserción demuestre que NO se trunca en el servidor.
        $this->assertGreaterThan(160, mb_strlen($this::LONG_EXCERPT));

        // Sin truncado en servidor: el corte a 4 líneas lo hace el CSS y solo
        // en <=767.98px (ver resources/css/app.scss).
        $this->get('/')->assertOk()->assertSee($this::LONG_EXCERPT, false);
    }

    private function crearDestacado(): void
    {
        Post::create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'title' => 'Caracas recibió el Fuego Patrio en los IV Juegos Deportivos Nacionales Comunales',
            'slug' => 'destacado-hero-'.uniqid(),
            'excerpt' => $this::LONG_EXCERPT,
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'is_featured' => true,
        ]);
    }
}
