<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

/**
 * Layout del detalle de noticia (news-show): el modo "revista" (texto a la
 * izquierda, medio a la derecha) solo se activa cuando TODOS los medios son
 * verticales; cualquier landscape, cuadrado o embed de YouTube (16:9) mantiene
 * el layout clásico a todo ancho con caja de 560 px.
 */
class NewsShowLayoutTest extends TestCase
{
    use BuildsMediaFixtures;
    use RefreshDatabase;

    /** Columnas del medio en el layout revista (desktop a la derecha). */
    private const REVISTA_MEDIO = 'col-lg-7 order-1 order-lg-2';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_single_portrait_image_uses_the_magazine_layout(): void
    {
        $post = $this->crearPost();
        $this->agregarImagen($post, 'uploads/t/retrato.png', 400, 700);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="revista"', false)
            ->assertSee(self::REVISTA_MEDIO)
            ->assertSee('aspect-ratio: 400/700', false);
    }

    public function test_a_landscape_image_keeps_the_classic_layout(): void
    {
        $post = $this->crearPost();
        $this->agregarImagen($post, 'uploads/t/apaisada.png', 900, 400);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="clasico"', false)
            ->assertDontSee(self::REVISTA_MEDIO)
            ->assertSee('height: 560px;', false);
    }

    public function test_a_portrait_carousel_keeps_the_magazine_layout(): void
    {
        $post = $this->crearPost();
        $this->agregarImagen($post, 'uploads/t/retrato-1.png', 400, 700);
        $this->agregarImagen($post, 'uploads/t/retrato-2.png', 450, 800);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="revista"', false)
            ->assertSee('id="newsGallery"', false)
            ->assertSee('aspect-ratio: 400/700', false);
    }

    public function test_a_single_landscape_element_in_the_gallery_forces_the_classic_layout(): void
    {
        $post = $this->crearPost();
        $this->agregarImagen($post, 'uploads/t/retrato.png', 400, 700);
        $this->agregarImagen($post, 'uploads/t/panorama.png', 1200, 300);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="clasico"', false)
            ->assertDontSee(self::REVISTA_MEDIO);
    }

    public function test_youtube_embeds_count_as_landscape(): void
    {
        $post = $this->crearPost();
        $this->agregarImagen($post, 'uploads/t/retrato.png', 400, 700);

        $video = Video::create([
            'title' => 'Canal GDC',
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        PostMedia::create([
            'post_id' => $post->id,
            'type' => 'video',
            'video_id' => $video->id,
            'position' => 1,
        ]);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="clasico"', false)
            ->assertDontSee(self::REVISTA_MEDIO);
    }

    public function test_a_portrait_mp4_activates_the_magazine_layout(): void
    {
        $post = $this->crearPost();
        Storage::disk('public')->put('videos/reel.mp4', $this->mp4(1080, 1920));

        $video = Video::create([
            'title' => 'Reel vertical',
            'type' => 'file',
            'path' => 'videos/reel.mp4',
        ]);
        PostMedia::create([
            'post_id' => $post->id,
            'type' => 'video',
            'video_id' => $video->id,
            'position' => 0,
        ]);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="revista"', false)
            ->assertSee('aspect-ratio: 1080/1920', false);
    }

    public function test_a_landscape_mp4_keeps_the_classic_layout(): void
    {
        $post = $this->crearPost();
        Storage::disk('public')->put('videos/clip.mp4', $this->mp4(1920, 1080));

        $video = Video::create([
            'title' => 'Clip apaisado',
            'type' => 'file',
            'path' => 'videos/clip.mp4',
        ]);
        PostMedia::create([
            'post_id' => $post->id,
            'type' => 'video',
            'video_id' => $video->id,
            'position' => 0,
        ]);

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="clasico"', false)
            ->assertSee('height: 560px;', false);
    }

    public function test_a_post_without_media_keeps_the_classic_layout(): void
    {
        $post = $this->crearPost();

        $this->get(route('news.show', $post->slug))
            ->assertOk()
            ->assertSee('data-layout="clasico"', false)
            ->assertDontSee('id="newsGallery"')
            ->assertDontSee(self::REVISTA_MEDIO);
    }

    private function crearPost(): Post
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        return Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Noticia de prueba',
            'slug' => 'noticia-de-prueba-'.uniqid(),
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ]);
    }

    private function agregarImagen(Post $post, string $path, int $width, int $height): void
    {
        Storage::disk('public')->put($path, $this->png($width, $height));

        PostMedia::create([
            'post_id' => $post->id,
            'type' => 'image',
            'path' => $path,
            'position' => $post->postMedia()->count(),
        ]);
    }
}
