<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorCannotPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_editor_cannot_publish_a_post(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $category = Category::factory()->create();

        $response = $this->actingAs($editor)->post('/admin/noticias', [
            'title' => 'Noticia del editor',
            'category_id' => $category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ]);

        $response->assertRedirect('/admin/noticias');
        $this->assertDatabaseHas('posts', [
            'title' => 'Noticia del editor',
            'status' => 'draft',
        ]);
    }
}
