<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NewsOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->category = Category::factory()->create();
        $this->author = User::factory()->create(['role' => 'superadmin']);
    }

    private function makePublishedPost(string $title, string $publishedAt): Post
    {
        static $i = 0;
        $i++;

        return Post::create([
            'title' => $title,
            'slug' => 'orden-'.$i.'-'.uniqid(),
            'excerpt' => $title,
            'content' => '<p>Contenido de '.$title.'</p>',
            'category_id' => $this->category->id,
            'user_id' => $this->author->id,
            'status' => 'published',
            'published_at' => $publishedAt,
        ]);
    }

    public function test_news_index_orders_by_publish_date_not_creation_date(): void
    {
        $reciente = $this->makePublishedPost('Noticia reciente publicada ayer', now()->subDay());
        $antigua = $this->makePublishedPost('Noticia antigua de redes sociales', now()->subDays(60));

        $response = $this->get('/noticias')->assertOk();

        $html = $response->getContent();
        $posReciente = strpos($html, $reciente->title);
        $posAntigua = strpos($html, $antigua->title);

        $this->assertNotFalse($posReciente, 'La noticia reciente debe verse en /noticias');
        $this->assertNotFalse($posAntigua, 'La noticia antigua debe verse en /noticias');
        $this->assertLessThan(
            $posAntigua,
            $posReciente,
            'Debe ordenarse por published_at: la publicada ayer antes que la de hace 60 días'
        );
    }

    public function test_home_orders_latest_section_by_publish_date(): void
    {
        $reciente = $this->makePublishedPost('Titular reciente del portal', now()->subDay());
        $antigua = $this->makePublishedPost('Titular antiguo migrado', now()->subDays(60));

        Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();

        $posReciente = strpos($html, $reciente->title);
        $posAntigua = strpos($html, $antigua->title);

        $this->assertNotFalse($posReciente, 'La noticia reciente debe verse en el home');
        $this->assertNotFalse($posAntigua, 'La noticia antigua debe verse en el home');
        $this->assertLessThan(
            $posAntigua,
            $posReciente,
            'La sección de últimas noticias del home debe ordenarse por published_at'
        );
    }

    public function test_admin_list_shows_original_publish_date_for_migrated_posts(): void
    {
        $migrada = $this->makePublishedPost('Noticia migrada con fecha original', now()->subDays(60));

        $this->actingAs($this->author)
            ->get('/admin/noticias')
            ->assertOk()
            ->assertSee($migrada->published_at->format('d/m/Y'));
    }
}
