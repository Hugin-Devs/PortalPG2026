<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gaceta;
use App\Models\OrgMember;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cobertura de los controladores públicos About/Gaceta/Page/Ente/News.category
 * (huecos de cobertura detectados en la ronda de coverage ≥90%).
 */
class PublicControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_settings_and_active_members(): void
    {
        Setting::create(['key' => 'about_history', 'value' => 'Historia pública']);
        OrgMember::create(['name' => 'Directora', 'position' => 'Directora General', 'is_active' => true, 'order' => 1]);
        OrgMember::create(['name' => 'Oculto', 'position' => 'Exmiembro', 'is_active' => false, 'order' => 2]);

        $this->get('/sobre-nosotros')
            ->assertOk()
            ->assertSee('Historia pública')
            ->assertSee('Directora General')
            ->assertDontSee('Exmiembro')
            ->assertSee('Sobre Nosotros', false);
    }

    public function test_public_gacetas_index_renders_ordered_and_seo(): void
    {
        Cache::flush();
        Gaceta::create(['numero' => '12', 'fecha' => '2026-01-15', 'path' => 'gacetas/12.pdf', 'original_name' => '12.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'file_hash' => str_repeat('a', 64)]);
        Gaceta::create(['numero' => '3', 'fecha' => '2026-01-15', 'path' => 'gacetas/3.pdf', 'original_name' => '3.pdf', 'mime_type' => 'application/pdf', 'size' => 2048, 'file_hash' => str_repeat('b', 64)]);

        $this->get('/gacetas')
            ->assertOk()
            ->assertSee('Gaceta Oficial N° 12')
            ->assertSee('Gaceta Oficial N° 3')
            ->assertSee('Gacetas Oficiales', false);

        // Misma fecha: el desempate numerico desc deja la 12 antes que la 3
        $body = $this->get('/gacetas')->getContent();
        $this->assertTrue(strpos($body, 'N° 12') < strpos($body, 'N° 3'));
    }

    public function test_page_show_active_and_404(): void
    {
        Page::create(['title' => 'Términos', 'slug' => 'terminos', 'content' => '<p>Contenido legal</p>', 'is_active' => true]);
        Page::create(['title' => 'Borrador', 'slug' => 'borrador', 'content' => '<p>x</p>', 'is_active' => false]);

        $this->get('/pagina/terminos')
            ->assertOk()
            ->assertSee('Contenido legal')
            ->assertSee('Términos', false);

        $this->get('/pagina/borrador')->assertNotFound();
        $this->get('/pagina/no-existe')->assertNotFound();
    }

    public function test_ente_show_active_and_404(): void
    {
        Partner::create(['name' => 'Ente Visible', 'slug' => 'ente-visible', 'description' => '<p>Descripción del ente</p>', 'is_active' => true]);
        Partner::create(['name' => 'Ente Oculto', 'slug' => 'ente-oculto', 'description' => 'x', 'is_active' => false]);

        $this->get('/entes/ente-visible')
            ->assertOk()
            ->assertSee('Ente Visible')
            ->assertSee('Descripción del ente');

        $this->get('/entes/ente-oculto')->assertNotFound();
        $this->get('/entes/no-existe')->assertNotFound();
    }

    public function test_news_category_page_filters_live_posts(): void
    {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'Cultura', 'slug' => 'cultura', 'is_active' => true]);
        $other = Category::create(['name' => 'Deporte', 'slug' => 'deporte', 'is_active' => true]);

        Post::create([
            'title' => 'Noticia de Cultura',
            'slug' => 'noticia-cultura',
            'content' => '<p>cuerpo</p>',
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Post::create([
            'title' => 'Borrador oculto',
            'slug' => 'borrador-oculto',
            'content' => '<p>x</p>',
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
        Post::create([
            'title' => 'Otra categoría',
            'slug' => 'otra-categoria',
            'content' => '<p>x</p>',
            'category_id' => $other->id,
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        $this->get('/categoria/cultura')
            ->assertOk()
            ->assertSee('Noticia de Cultura')
            ->assertDontSee('Borrador oculto')
            ->assertDontSee('Otra categoría')
            ->assertSee('Cultura', false);

        $this->get('/categoria/no-existe')->assertNotFound();
    }
}
