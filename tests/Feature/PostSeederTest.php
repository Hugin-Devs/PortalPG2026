<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_seeds_example_posts_with_expected_states(): void
    {
        $this->seed(PostSeeder::class);

        $plantilla = Post::where('slug', 'gdc-ejecuta-42-obras-viarias-en-las-parroquias-del-distrito')->firstOrFail();
        $this->assertSame('published', $plantilla->status);
        $this->assertTrue($plantilla->is_featured);
        $this->assertSame(145, (int) $plantilla->views);
        $this->assertNotNull($plantilla->published_at);
        $this->assertFalse($plantilla->published_at->isFuture());
        $this->assertNotNull($plantilla->category_id);
        $this->assertNotNull($plantilla->user_id);

        $migrada = Post::where('slug', 'festival-de-teatro-lleno-la-plaza-bolivar')->firstOrFail();
        $this->assertSame('published', $migrada->status);
        $this->assertFalse($migrada->published_at->isFuture());
        $this->assertTrue($migrada->published_at->lt($plantilla->published_at));

        $borrador = Post::where('slug', 'jornada-de-atencion-al-ciudadano-llegara-a-las-zonas-aledanas')->firstOrFail();
        $this->assertSame('draft', $borrador->status);
        $this->assertNull($borrador->published_at);

        $programada = Post::where('slug', 'convenio-de-cooperacion-fortalecera-los-programas-culturales-del-distrito')->firstOrFail();
        $this->assertSame('published', $programada->status);
        $this->assertTrue($programada->published_at->isFuture());

        $this->assertDatabaseCount('posts', 4);
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

        $this->seed(PostSeeder::class);

        $this->assertSame(0, Post::withTrashed()->where('slug', 'like', 'noticia-destacada-%')->count());
        $this->assertNull(Post::withTrashed()->find($visible->id), 'Las placeholders se purgan con borrado físico, incluso la visible');
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $this->seed(PostSeeder::class);
        $count = Post::count();

        $this->seed(PostSeeder::class);

        $this->assertSame(4, $count);
        $this->assertSame($count, Post::count());
    }
}
