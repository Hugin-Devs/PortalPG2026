<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_invalid_role_is_rejected_and_permissions_remain_intact(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $before = DB::table('role_permission')->where('role', 'editor')->count();

        $response = $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'no-existe',
            'permissions' => '1,2,3',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame($before, DB::table('role_permission')->where('role', 'editor')->count());
    }

    public function test_unknown_permission_ids_are_rejected_before_deleting(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $before = DB::table('role_permission')->where('role', 'editor')->pluck('permission_id')->sort()->values();

        $response = $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => '999999',
        ]);

        $response->assertSessionHasErrors('permissions');
        $after = DB::table('role_permission')->where('role', 'editor')->pluck('permission_id')->sort()->values();
        $this->assertTrue($before->all() === $after->all(), 'Los permisos previos deben conservarse intactos.');
    }

    public function test_superadmin_can_replace_editor_permissions(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $targetIds = Permission::whereIn('slug', ['news.view', 'news.create'])->pluck('id')->all();

        $response = $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => implode(',', $targetIds),
        ]);

        $response->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(
            $targetIds,
            DB::table('role_permission')->where('role', 'editor')->pluck('permission_id')->all()
        );
    }

    public function test_non_superadmin_cannot_modify_superadmin_role(): void
    {
        $admin = $this->createAdminWithRolesManage();

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'role' => 'superadmin',
            'permissions' => '',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_superadmin_cannot_grant_permissions_their_role_lacks(): void
    {
        $admin = $this->createAdminWithRolesManage();
        // settings.manage solo lo tiene superadmin por defecto.
        $forbidden = Permission::where('slug', 'settings.manage')->firstOrFail()->id;

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => (string) $forbidden,
        ]);

        $response->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('role_permission', [
            'role' => 'editor',
            'permission_id' => $forbidden,
        ]);
    }

    public function test_non_superadmin_can_grant_permissions_it_owns(): void
    {
        $admin = $this->createAdminWithRolesManage();
        $owned = Permission::where('slug', 'news.view')->firstOrFail()->id;

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => (string) $owned,
        ]);

        $response->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(
            [$owned],
            DB::table('role_permission')->where('role', 'editor')->pluck('permission_id')->all()
        );
    }

    public function test_permission_update_is_transactional_on_success(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $ids = Permission::whereIn('slug', ['news.view'])->pluck('id')->all();

        $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => implode(',', $ids),
        ])->assertSessionHas('success');

        $this->assertSame(1, DB::table('role_permission')->where('role', 'editor')->count());
    }

    private function createAdminWithRolesManage(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = Permission::where('slug', 'roles.manage')->firstOrFail()->id;
        DB::table('role_permission')->insert([
            'role' => 'admin',
            'permission_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }
}
