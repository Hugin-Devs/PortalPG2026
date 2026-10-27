<?php

namespace Tests\Feature;

use App\Models\Gaceta;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGacetaControllerTest extends TestCase
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

    private function gaceta(array $attrs = []): Gaceta
    {
        Storage::disk('public')->put('gacetas/base.pdf', 'pdf');

        return Gaceta::create(array_merge([
            'numero' => '5',
            'fecha' => '2026-05-01',
            'path' => 'gacetas/base.pdf',
            'original_name' => 'base.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'file_hash' => str_repeat('c', 64),
        ], $attrs));
    }

    public function test_edit_renders(): void
    {
        $g = $this->gaceta();

        $this->actingAs($this->superadmin())->get("/admin/gacetas/{$g->id}/editar")
            ->assertOk()
            ->assertSee('5');
    }

    public function test_update_metadata_without_file_keeps_path(): void
    {
        $g = $this->gaceta();

        $this->actingAs($this->superadmin())->put("/admin/gacetas/{$g->id}", [
            'numero' => '7',
            'fecha' => '2026-07-01',
        ])->assertRedirect('/admin/gacetas');

        $g->refresh();
        $this->assertSame('7', $g->numero);
        $this->assertSame('gacetas/base.pdf', $g->path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'gaceta.updated', 'entity_id' => $g->id]);
    }

    public function test_update_replaces_file_and_rehashes(): void
    {
        $g = $this->gaceta();

        $this->actingAs($this->superadmin())->put("/admin/gacetas/{$g->id}", [
            'numero' => '5',
            'fecha' => '2026-05-01',
            'file' => UploadedFile::fake()->create('nuevo.pdf', 32, 'application/pdf'),
        ])->assertRedirect('/admin/gacetas');

        $g->refresh();
        $this->assertStringStartsWith('gacetas/', $g->path);
        $this->assertNotSame('gacetas/base.pdf', $g->path);
        Storage::disk('public')->assertExists($g->path);
        Storage::disk('public')->assertMissing('gacetas/base.pdf');
        $this->assertSame(64, strlen($g->file_hash));
    }

    public function test_update_validation(): void
    {
        $g = $this->gaceta();

        $this->actingAs($this->superadmin())->put("/admin/gacetas/{$g->id}", [
            'numero' => '', 'fecha' => 'no-fecha',
        ])->assertSessionHasErrors(['numero', 'fecha']);
    }

    public function test_destroy_removes_record_and_file(): void
    {
        $g = $this->gaceta();

        $this->actingAs($this->superadmin())->delete("/admin/gacetas/{$g->id}")->assertRedirect('/admin/gacetas');

        $this->assertDatabaseMissing('gacetas', ['id' => $g->id]);
        Storage::disk('public')->assertMissing('gacetas/base.pdf');
        $this->assertDatabaseHas('activity_logs', ['action' => 'gaceta.deleted', 'entity_id' => $g->id]);
    }

    public function test_authorization(): void
    {
        $g = $this->gaceta();
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/gacetas')->assertForbidden();
        $this->actingAs($editor)->delete("/admin/gacetas/{$g->id}")->assertForbidden();
    }
}
