<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFilterTest extends TestCase
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

    private function log(string $action, array $extra = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'action' => $action,
            'ip_address' => '127.0.0.1',
        ], $extra));
    }

    public function test_filter_by_action_returns_only_matching_rows(): void
    {
        // Marcadores sin subcadena "dos" (aparece en "Todos"/"login.success" del HTML).
        $this->log('login.success', ['description' => 'zzmarcadoruno']);
        $this->log('news.created', ['description' => 'zzmarcadordosx']);

        $response = $this->actingAs($this->superadmin())
            ->get('/admin/auditoria?action=login.success');

        $response->assertOk();
        $response->assertSee('zzmarcadoruno');
        $response->assertDontSee('zzmarcadordosx');
    }

    public function test_search_q_filters_by_description(): void
    {
        $this->log('news.created', ['description' => 'Noticia sobre infraestructura vial']);
        $this->log('news.deleted', ['description' => 'Otra cosa distinta']);

        $response = $this->actingAs($this->superadmin())
            ->get('/admin/auditoria?q=infraestructura');

        $response->assertOk();
        $response->assertSee('infraestructura vial');
        $response->assertDontSee('Otra cosa distinta');
    }

    public function test_filter_by_date_range(): void
    {
        $old = $this->log('login.success', ['created_at' => now()->subDays(10)]);
        $this->log('login.success', ['created_at' => now()]);

        $response = $this->actingAs($this->superadmin())
            ->get('/admin/auditoria?date_from='.now()->subDay()->toDateString());

        $response->assertOk();
        $this->assertSame(1, $response->viewData('logs')->total());
    }

    public function test_invalid_filter_params_are_rejected(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->from('/admin/auditoria')
            ->get('/admin/auditoria?user_id=abc');

        $response->assertSessionHasErrors('user_id');
    }

    public function test_filters_are_preserved_in_pagination_query_string(): void
    {
        // > 1 página (50 por página) para que se rendericen los enlaces de paginación.
        foreach (range(1, 51) as $i) {
            $this->log('login.success', ['description' => "fila {$i}"]);
        }

        $response = $this->actingAs($this->superadmin())
            ->get('/admin/auditoria?action=login.success');

        $response->assertOk();
        $this->assertSame(51, $response->viewData('logs')->total());
        $response->assertSee('action=login.success', false);
    }

    public function test_metadata_is_rendered_in_details(): void
    {
        $this->log('role.updated', [
            'metadata' => ['role' => 'editor', 'granted' => ['news.view']],
        ]);

        $response = $this->actingAs($this->superadmin())->get('/admin/auditoria');

        $response->assertOk();
        $response->assertSee('news.view');
    }

    public function test_admin_without_permission_gets_403(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/auditoria')->assertForbidden();
    }
}
