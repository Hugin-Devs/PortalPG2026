<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        RateLimiter::clear('login:127.0.0.1');
    }

    public function test_login_success_is_logged(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login.success',
            'user_id' => $user->id,
            'role' => 'superadmin',
        ]);
    }

    public function test_login_failure_is_logged(): void
    {
        $this->post('/admin/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login.failed',
            'user_id' => null,
        ]);
    }

    public function test_logout_is_logged(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($user)->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_password_change_is_logged(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'password' => Hash::make('old-password'),
            'password_changed_at' => null,
        ]);

        $this->actingAs($user)->post('/admin/cambiar-contrasena', [
            'current_password' => 'old-password',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect('/admin');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'password.changed',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_crud_is_logged(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        // Create
        $response = $this->actingAs($superadmin)->post('/admin/usuarios', [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo@gdc.gob.ve',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);
        $response->assertRedirect('/admin/usuarios');
        $user = User::where('email', 'nuevo@gdc.gob.ve')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.created',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);

        // Update
        $this->actingAs($superadmin)->put("/admin/usuarios/{$user->id}", [
            'name' => 'Admin Actualizado',
            'email' => 'nuevo@gdc.gob.ve',
            'role' => 'admin',
        ])->assertRedirect('/admin/usuarios');
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.updated',
            'entity_id' => $user->id,
        ]);

        // Delete
        $this->actingAs($superadmin)->delete("/admin/usuarios/{$user->id}")
            ->assertRedirect('/admin/usuarios');
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deleted',
            'entity_id' => $user->id,
        ]);
    }

    public function test_role_permission_update_is_logged(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $permissionId = \DB::table('permissions')->where('slug', 'news.view')->value('id');

        $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => (string) $permissionId,
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'role.updated',
            'description' => 'Permisos actualizados para rol: editor',
        ]);
    }

    public function test_news_publish_is_logged(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $category = Category::factory()->create();

        $this->actingAs($superadmin)->post('/admin/noticias', [
            'title' => 'Noticia de auditoría',
            'category_id' => $category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'status' => 'published',
        ])->assertRedirect('/admin/noticias');

        $post = Post::where('title', 'Noticia de auditoría')->first();
        $this->assertNotNull($post);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'news.created',
            'entity_type' => Post::class,
            'entity_id' => $post->id,
            'metadata' => json_encode(['status' => 'published']),
        ]);
    }

    public function test_contact_message_read_is_logged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = Contact::create([
            'name' => 'Ciudadano',
            'email' => 'ciudadano@example.com',
            'subject' => 'Consulta',
            'message' => 'Mensaje de prueba',
        ]);

        $this->actingAs($admin)->get("/admin/contacto/mensajes/{$contact->id}")->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'contact.viewed',
            'entity_type' => Contact::class,
            'entity_id' => $contact->id,
        ]);
    }

    public function test_audit_view_is_restricted_to_superadmin(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superadmin)->get('/admin/auditoria')->assertOk();
        $this->actingAs($admin)->get('/admin/auditoria')->assertForbidden();
    }
}
