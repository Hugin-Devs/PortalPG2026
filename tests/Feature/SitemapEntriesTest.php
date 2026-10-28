<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gaceta;
use App\Models\Page;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * El sitemap incluye categorías, entes, gacetas y páginas activas
 * (closures de chunkById sin cubrir según el reporte de cobertura).
 */
class SitemapEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_categories_partners_gacetas_and_pages(): void
    {
        Cache::flush();

        Category::create(['name' => 'Sitemap Cat', 'slug' => 'sitemap-cat', 'is_active' => true]);
        Category::create(['name' => 'Cat Inactiva', 'slug' => 'cat-inactiva', 'is_active' => false]);
        Partner::create(['name' => 'Ente Sitemap', 'slug' => 'ente-sitemap', 'is_active' => true]);
        Partner::create(['name' => 'Ente Oculto', 'slug' => 'ente-oculto-map', 'is_active' => false]);
        Gaceta::create([
            'numero' => '77', 'fecha' => '2026-07-01', 'path' => 'gacetas/77.pdf',
            'original_name' => '77.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
            'file_hash' => str_repeat('e', 64),
        ]);
        Page::create(['title' => 'Map Page', 'slug' => 'map-page', 'content' => '<p>x</p>', 'is_active' => true]);
        Page::create(['title' => 'Map Borrador', 'slug' => 'map-borrador', 'content' => '<p>x</p>', 'is_active' => false]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('categoria/sitemap-cat', $xml);
        $this->assertStringNotContainsString('cat-inactiva', $xml);
        $this->assertStringContainsString('entes/ente-sitemap', $xml);
        $this->assertStringNotContainsString('ente-oculto-map', $xml);
        $this->assertStringContainsString('gacetas', $xml);
        $this->assertStringContainsString('pagina/map-page', $xml);
        $this->assertStringNotContainsString('map-borrador', $xml);
    }
}
