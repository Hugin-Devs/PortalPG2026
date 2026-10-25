<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPartnerCrudTest extends TestCase
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

    public function test_index_and_create_render_for_superadmin(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/entes')->assertOk();
        $this->actingAs($this->superadmin())->get('/admin/entes/crear')->assertOk();
    }

    public function test_store_creates_partner_with_unique_slug_and_audit(): void
    {
        $this->actingAs($this->superadmin())->post('/admin/entes', [
            'name' => 'Ente de Prueba',
            'description' => 'Descripción',
            'url' => 'https://example.gob.ve',
            'order' => 3,
        ])->assertRedirect('/admin/entes');

        $partner = Partner::where('name', 'Ente de Prueba')->first();
        $this->assertNotNull($partner);
        $this->assertSame('ente-de-prueba', $partner->slug);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'partner.created',
            'entity_type' => Partner::class,
            'entity_id' => $partner->id,
        ]);
    }

    public function test_store_duplicate_name_gets_unique_slug(): void
    {
        Partner::create(['name' => 'Ente Unico', 'slug' => 'ente-unico']);

        $this->actingAs($this->superadmin())->post('/admin/entes', [
            'name' => 'Ente Unico',
        ])->assertRedirect('/admin/entes');

        $this->assertDatabaseHas('partners', ['name' => 'Ente Unico', 'slug' => 'ente-unico-2']);
    }

    public function test_store_rejects_missing_name(): void
    {
        $this->actingAs($this->superadmin())->from('/admin/entes/crear')
            ->post('/admin/entes', ['url' => 'https://example.gob.ve'])
            ->assertRedirect('/admin/entes/crear')
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('partners', 0);
    }

    public function test_store_rejects_invalid_url(): void
    {
        $this->actingAs($this->superadmin())->post('/admin/entes', [
            'name' => 'Ente URL',
            'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('partners', 0);
    }

    public function test_store_with_logo_uploads_file_and_sidecar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin())->post('/admin/entes', [
            'name' => 'Ente con Logo',
            'logo' => UploadedFile::fake()->image('logo.png', 4, 4),
        ])->assertRedirect('/admin/entes');

        $partner = Partner::where('name', 'Ente con Logo')->first();
        $this->assertNotNull($partner);
        $this->assertStringStartsWith('partners/', $partner->logo);
        Storage::disk('public')->assertExists($partner->logo);
        Storage::disk('public')->assertExists(preg_replace('/\.png$/', '.webp', $partner->logo));
    }

    public function test_update_renames_slug_and_toggles_active(): void
    {
        $partner = Partner::create(['name' => 'Nombre Viejo', 'slug' => 'nombre-viejo']);

        $this->actingAs($this->superadmin())->put("/admin/entes/{$partner->id}", [
            'name' => 'Nombre Nuevo',
            'order' => 1,
        ])->assertRedirect('/admin/entes');

        $partner->refresh();
        $this->assertSame('nombre-nuevo', $partner->slug);
        $this->assertFalse($partner->is_active);

        $this->actingAs($this->superadmin())->put("/admin/entes/{$partner->id}", [
            'name' => 'Nombre Nuevo',
            'is_active' => '1',
        ]);
        $this->assertTrue($partner->refresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'partner.updated', 'entity_id' => $partner->id]);
    }

    public function test_update_remove_logo_clears_file_and_attribute(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('partners/old.png', 'x');
        $partner = Partner::create(['name' => 'Con Logo', 'slug' => 'con-logo', 'logo' => 'partners/old.png']);

        $this->actingAs($this->superadmin())->put("/admin/entes/{$partner->id}", [
            'name' => 'Con Logo',
            'remove_logo' => '1',
        ])->assertRedirect('/admin/entes');

        $this->assertNull($partner->refresh()->logo);
        Storage::disk('public')->assertMissing('partners/old.png');
    }

    public function test_update_with_new_logo_replaces_and_deletes_old(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('partners/old.png', 'x');
        $partner = Partner::create(['name' => 'Cambio Logo', 'slug' => 'cambio-logo', 'logo' => 'partners/old.png']);

        $this->actingAs($this->superadmin())->put("/admin/entes/{$partner->id}", [
            'name' => 'Cambio Logo',
            'logo' => UploadedFile::fake()->image('nuevo.png', 4, 4),
        ])->assertRedirect('/admin/entes');

        $new = $partner->refresh()->logo;
        $this->assertStringStartsWith('partners/', $new);
        $this->assertNotSame('partners/old.png', $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing('partners/old.png');
    }

    public function test_destroy_removes_partner_logo_and_logs_audit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('partners/borrar.png', 'x');
        $partner = Partner::create(['name' => 'Borrar', 'slug' => 'borrar', 'logo' => 'partners/borrar.png']);

        $this->actingAs($this->superadmin())->delete("/admin/entes/{$partner->id}")
            ->assertRedirect('/admin/entes');

        $this->assertDatabaseMissing('partners', ['id' => $partner->id]);
        Storage::disk('public')->assertMissing('partners/borrar.png');
        $this->assertDatabaseHas('activity_logs', ['action' => 'partner.deleted', 'entity_id' => $partner->id]);
    }

    public function test_editor_cannot_access_partner_admin(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/entes')->assertForbidden();
        $this->actingAs($editor)->post('/admin/entes', ['name' => 'X'])->assertForbidden();
    }

    public function test_admin_can_view_but_not_create_edit_or_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Partner::create(['name' => 'Ente', 'slug' => 'ente']);

        $this->actingAs($admin)->get('/admin/entes')->assertOk();
        $this->actingAs($admin)->post('/admin/entes', ['name' => 'Nuevo'])->assertForbidden();
        $this->actingAs($admin)->put("/admin/entes/{$partner->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($admin)->delete("/admin/entes/{$partner->id}")->assertForbidden();
    }
}
