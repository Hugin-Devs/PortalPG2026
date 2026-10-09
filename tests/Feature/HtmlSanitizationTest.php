<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_tags_and_event_handlers_are_stripped_from_post_content(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);

        $dirty = '<p onclick="alert(1)">Párrafo válido</p>'
            .'<script>alert("xss")</script>'
            .'<a href="https://example.com" onclick="evil()">enlace</a>'
            .'<img src="/storage/x.png" onerror="alert(2)" alt="x">';

        $response = $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'Noticia con HTML',
            'category_id' => 1, // no existe; forzamos validación
            'excerpt' => 'Resumen',
            'content' => $dirty,
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('category_id');

        // Creamos la categoría y reintentamos para validar sanitización real
        Category::factory()->create();

        $response = $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'Noticia con HTML',
            'category_id' => 1,
            'excerpt' => 'Resumen',
            'content' => $dirty,
            'status' => 'draft',
        ]);

        $response->assertRedirect('/admin/noticias');

        $post = Post::latest()->first();
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringNotContainsString('onclick', $post->content);
        $this->assertStringNotContainsString('onerror', $post->content);
        $this->assertStringContainsString('<p>Párrafo válido</p>', $post->content);
        $this->assertStringContainsString('enlace', $post->content);
        $this->assertStringContainsString('<img', $post->content);
    }

    public function test_script_tags_are_stripped_from_page_content(): void
    {
        $page = Page::create([
            'title' => 'Página de prueba',
            'slug' => 'pagina-de-prueba',
            'content' => '<p onclick="alert(1)">Válida</p><script>alert("xss")</script>',
            'is_active' => true,
        ]);

        $this->assertStringNotContainsString('<script>', $page->fresh()->content);
        $this->assertStringNotContainsString('onclick', $page->fresh()->content);
        $this->assertStringContainsString('<p>Válida</p>', $page->fresh()->content);
    }

    public function test_inline_data_uri_image_is_preserved(): void
    {
        $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $user = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'Imagen inline',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Texto con imagen:</p><img src="data:image/png;base64,'.$b64.'" alt="x">',
            'status' => 'draft',
        ])->assertRedirect('/admin/noticias');

        $this->assertStringContainsString('data:image/png;base64', Post::latest()->first()->content);
    }

    public function test_data_uri_in_anchor_is_removed_while_image_inline_is_kept(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);

        $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $content = '<p>Antes</p>'
            .'<a href="data:text/html,<script>alert(1)</script>">click</a>'
            .'<p>Después</p>'
            .'<img src="data:image/png;base64,'.$b64.'" alt="inline">';

        $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'Ancla data:',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Resumen',
            'content' => $content,
            'status' => 'draft',
        ])->assertRedirect('/admin/noticias');

        $stored = Post::latest()->first()->content;

        $this->assertStringNotContainsString('data:text/html', $stored);
        $this->assertStringNotContainsString('<a', $stored);
        $this->assertStringContainsString('Antes', $stored);
        $this->assertStringContainsString('Después', $stored);
        $this->assertStringContainsString('data:image/png;base64', $stored);
    }
}
