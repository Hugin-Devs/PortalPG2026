<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\Video;
use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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

    /**
     * Invariante de config/cache.php `serializable_classes` (Fase 3 L13):
     * el store "file" (producción) hace unserialize() con la allowlist; si
     * falta una clase de las que la app cachea como objeto, la lectura se
     * corrompe en silencio (__PHP_Incomplete_Class). Este test pasa los
     * payloads reales por FileStore y exige roundtrip íntegro.
     */
    public function test_cache_serializable_classes_allowlist_roundtrips_cached_payloads(): void
    {
        $allowed = config('cache.serializable_classes');
        $this->assertIsArray($allowed, 'Falta cache.serializable_classes en config/cache.php');
        $this->assertContains(EloquentCollection::class, $allowed);
        $this->assertContains(Post::class, $allowed);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Post para allowlist',
            'slug' => 'post-allowlist',
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'is_featured' => true,
        ]);
        // Adjunto de vídeo: reproduce el payload real de HomeController
        // (Post::with('category', 'postMedia.video')) — si PostMedia falta en
        // la allowlist, la lectura devuelve __PHP_Incomplete_Class y la vista
        // revienta con "data_get(): ... incomplete object" (bug 2026-10-28).
        $video = Video::create([
            'title' => 'Video allowlist',
            'type' => 'file',
            'path' => 'videos/allowlist.mp4',
            'is_featured' => false,
            'user_id' => $user->id,
        ]);
        PostMedia::create([
            'post_id' => Post::where('slug', 'post-allowlist')->first()->id,
            'type' => 'video',
            'video_id' => $video->id,
            'position' => 0,
        ]);

        $file = Cache::store('file');
        $key = 'allowlist-roundtrip-test';

        // Payload idéntico al de HomeController (Collection de Post + relación
        // category eager + casts datetime → Carbon).
        $file->put($key, Post::with('category', 'postMedia.video')->get(), 60);
        $back = $file->get($key);

        $this->assertInstanceOf(EloquentCollection::class, $back);
        $this->assertCount(1, $back);
        $this->assertInstanceOf(Post::class, $back[0]);
        $this->assertSame('Post para allowlist', $back[0]->title);
        $this->assertInstanceOf(Category::class, $back[0]->category);
        $this->assertInstanceOf(Carbon::class, $back[0]->created_at);

        // La galería debe volver íntegra (no __PHP_Incomplete_Class).
        $this->assertInstanceOf(EloquentCollection::class, $back[0]->postMedia);
        $this->assertInstanceOf(PostMedia::class, $back[0]->postMedia->first());
        $this->assertInstanceOf(Video::class, $back[0]->postMedia->first()->video);
        $this->assertSame('videos/allowlist.mp4', $back[0]->postMedia->first()->video->path);

        // Payload idéntico al de GacetaController::index (cachea ->paginate()).
        $paginator = new LengthAwarePaginator($back, $back->count(), 12, 1);
        $file->put($key, $paginator, 60);
        $backPag = $file->get($key);

        $this->assertInstanceOf(LengthAwarePaginator::class, $backPag);
        $this->assertInstanceOf(Post::class, $backPag->items()[0]);
        $this->assertInstanceOf(Category::class, $backPag->items()[0]->category);

        $file->forget($key);
    }
}
