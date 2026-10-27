<?php

namespace Tests\Feature;

use App\Models\OrgMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrgMemberControllerTest extends TestCase
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
        OrgMember::create(['name' => 'Miembro', 'position' => 'Cargo', 'order' => 1]);

        $this->actingAs($this->superadmin())->get('/admin/estructura')->assertOk()->assertSee('Miembro');
        $this->actingAs($this->superadmin())->get('/admin/estructura/crear')->assertOk();
    }

    public function test_store_edit_update_destroy_full_cycle(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/estructura', [
            'name' => 'Ana Pérez',
            'position' => 'Directora',
            'photo' => 'images/fotos/ana.png',
            'description' => 'Bio',
            'order' => 1,
        ])->assertRedirect('/admin/estructura');

        $m = OrgMember::where('name', 'Ana Pérez')->first();
        $this->assertNotNull($m);
        $this->assertDatabaseHas('activity_logs', ['action' => 'org_member.created', 'entity_id' => $m->id]);

        $this->actingAs($admin)->get("/admin/estructura/{$m->id}/editar")->assertOk();

        $this->actingAs($admin)->put("/admin/estructura/{$m->id}", [
            'name' => 'Ana Pérez',
            'position' => 'Directora General',
            'is_active' => '1',
        ])->assertRedirect('/admin/estructura');

        $m->refresh();
        $this->assertSame('Directora General', $m->position);
        $this->assertTrue($m->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'org_member.updated', 'entity_id' => $m->id]);

        $this->actingAs($admin)->delete("/admin/estructura/{$m->id}")->assertRedirect('/admin/estructura');
        $this->assertDatabaseMissing('org_members', ['id' => $m->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'org_member.deleted', 'entity_id' => $m->id]);
    }

    public function test_store_validation_rejects_bad_photo_and_missing_fields(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/estructura', ['name' => '', 'position' => ''])
            ->assertSessionHasErrors(['name', 'position']);

        $this->actingAs($admin)->post('/admin/estructura', [
            'name' => 'X', 'position' => 'Y', 'photo' => 'imagenes/x.png',
        ])->assertSessionHasErrors('photo');

        $this->actingAs($admin)->post('/admin/estructura', [
            'name' => 'X', 'position' => 'Y', 'photo' => 'images/../secret.png',
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('org_members', 0);
    }

    public function test_update_toggle_is_active_off(): void
    {
        $m = OrgMember::create(['name' => 'Activo', 'position' => 'Cargo', 'is_active' => true]);

        $this->actingAs($this->superadmin())->put("/admin/estructura/{$m->id}", [
            'name' => 'Activo', 'position' => 'Cargo',
        ])->assertRedirect();

        $this->assertFalse($m->refresh()->is_active);
    }

    public function test_authorization(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/estructura')->assertForbidden();
        $this->actingAs($editor)->post('/admin/estructura', ['name' => 'x', 'position' => 'y'])->assertForbidden();
    }
}
