<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Huecos residuales de cobertura: Media::destroy, UserController (edit,
 * guards de auto-desactivación/auto-borrado/borrado con noticias, password
 * en update), RrssController::index, vistas create/edit de noticias, ramas de
 * featured_image en store/update, destroy de noticia y TrustHosts::hosts().
 */
class AdminMiscCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_media_destroy_removes_file_sidecar_and_audits(): void
    {
        Storage::disk('public')->put('uploads/2026/10/borrar.png', 'x');
        $media = Media::create([
            'filename' => 'borrar.png',
            'path' => 'uploads/2026/10/borrar.png',
            'original_name' => 'borrar.png',
            'mime_type' => 'image/png',
            'size' => 1,
            'user_id' => $this->superadmin()->id,
        ]);

        $this->actingAs($this->superadmin())->delete("/admin/medios/{$media->id}")
            ->assertRedirect('/admin/medios');

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing('uploads/2026/10/borrar.png');
        $this->assertDatabaseHas('activity_logs', ['action' => 'media.deleted', 'entity_id' => $media->id]);
    }

    public function test_user_edit_create_render_and_password_update(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/admin/usuarios/crear')->assertOk();
        $this->actingAs($admin)->get("/admin/usuarios/{$target->id}/editar")->assertOk();

        $this->actingAs($admin)->put("/admin/usuarios/{$target->id}", [
            'name' => 'Editor Actualizado',
            'email' => $target->email,
            'role' => 'editor',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])->assertRedirect('/admin/usuarios');

        $target->refresh();
        $this->assertSame('Editor Actualizado', $target->name);
        $this->assertTrue(Hash::check('nueva-clave-123', $target->password));
    }

    public function test_user_cannot_deactivate_or_delete_self(): void
    {
        $me = $this->superadmin();

        $this->actingAs($me)->put("/admin/usuarios/{$me->id}", [
            'name' => 'Yo', 'email' => $me->email, 'role' => 'superadmin',
        ])->assertSessionHas('error');
        $this->assertTrue($me->refresh()->is_active);

        $this->actingAs($me)->delete("/admin/usuarios/{$me->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $me->id]);
    }

    public function test_user_with_posts_cannot_be_deleted(): void
    {
        $admin = $this->superadmin();
        $author = User::factory()->create(['role' => 'editor']);
        Post::create([
            'title' => 'Del autor', 'slug' => 'del-autor', 'content' => '<p>x</p>',
            'category_id' => Category::create(['name' => 'C', 'slug' => 'c'])->id,
            'user_id' => $author->id, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete("/admin/usuarios/{$author->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $author->id]);
    }

    public function test_rrss_index_renders_settings(): void
    {
        Setting::create(['key' => 'social_facebook', 'value' => "https://facebook.com/a\nhttps://facebook.com/b"]);

        $this->actingAs($this->superadmin())->get('/admin/rrss')
            ->assertOk()
            ->assertSee('https://facebook.com/a')
            ->assertSee('https://facebook.com/b');
    }

    public function test_news_create_edit_views_and_featured_image_branches(): void
    {
        $admin = $this->superadmin();
        // En PostgreSQL las secuencias no se reinician con RefreshDatabase:
        // usamos el id real (id fijo no es portable).
        $categoryId = Category::create(['name' => 'Cat', 'slug' => 'cat'])->id;

        $this->actingAs($admin)->get('/admin/noticias/crear')->assertOk();
        $this->actingAs($admin)->post('/admin/noticias', [
            'title' => 'Con destacada archivo',
            'category_id' => $categoryId,
            'excerpt' => 'Ex',
            'content' => '<p>cuerpo</p>',
            'status' => 'draft',
            'featured_image_file' => UploadedFile::fake()->image('porada.png', 4, 4),
        ])->assertRedirect('/admin/noticias');

        $post = Post::where('title', 'Con destacada archivo')->first();
        $this->assertNotNull($post);
        $this->assertNotNull($post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);

        $this->actingAs($admin)->get("/admin/noticias/{$post->id}/editar")->assertOk();

        // Quitar la destacada → null
        $this->actingAs($admin)->put("/admin/noticias/{$post->id}", [
            'title' => 'Con destacada archivo',
            'category_id' => $categoryId,
            'excerpt' => 'Ex',
            'content' => '<p>cuerpo</p>',
            'status' => 'draft',
            'remove_image' => '1',
        ])->assertRedirect('/admin/noticias');
        $this->assertNull($post->refresh()->featured_image);

        // Seleccionar desde la biblioteca (input oculto featured_image)
        $lib = Media::create(['filename' => 'lib.png', 'path' => 'uploads/2026/10/lib.png', 'original_name' => 'lib.png', 'mime_type' => 'image/png', 'size' => 1, 'user_id' => $admin->id]);
        $this->actingAs($admin)->put("/admin/noticias/{$post->id}", [
            'title' => 'Con destacada archivo',
            'category_id' => $categoryId,
            'excerpt' => 'Ex',
            'content' => '<p>cuerpo</p>',
            'status' => 'draft',
            'featured_image' => $lib->path,
        ])->assertRedirect('/admin/noticias');
        $this->assertSame($lib->path, $post->refresh()->featured_image);
    }

    public function test_news_destroy(): void
    {
        $admin = $this->superadmin();
        $post = Post::create([
            'title' => 'Bórrame', 'slug' => 'borrame', 'content' => '<p>x</p>',
            'category_id' => Category::create(['name' => 'C2', 'slug' => 'c2'])->id,
            'user_id' => User::factory()->create()->id, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete("/admin/noticias/{$post->id}")->assertRedirect('/admin/noticias');
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'news.deleted', 'entity_id' => $post->id]);
    }

    public function test_trust_hosts_merges_config_and_defaults(): void
    {
        $mw = new TrustHosts($this->app);

        // Los patrones deben salir anclados como regexps (contrato Symfony 7):
        // un globo tipo "*.gdc.gob.ve" se compila crudo y provoca 500 en prod.
        $base = $mw->hosts();
        $this->assertContains('^localhost$', $base);
        $this->assertContains('^127\.0\.0\.1$', $base);
        $this->assertContains('^(.+\.)?gdc\.gob\.ve$', $base);

        config(['security.trusted_hosts' => 'intra.gob.ve, otro.test, *.priv.gob.ve']);
        $hosts = (new TrustHosts($this->app))->hosts();
        $this->assertContains('^intra\.gob\.ve$', $hosts);
        $this->assertContains('^otro\.test$', $hosts);
        $this->assertContains('^(.+\.)?priv\.gob\.ve$', $hosts);

        config(['security.trusted_hosts' => ' , ']);
        $this->assertSame($base, (new TrustHosts($this->app))->hosts());
    }
}
