<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->category = Category::factory()->create();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    private function makePost(array $attrs = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'title' => "Noticia de prueba $i",
            'slug' => "noticia-de-prueba-$i-".uniqid(),
            'excerpt' => 'Resumen de la noticia',
            'content' => '<p>Contenido</p>',
            'category_id' => $this->category->id,
            'user_id' => $this->superadmin->id,
            'status' => 'draft',
        ], $attrs));
    }

    public function test_index_shows_pending_and_published_badges(): void
    {
        $this->makePost(['title' => 'Borrador Pendiente']);
        $this->makePost(['title' => 'Noticia Publicada', 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->superadmin)->get('/admin/noticias')
            ->assertOk()
            ->assertSee('Pendiente de aprobación')
            ->assertSee('Publicado');
    }

    public function test_individual_approve_publishes_draft_and_logs_audit(): void
    {
        $post = $this->makePost(['title' => 'Borrador para Aprobar']);

        $this->actingAs($this->superadmin)
            ->post("/admin/noticias/{$post->id}/aprobar")
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertFalse($post->published_at->isFuture());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'news.approved',
            'entity_type' => Post::class,
            'entity_id' => $post->id,
        ]);
    }

    public function test_approving_non_draft_is_rejected(): void
    {
        $post = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->superadmin)
            ->post("/admin/noticias/{$post->id}/aprobar")
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('warning');

        $this->assertSame('published', $post->fresh()->status);
    }

    public function test_editor_cannot_approve_or_bulk_publish(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $post = $this->makePost();

        $this->actingAs($editor)->post("/admin/noticias/{$post->id}/aprobar")->assertForbidden();
        $this->actingAs($editor)->post('/admin/noticias/publicar', ['posts' => [$post->id]])->assertForbidden();
        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_bulk_publish_publishes_drafts_and_skips_others(): void
    {
        $draftA = $this->makePost(['title' => 'Borrador A']);
        $draftB = $this->makePost(['title' => 'Borrador B']);
        $live = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->superadmin)->post('/admin/noticias/publicar', [
            'posts' => [$draftA->id, $draftB->id, $live->id],
        ])->assertRedirect('/admin/noticias')->assertSessionHas('success');

        foreach ([$draftA, $draftB] as $draft) {
            $draft->refresh();
            $this->assertSame('published', $draft->status);
            $this->assertNotNull($draft->published_at);
            $this->assertFalse($draft->published_at->isFuture());
        }

        $this->assertSame('published', $live->fresh()->status);
        $this->assertEquals(now()->toDateTimeString(), $live->fresh()->published_at->toDateTimeString());
        $this->assertSame(2, \DB::table('activity_logs')->where('action', 'news.approved')->count());
    }

    public function test_bulk_publish_without_selection_shows_warning(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->superadmin)->post('/admin/noticias/publicar', [])
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('warning');

        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_bulk_publish_skips_already_published_with_warning(): void
    {
        $live = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->superadmin)->post('/admin/noticias/publicar', [
            'posts' => [$live->id],
        ])->assertRedirect('/admin/noticias')->assertSessionHas('warning');

        $this->assertSame(0, \DB::table('activity_logs')->where('action', 'news.approved')->count());
    }

    public function test_scheduled_post_is_hidden_until_published_at(): void
    {
        $future = now()->addHour();

        $this->actingAs($this->superadmin)->post('/admin/noticias', [
            'title' => 'Noticia Programada',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'published_at' => $future->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/noticias');

        $post = Post::where('title', 'Noticia Programada')->first();
        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertTrue($post->published_at->isFuture());

        $this->get('/noticias/'.$post->slug)->assertNotFound();
        $this->get('/noticias')->assertOk()->assertDontSee('Noticia Programada');
        $this->actingAs($this->superadmin)->get('/admin/noticias')
            ->assertOk()
            ->assertSee('Programada');

        $this->travelTo($future->addMinute());
        $this->get('/noticias/'.$post->slug)->assertOk();
        $this->get('/noticias')->assertOk()->assertSee('Noticia Programada');
        $this->travelBack();
    }

    public function test_editor_editing_scheduled_post_does_not_advance_it(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $future = now()->addHours(6);
        $post = $this->makePost([
            'title' => 'Programada Existente',
            'status' => 'published',
            'published_at' => $future,
        ]);

        $this->actingAs($editor)->put("/admin/noticias/{$post->id}", [
            'title' => 'Programada Existente Editada',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen editado',
            'content' => '<p>Contenido editado</p>',
        ])->assertRedirect('/admin/noticias');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertEquals($future->toDateTimeString(), $post->published_at->toDateTimeString());
        $this->assertTrue($post->published_at->isFuture());
    }

    public function test_editor_cannot_publish_or_schedule_even_with_tampered_fields(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $post = $this->makePost(['title' => 'Borrador del Editor']);

        $this->actingAs($editor)->put("/admin/noticias/{$post->id}", [
            'title' => 'Borrador del Editor',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/noticias')->assertSessionHas('warning');

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_approver_clearing_datetime_publishes_immediately(): void
    {
        $post = $this->makePost([
            'title' => 'Programada por Limpiar',
            'status' => 'published',
            'published_at' => now()->addDays(2),
        ]);

        $this->actingAs($this->superadmin)->put("/admin/noticias/{$post->id}", [
            'title' => 'Programada por Limpiar',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'published_at' => '',
        ])->assertRedirect('/admin/noticias');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertFalse($post->published_at->isFuture());
    }

    public function test_approver_scheduling_a_draft_from_edit(): void
    {
        $future = now()->addDays(1);
        $post = $this->makePost(['title' => 'Borrador a Programar']);

        $this->actingAs($this->superadmin)->put("/admin/noticias/{$post->id}", [
            'title' => 'Borrador a Programar',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
            'published_at' => $future->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/noticias');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue($post->published_at->isFuture());
        $this->get('/noticias/'.$post->slug)->assertNotFound();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'news.approved',
            'entity_id' => $post->id,
        ]);
    }

    public function test_store_published_without_datetime_sets_published_at_now(): void
    {
        $this->actingAs($this->superadmin)->post('/admin/noticias', [
            'title' => 'Publicada Inmediata',
            'category_id' => $this->category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ])->assertRedirect('/admin/noticias');

        $post = Post::where('title', 'Publicada Inmediata')->first();
        $this->assertNotNull($post);
        $this->assertNotNull($post->published_at);
        $this->assertFalse($post->published_at->isFuture());
    }
}
