<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_superadmin_can_export_csv(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        ActivityLog::create([
            'action' => 'login.success',
            'description' => 'Login exitoso: usuario #1',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($superadmin)->get('/admin/auditoria/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $content = collect($response->streamedContent())->implode('');
        $this->assertStringContainsString('login.success', $content);
        $this->assertStringContainsString('Fecha', $content);
    }

    public function test_admin_without_export_permission_gets_403(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/auditoria/export')->assertForbidden();
    }

    public function test_csv_injection_is_neutralized(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        ActivityLog::create([
            'action' => 'news.created',
            'description' => '=SUM(A1:A9)+cmd|calc',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($superadmin)->get('/admin/auditoria/export');

        $content = collect($response->streamedContent())->implode('');
        $this->assertStringContainsString("'=SUM(A1:A9)+cmd|calc", $content);
        $this->assertStringNotContainsString(';=SUM', $content);
    }

    public function test_export_respects_filters(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        ActivityLog::create(['action' => 'login.success', 'ip_address' => '127.0.0.1']);
        ActivityLog::create(['action' => 'news.deleted', 'ip_address' => '127.0.0.1']);

        $response = $this->actingAs($superadmin)->get('/admin/auditoria/export?action=login.success');

        $content = collect($response->streamedContent())->implode('');
        $this->assertStringContainsString('login.success', $content);
        $this->assertStringNotContainsString('news.deleted', $content);
    }

    public function test_export_is_recorded_as_audit_event(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        ActivityLog::create(['action' => 'login.success', 'ip_address' => '127.0.0.1']);

        $this->actingAs($superadmin)->get('/admin/auditoria/export')->assertOk();

        $log = ActivityLog::where('action', 'audit.exported')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->metadata['count']);
        $this->assertSame($superadmin->id, $log->user_id);
    }
}
