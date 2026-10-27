<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_index_and_create_render(): void
    {
        Category::create(['name' => 'Raíz', 'slug' => 'raiz']);

        $this->actingAs($this->superadmin())->get('/admin/categorias')->assertOk();
        $this->actingAs($this->superadmin())->get('/admin/categorias/crear')->assertOk();
    }

    public function test_store_creates_category_with_slug_and_audit(): void
    {
        $parent = Category::create(['name' => 'Padre', 'slug' => 'padre']);

        $this->actingAs($this->superadmin())->post('/admin/categorias', [
            'name' => 'Categoría Nueva',
            'description' => 'Desc',
            'icon' => 'fa-star',
            'parent_id' => $parent->id,
            'order' => 5,
        ])->assertRedirect('/admin/categorias');

        $cat = Category::where('name', 'Categoría Nueva')->first();
        $this->assertNotNull($cat);
        $this->assertSame('categoria-nueva', $cat->slug);
        $this->assertSame($parent->id, $cat->parent_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'category.created', 'entity_id' => $cat->id]);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->actingAs($this->superadmin())->post('/admin/categorias', [
            'name' => '',
        ])->assertSessionHasErrors('name');

        $this->actingAs($this->superadmin())->post('/admin/categorias', [
            'name' => 'X',
            'parent_id' => 999,
            'order' => -1,
        ])->assertSessionHasErrors(['parent_id', 'order']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_edit_update_renames_slug_and_toggles_active(): void
    {
        $cat = Category::create(['name' => 'Viejo', 'slug' => 'viejo']);

        $this->actingAs($this->superadmin())->get("/admin/categorias/{$cat->id}/editar")->assertOk();

        $this->actingAs($this->superadmin())->put("/admin/categorias/{$cat->id}", [
            'name' => 'Nuevo Nombre',
            'order' => 2,
        ])->assertRedirect('/admin/categorias');

        $cat->refresh();
        $this->assertSame('nuevo-nombre', $cat->slug);
        $this->assertFalse($cat->is_active);

        $this->actingAs($this->superadmin())->put("/admin/categorias/{$cat->id}", [
            'name' => 'Nuevo Nombre',
            'is_active' => '1',
        ]);
        $this->assertTrue($cat->refresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'category.updated', 'entity_id' => $cat->id]);
    }

    public function test_destroy_deletes_empty_category(): void
    {
        $cat = Category::create(['name' => 'Borrar', 'slug' => 'borrar']);

        $this->actingAs($this->superadmin())->delete("/admin/categorias/{$cat->id}")
            ->assertRedirect('/admin/categorias');

        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'category.deleted', 'entity_id' => $cat->id]);
    }

    public function test_destroy_refuses_category_with_posts(): void
    {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'ConPosts', 'slug' => 'conposts']);
        Post::create([
            'title' => 'Noticia',
            'slug' => 'noticia-cat',
            'content' => '<p>x</p>',
            'category_id' => $cat->id,
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->superadmin())->delete("/admin/categorias/{$cat->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $cat->id]);
    }

    public function test_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/admin/categorias')->assertOk();
        $this->actingAs($editor)->get('/admin/categorias')->assertForbidden();
        $this->actingAs($editor)->post('/admin/categorias', ['name' => 'X'])->assertForbidden();
    }
}
