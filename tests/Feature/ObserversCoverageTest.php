<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gaceta;
use App\Models\Page;
use App\Models\Video;
use App\Support\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Invalidación de caché por observers en saved y deleted (huecos de cobertura).
 */
class ObserversCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function prime(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::put($key, 'x', 60);
        }
    }

    private function assertForgotten(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->assertNull(Cache::get($key), "La clave {$key} debió invalidarse");
        }
    }

    public function test_category_observer_flushes_home_news_and_sitemap(): void
    {
        $this->prime(CacheKeys::HOME_FEATURED, CacheKeys::NEWS_CATEGORIES_NAV, CacheKeys::SITEMAP);

        $cat = Category::create(['name' => 'Obs', 'slug' => 'obs']);
        $this->assertForgotten(CacheKeys::HOME_FEATURED, CacheKeys::NEWS_CATEGORIES_NAV, CacheKeys::SITEMAP);

        $this->prime(CacheKeys::HOME_LATEST, CacheKeys::SITEMAP);
        $cat->delete();
        $this->assertForgotten(CacheKeys::HOME_LATEST, CacheKeys::SITEMAP);
    }

    public function test_video_observer_flushes_home_and_sitemap(): void
    {
        $this->prime(CacheKeys::HOME_VIDEOS, CacheKeys::SITEMAP);
        $video = Video::create(['title' => 'Obs', 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->assertForgotten(CacheKeys::HOME_VIDEOS, CacheKeys::SITEMAP);

        $this->prime(CacheKeys::HOME_VIDEOS);
        $video->delete();
        $this->assertForgotten(CacheKeys::HOME_VIDEOS);
    }

    public function test_gaceta_observer_flushes_gacetas_and_sitemap(): void
    {
        $this->prime(CacheKeys::GACETAS_INDEX, CacheKeys::SITEMAP);
        $g = Gaceta::create([
            'numero' => '9', 'fecha' => '2026-09-01', 'path' => 'gacetas/9.pdf',
            'original_name' => '9.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
            'file_hash' => str_repeat('d', 64),
        ]);
        $this->assertForgotten(CacheKeys::GACETAS_INDEX, CacheKeys::SITEMAP);

        $this->prime(CacheKeys::GACETAS_INDEX);
        $g->delete();
        $this->assertForgotten(CacheKeys::GACETAS_INDEX);
    }

    public function test_page_observer_flushes_sitemap(): void
    {
        $this->prime(CacheKeys::SITEMAP);
        $page = Page::create(['title' => 'P', 'slug' => 'p-obs', 'content' => '<p>x</p>', 'is_active' => true]);
        $this->assertForgotten(CacheKeys::SITEMAP);

        $this->prime(CacheKeys::SITEMAP);
        $page->delete();
        $this->assertForgotten(CacheKeys::SITEMAP);
    }
}
