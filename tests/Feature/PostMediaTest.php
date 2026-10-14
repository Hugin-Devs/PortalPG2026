<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\Video;
use App\Services\Webp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (PostMedia::with('video')->get() as $media) {
            if ($media->path) {
                Storage::disk('public')->delete($media->path);
                Webp::delete($media->path);
            }
            if ($media->video?->path) {
                Storage::disk('public')->delete($media->video->path);
            }
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function news(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'title' => 'PM Test Noticia',
            'slug' => 'pm-test-noticia',
            'excerpt' => 'Extracto de prueba',
            'content' => '<p>Contenido de prueba</p>',
            'category_id' => Category::factory()->create()->id,
            'user_id' => $this->admin()->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_gallery_images_are_stored_dated_and_rendered_in_carousel(): void
    {
        $post = $this->news();
        $user = $this->admin();

        $response = $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'PM Test Galeria',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Extracto',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'featured_image_file' => UploadedFile::fake()->image('portada.jpg', 40, 40),
            'gallery_images' => [
                UploadedFile::fake()->image('g1.jpg', 40, 40),
                UploadedFile::fake()->image('g2.png', 40, 40),
            ],
        ]);

        $response->assertRedirect('/admin/noticias');

        $created = Post::where('slug', 'pm-test-galeria')->first();
        $this->assertNotNull($created);
        $this->assertSame('uploads/'.date('Y/m'), dirname($created->featured_image));

        $images = $created->postMedia()->where('type', 'image')->get();
        $this->assertCount(2, $images);
        foreach ($images as $image) {
            $this->assertSame('uploads/'.date('Y/m'), dirname($image->path));
            $this->assertTrue(Storage::disk('public')->exists($image->path));
        }

        $html = $this->get('/noticias/'.$created->slug)->assertOk()->getContent();
        $this->assertStringContainsString('id="newsGallery"', $html);
        $this->assertSame(3, substr_count($html, 'carousel-item'));
        $this->assertMatchesRegularExpression('/carousel-item active[^>]*>.*'.preg_quote(basename($created->featured_image), '/').'/s', $html);
    }

    public function test_carousel_orders_cover_first_then_gallery_videos(): void
    {
        $post = $this->news(['featured_image' => 'uploads/2026/01/portada.jpg']);
        $video = Video::create([
            'title' => 'PM Video',
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'user_id' => $this->admin()->id,
        ]);
        PostMedia::create(['post_id' => $post->id, 'type' => 'video', 'video_id' => $video->id, 'position' => 0]);
        PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/2026/01/galeria.jpg', 'position' => 1]);

        $html = $this->get('/noticias/'.$post->slug)->assertOk()->getContent();

        $this->assertStringContainsString('id="newsGallery"', $html);
        $this->assertSame(3, substr_count($html, 'carousel-item'));
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $html);
        $this->assertMatchesRegularExpression('/carousel-item active[^>]*>.*portada\.jpg/s', $html);
    }

    public function test_move_reorders_gallery_items(): void
    {
        $post = $this->news();
        $a = PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/2026/01/a.jpg', 'position' => 0]);
        $b = PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/2026/01/b.jpg', 'position' => 1]);

        $this->actingAs($this->admin())
            ->post("/admin/noticias/{$post->id}/media/{$a->id}/move?direction=down")
            ->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(0, $b->fresh()->position);
    }

    public function test_gallery_image_limit_is_enforced(): void
    {
        $post = $this->news();
        for ($i = 0; $i < 9; $i++) {
            PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => "uploads/2026/01/x{$i}.jpg", 'position' => $i]);
        }

        $response = $this->actingAs($this->admin())->put('/admin/noticias/'.$post->id, [
            'title' => $post->title,
            'category_id' => $post->category_id,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'gallery_images' => [
                UploadedFile::fake()->image('n1.jpg', 10, 10),
                UploadedFile::fake()->image('n2.jpg', 10, 10),
            ],
        ]);

        $response->assertSessionHasErrors(['gallery_images']);
        $this->assertStringContainsString(
            'máximo 10',
            $response->getSession()->get('errors')->first('gallery_images')
        );
        $this->assertSame(9, $post->postMedia()->where('type', 'image')->count());
    }

    public function test_video_limit_is_enforced(): void
    {
        $post = $this->news();
        $user = $this->admin();
        foreach (['v1', 'v2'] as $slug) {
            $video = Video::create(['title' => $slug, 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'user_id' => $user->id]);
            PostMedia::create(['post_id' => $post->id, 'type' => 'video', 'video_id' => $video->id, 'position' => 0]);
        }

        $response = $this->actingAs($user)->put('/admin/noticias/'.$post->id, [
            'title' => $post->title,
            'category_id' => $post->category_id,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'new_video_urls' => "https://youtu.be/dQw4w9WgXcQ\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ",
        ]);

        $response->assertSessionHasErrors(['new_video_urls']);
        $this->assertStringContainsString(
            'Máximo 3',
            $response->getSession()->get('errors')->first('new_video_urls')
        );
    }

    public function test_new_youtube_url_creates_video_module_record_and_links_it(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/noticias', [
            'title' => 'PM Test Video Nuevo',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Extracto',
            'content' => '<p>Contenido</p>',
            'status' => 'draft',
            'new_video_urls' => "https://www.youtube.com/watch?v=dQw4w9WgXcQ\nhttps://youtu.be/dQw4w9WgXcQ",
        ]);

        $response->assertRedirect('/admin/noticias');

        $post = Post::where('slug', 'pm-test-video-nuevo')->first();
        $videos = Video::where('title', 'like', 'PM Test Video Nuevo%')->get();
        $this->assertCount(2, $videos);
        $this->assertTrue($videos->every(fn (Video $v) => $v->type === 'youtube' && ! $v->is_featured));
        $this->assertSame(
            $videos->pluck('id')->sort()->values()->all(),
            $post->postMedia()->where('type', 'video')->pluck('video_id')->sort()->values()->all()
        );
        $this->assertSame('PM Test Video Nuevo (2)', $videos->sortBy('id')->last()->title);
    }

    public function test_new_video_file_is_stored_in_videos_folder(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/noticias', [
            'title' => 'PM Test Video File',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Extracto',
            'content' => '<p>Contenido</p>',
            'status' => 'draft',
            'new_video_files' => [UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4')],
        ]);

        $response->assertRedirect('/admin/noticias');

        $video = Video::where('title', 'PM Test Video File')->first();
        $this->assertNotNull($video);
        $this->assertSame('file', $video->type);
        $this->assertSame('videos', dirname($video->path));
        $this->assertTrue(Storage::disk('public')->exists($video->path));
    }

    public function test_invalid_youtube_url_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/noticias', [
            'title' => 'PM Test URL Mala',
            'category_id' => Category::factory()->create()->id,
            'excerpt' => 'Extracto',
            'content' => '<p>Contenido</p>',
            'status' => 'draft',
            'new_video_urls' => 'https://example.com/no-es-youtube',
        ]);

        $response->assertSessionHasErrors(['new_video_urls']);
        $this->assertNull(Post::where('slug', 'pm-test-url-mala')->first());
    }

    public function test_remove_media_requires_own_rows_and_keeps_files(): void
    {
        $post = $this->news();
        $own = PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/2026/01/own.jpg', 'position' => 0]);

        $other = $this->news(['slug' => 'pm-test-otra', 'title' => 'PM Test Otra']);
        $foreign = PostMedia::create(['post_id' => $other->id, 'type' => 'image', 'path' => 'uploads/2026/01/foreign.jpg', 'position' => 0]);

        $this->actingAs($this->admin())->put('/admin/noticias/'.$post->id, [
            'title' => $post->title,
            'category_id' => $post->category_id,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'remove_media' => [$foreign->id],
        ])->assertSessionHasErrors(['remove_media']);

        $this->assertNotNull($foreign->fresh());

        Storage::disk('public')->put('uploads/2026/01/own.jpg', 'x');

        $this->actingAs($this->admin())->put('/admin/noticias/'.$post->id, [
            'title' => $post->title,
            'category_id' => $post->category_id,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'remove_media' => [$own->id],
        ])->assertRedirect('/admin/noticias');

        $this->assertNull($own->fresh());
        $this->assertTrue(Storage::disk('public')->exists('uploads/2026/01/own.jpg'));
        Storage::disk('public')->delete('uploads/2026/01/own.jpg');
    }

    public function test_attach_existing_video_from_module(): void
    {
        $post = $this->news();
        $video = Video::create([
            'title' => 'PM Existente',
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'user_id' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())->put('/admin/noticias/'.$post->id, [
            'title' => $post->title,
            'category_id' => $post->category_id,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'attach_video' => [$video->id],
        ])->assertRedirect('/admin/noticias');

        $this->assertTrue(
            $post->postMedia()->where('type', 'video')->where('video_id', $video->id)->exists()
        );
        $this->assertSame(1, Video::count());
    }

    public function test_single_cover_keeps_static_image_without_carousel(): void
    {
        $post = $this->news(['featured_image' => 'uploads/2026/01/solo.jpg']);

        $html = $this->get('/noticias/'.$post->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('id="newsGallery"', $html);
        $this->assertStringContainsString('img-fluid rounded mb-4 w-100', $html);
    }
}
