<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_has_meta_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:description"', false);
        $response->assertSee('<meta property="og:image"', false);
        $response->assertSee('<meta property="og:url"', false);
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('<meta property="og:locale" content="es_VE">', false);
    }

    public function test_post_detail_has_dynamic_meta_tags(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Título SEO de prueba',
            'slug' => 'titulo-seo-prueba',
            'excerpt' => 'Resumen de la noticia para SEO.',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ]);

        $response = $this->get(route('news.show', $post->slug));

        $response->assertOk();
        $response->assertSee('Título SEO de prueba - Portal GDC', false);
        $response->assertSee('Resumen de la noticia para SEO.', false);
        $response->assertSee('<meta property="og:type" content="article">', false);
        $response->assertSee(route('news.show', $post->slug), false);
    }
}
