<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_permission_denied_is_logged_on_403(): void
    {
        // El rol 'editor' no tiene users.view ni audit.view.
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/usuarios')->assertForbidden();

        $log = ActivityLog::where('action', 'permission.denied')->first();
        $this->assertNotNull($log);
        $this->assertSame($editor->id, $log->user_id);
        $this->assertSame('users.view', $log->metadata['permission']);
        $this->assertSame('admin/usuarios', $log->metadata['path']);
    }

    public function test_permission_denied_is_not_logged_for_unauthenticated(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/admin/login');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'permission.denied']);
    }

    public function test_role_update_logs_granted_and_removed_slugs(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        // Estado inicial del rol editor (del PermissionSeeder): news.view, news.create,
        // news.edit, media.view, media.upload. Usamos gacetas.view (no asignada) como
        // cambio real de otorgamiento y news.create como cambio real de retirada.
        $newsCreateId = Permission::where('slug', 'news.create')->value('id');
        $gacetasViewId = Permission::where('slug', 'gacetas.view')->value('id');

        $this->actingAs($superadmin)->post('/admin/roles', [
            'role' => 'editor',
            'permissions' => "{$newsCreateId},{$gacetasViewId}",
        ])->assertRedirect();

        $log = ActivityLog::where('action', 'role.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['gacetas.view'], $log->metadata['granted']);
        $this->assertSame(
            ['media.upload', 'media.view', 'news.edit', 'news.view'],
            $log->metadata['removed']
        );
        $this->assertSame(2, $log->metadata['permission_count']);
    }

    public function test_media_upload_logs_server_generated_filenames(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $file = new UploadedFile(
            base_path('tests/fixtures/pixel.png'),
            'original-name.png',
            'image/png',
            null,
            true
        );

        $this->actingAs($superadmin)->post('/admin/medios', [
            'files' => [$file],
        ])->assertRedirect('/admin/medios');

        $log = ActivityLog::where('action', 'media.uploaded')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertCount(1, $log->metadata['filenames']);
        $this->assertStringEndsWith('.png', $log->metadata['filenames'][0]);
        // El nombre registrado debe ser el generado por servidor, no el del cliente.
        $this->assertStringNotContainsString('original-name', $log->metadata['filenames'][0]);
    }

    public function test_user_validation_failure_logs_field_names_only(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)->from('/admin/usuarios/crear')->post('/admin/usuarios', [
            'name' => 'Valid',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'editor',
        ])->assertSessionHasErrors(['email', 'password']);

        $log = ActivityLog::where('action', 'user.validation_failed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertContains('email', $log->metadata['fields']);
        $this->assertContains('password', $log->metadata['fields']);
        // Nunca se registran los valores fallidos (contendrían PII/credenciales).
        $this->assertStringNotContainsString('not-an-email', json_encode($log->metadata));
        $this->assertStringNotContainsString('short', json_encode($log->metadata));
    }

    public function test_successful_user_creation_does_not_log_validation_failure(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)->post('/admin/usuarios', [
            'name' => 'Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'editor',
        ])->assertRedirect('/admin/usuarios');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'user.validation_failed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created']);
    }
}
