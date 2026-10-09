<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_xml(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Noticia sitemap',
            'slug' => 'noticia-sitemap',
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee(route('home'), false);
        $response->assertSee(route('news.show', $post->slug), false);
    }
}
