<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Post;
use App\Models\User;
use App\Support\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_home_queries_are_cached(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Post cache',
            'slug' => 'post-cache',
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'is_featured' => true,
        ]);

        $this->assertFalse(Cache::has(CacheKeys::HOME_FEATURED));

        $this->get('/')->assertOk();

        $this->assertTrue(Cache::has(CacheKeys::HOME_FEATURED));
        $this->assertTrue(Cache::has(CacheKeys::HOME_LATEST));
        $this->assertTrue(Cache::has(CacheKeys::HOME_CATEGORIES));
        $this->assertTrue(Cache::has(CacheKeys::HOME_MOST_READ));
    }

    public function test_creating_a_post_flushes_home_cache(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->get('/')->assertOk();
        $this->assertTrue(Cache::has(CacheKeys::HOME_FEATURED));

        Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Nuevo post',
            'slug' => 'nuevo-post',
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ]);

        $this->assertFalse(Cache::has(CacheKeys::HOME_FEATURED));
    }

    public function test_creating_a_partner_flushes_entes_cache(): void
    {
        $this->get(route('entes.index'))->assertOk();
        $this->assertTrue(Cache::has(CacheKeys::ENTES_INDEX));

        Partner::create([
            'name' => 'Nuevo Ente',
            'slug' => 'nuevo-ente',
            'is_active' => true,
        ]);

        $this->assertFalse(Cache::has(CacheKeys::ENTES_INDEX));
    }
}
