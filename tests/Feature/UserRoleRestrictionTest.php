<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_cannot_assign_superadmin_role(): void
    {
        $admin = $this->createAdminWithUserPermissions();

        $response = $this->actingAs($admin)->from('/admin/usuarios/crear')->post('/admin/usuarios', [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@gdc.gob.ve',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superadmin',
        ]);

        $response->assertRedirect('/admin/usuarios/crear');
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@gdc.gob.ve']);
    }

    public function test_admin_cannot_upgrade_existing_user_to_superadmin(): void
    {
        $admin = $this->createAdminWithUserPermissions();
        $target = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($admin)->from("/admin/usuarios/{$target->id}/editar")
            ->put("/admin/usuarios/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'superadmin',
            ]);

        $response->assertRedirect("/admin/usuarios/{$target->id}/editar");
        $response->assertSessionHasErrors('role');
        $this->assertSame('editor', $target->fresh()->role);
    }

    public function test_superadmin_can_assign_superadmin_role(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->post('/admin/usuarios', [
            'name' => 'Nuevo superadmin',
            'email' => 'super2@gdc.gob.ve',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superadmin',
        ]);

        $response->assertRedirect('/admin/usuarios');
        $this->assertDatabaseHas('users', ['email' => 'super2@gdc.gob.ve', 'role' => 'superadmin']);
    }

    public function test_last_superadmin_cannot_be_demoted(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->from("/admin/usuarios/{$superadmin->id}/editar")
            ->put("/admin/usuarios/{$superadmin->id}", [
                'name' => $superadmin->name,
                'email' => $superadmin->email,
                'role' => 'admin',
                'is_active' => true,
            ]);

        $response->assertRedirect("/admin/usuarios/{$superadmin->id}/editar");
        $response->assertSessionHasErrors('role');
        $this->assertSame('superadmin', $superadmin->fresh()->role);
    }

    public function test_superadmin_can_be_demoted_when_another_superadmin_exists(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->put("/admin/usuarios/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/usuarios');
        $this->assertSame('admin', $superadmin->fresh()->role);
    }

    public function test_admin_cannot_change_password_of_superadmin_account(): void
    {
        $admin = $this->createAdminWithUserPermissions();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($admin)->put("/admin/usuarios/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'password' => 'hijacked123',
            'password_confirmation' => 'hijacked123',
            'role' => 'superadmin',
        ]);

        $response->assertForbidden();
        $this->assertFalse(\Hash::check('hijacked123', $superadmin->fresh()->password));
    }

    public function test_admin_cannot_deactivate_superadmin_account(): void
    {
        $admin = $this->createAdminWithUserPermissions();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($admin)->put("/admin/usuarios/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role' => 'superadmin',
        ]);

        $response->assertForbidden();
        $this->assertTrue($superadmin->fresh()->is_active);
    }

    public function test_admin_cannot_delete_superadmin_account(): void
    {
        $admin = $this->createAdminWithUserPermissions();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($admin)->delete("/admin/usuarios/{$superadmin->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
    }

    public function test_admin_cannot_modify_peer_admin_account(): void
    {
        $admin = $this->createAdminWithUserPermissions();
        $peer = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put("/admin/usuarios/{$peer->id}", [
            'name' => $peer->name,
            'email' => $peer->email,
            'role' => 'editor',
        ]);

        $response->assertForbidden();
        $this->assertSame('admin', $peer->fresh()->role);
    }

    public function test_admin_cannot_assign_admin_role_only_editor(): void
    {
        $admin = $this->createAdminWithUserPermissions();

        $response = $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Nuevo admin',
            'email' => 'nuevo-admin@gdc.gob.ve',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo-admin@gdc.gob.ve']);
    }

    public function test_admin_can_still_create_and_edit_editors(): void
    {
        $admin = $this->createAdminWithUserPermissions();

        $response = $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Nuevo editor',
            'email' => 'nuevo-editor@gdc.gob.ve',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'editor',
        ]);

        $response->assertRedirect('/admin/usuarios');
        $this->assertDatabaseHas('users', ['email' => 'nuevo-editor@gdc.gob.ve', 'role' => 'editor']);
    }

    private function createAdminWithUserPermissions(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $slugs = ['users.view', 'users.create', 'users.edit', 'users.delete'];
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $rows = $ids->map(fn ($id) => [
            'role' => 'admin',
            'permission_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();
        DB::table('role_permission')->insert($rows);

        return $admin;
    }
}
