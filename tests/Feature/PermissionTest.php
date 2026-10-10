<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_editor_is_forbidden_from_user_management(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/usuarios')->assertForbidden();
    }

    public function test_admin_is_also_forbidden_from_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/usuarios')->assertForbidden();
    }

    public function test_superadmin_can_access_user_management(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)->get('/admin/usuarios')->assertOk();
    }

    public function test_editor_can_access_their_own_scope(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/noticias')->assertOk();
        $this->actingAs($editor)->get('/admin/medios')->assertOk();
    }

    public function test_editor_cannot_access_gacetas(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/gacetas')->assertForbidden();
    }

    public function test_has_permission_matches_the_canonical_reparto(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->assertTrue($superadmin->hasPermission('users.view'));
        $this->assertTrue($superadmin->hasPermission('settings.manage'));

        $this->assertTrue($admin->hasPermission('gacetas.view'));
        $this->assertTrue($admin->hasPermission('categories.view'));
        $this->assertTrue($admin->hasPermission('news.approve'));
        $this->assertFalse($admin->hasPermission('users.view'));
        $this->assertFalse($admin->hasPermission('videos.view'));

        $this->assertTrue($editor->hasPermission('news.view'));
        $this->assertTrue($editor->hasPermission('media.upload'));
        $this->assertFalse($editor->hasPermission('news.approve'));
        $this->assertFalse($editor->hasPermission('users.view'));
        $this->assertFalse($editor->hasPermission('gacetas.view'));
        $this->assertFalse($editor->hasPermission('categories.view'));
    }

    public function test_unauthenticated_request_to_protected_module_redirects_to_login(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/admin/login');
    }
}
