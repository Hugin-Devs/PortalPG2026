<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchXssTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_query_is_escaped_in_output(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Post::create([
            'category_id' => $category->id,
            'user_id' => $user->id,
            'status' => 'published',
            'title' => 'Noticia de prueba',
            'slug' => 'noticia-de-prueba',
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
        ]);
        $payload = '<script>alert(1)</script>';

        $response = $this->get('/buscar?q='.urlencode($payload));

        $response->assertOk();
        $response->assertDontSee($payload, false);
        $response->assertSee(htmlentities($payload), false);
    }
}
