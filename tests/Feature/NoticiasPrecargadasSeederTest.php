<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\NoticiasPrecargadasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoticiasPrecargadasSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CategorySeeder::class);
    }

    public function test_seeds_the_five_preloaded_news_with_dates_categories_and_gallery(): void
    {
        $this->seed(NoticiasPrecargadasSeeder::class);

        $esperadas = [
            'caracas-recibio-el-fuego-patrio-en-los-iv-juegos-deportivos-nacionales-comunales' => ['2026-10-13 22:00', 'deporte', 3],
            'trabajadores-de-caracas-marchan-a-la-asamblea-en-respaldo-a-la-mesa-de-dialogo' => ['2026-10-13 21:30', 'politica', 0],
            'vuelos-directos-caracas-roma-suscriben-memorando-con-ita-airways-para-marzo-de-2027' => ['2026-10-13 16:30', 'nacionales', 2],
            'punto-de-salud-del-campamento-ce-andres-bello-opera-en-servicio-continuo' => ['2026-10-13 16:30', 'comunal', 3],
            'liceo-abalos-acogio-taller-de-prevencion-de-embarazo-a-temprana-edad' => ['2026-10-13 13:00', 'educacion', 2],
        ];

        $this->assertDatabaseCount('posts', 5);

        foreach ($esperadas as $slug => [$fecha, $categoria, $galeria]) {
            $post = Post::where('slug', $slug)->firstOrFail();

            $this->assertSame('published', $post->status);
            $this->assertSame($fecha, $post->published_at->format('Y-m-d H:i'));
            $this->assertSame($categoria, $post->category->slug);
            $this->assertFalse($post->is_featured);
            $this->assertNotNull($post->featured_image);
            $this->assertStringStartsWith('uploads/2026/10/', $post->featured_image);
            Storage::disk('public')->assertExists($post->featured_image);
            $this->assertSame($galeria, $post->postMedia()->where('type', 'image')->count());
            $this->assertStringContainsString('<p>', $post->content);
            $this->assertStringNotContainsString('Fecha:', $post->content);
            $this->assertStringNotContainsString('Fech:', $post->content);
        }

        $this->assertSame(15, Media::count());
        $this->assertSame(10, PostMedia::where('type', 'image')->count());

        $noticia1 = Post::where('slug', 'caracas-recibio-el-fuego-patrio-en-los-iv-juegos-deportivos-nacionales-comunales')->firstOrFail();
        $this->assertStringContainsString('@nicolasmaduro', $noticia1->content);
        $this->assertStringContainsString('#13oct', $noticia1->content);
        $this->assertSame(
            [1, 2, 3],
            $noticia1->postMedia()->where('type', 'image')->orderBy('position')->pluck('position')->all()
        );
    }

    public function test_purges_old_placeholders_including_trashed_rows(): void
    {
        $user = User::factory()->create();

        $visible = Post::create([
            'title' => 'Placeholder visible',
            'slug' => 'noticia-destacada-1',
            'excerpt' => 'Placeholder de prueba',
            'content' => '<p>Placeholder de prueba</p>',
            'category_id' => Category::first()->id,
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        Post::create([
            'title' => 'Placeholder borrada',
            'slug' => 'noticia-destacada-2',
            'excerpt' => 'Placeholder de prueba',
            'content' => '<p>Placeholder de prueba</p>',
            'category_id' => Category::first()->id,
            'user_id' => $user->id,
            'status' => 'published',
        ])->delete();

        $this->assertSoftDeleted('posts', ['slug' => 'noticia-destacada-2']);

        $this->seed(NoticiasPrecargadasSeeder::class);

        $this->assertSame(0, Post::withTrashed()->where('slug', 'like', 'noticia-destacada-%')->count());
        $this->assertNull(Post::withTrashed()->find($visible->id), 'Las placeholders se purgan con borrado físico, incluso la visible');
        $this->assertDatabaseCount('posts', 5);
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $this->seed(NoticiasPrecargadasSeeder::class);
        $posts = Post::count();
        $media = Media::count();
        $galeria = PostMedia::count();

        $this->seed(NoticiasPrecargadasSeeder::class);

        $this->assertSame(5, $posts);
        $this->assertSame($posts, Post::count());
        $this->assertSame($media, Media::count());
        $this->assertSame($galeria, PostMedia::count());
    }
}
