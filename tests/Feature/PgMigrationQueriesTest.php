<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gaceta;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contratos de comportamiento que la migración MariaDB -> PostgreSQL debe
 * preservar. La suite corre en SQLite (rápida) y contra PG real (gate de
 * dialecto); cada test fija el resultado esperado, no la sintaxis SQL.
 */
class PgMigrationQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makePublishedPost(string $title, string $slug): Post
    {
        $category = Category::factory()->create();
        $author = User::factory()->create(['role' => 'superadmin']);

        return Post::create([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $title,
            'content' => '<p>Contenido de '.$title.'</p>',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_roles_page_orders_permissions_by_group_then_module_then_action(): void
    {
        $groupOrder = ['Gestión Noticias', 'Gestión Portal', 'Configuración Portal'];
        $moduleOrder = [
            'Noticias', 'Medios', 'Videos', 'Gacetas',
            'Categorías', 'Entes Afiliados', 'Sobre Nosotros', 'Contacto',
            'Mensajes', 'Estructura Org.', 'Redes Sociales',
            'Identidad / Enlaces', 'Usuarios', 'Roles y Permisos',
        ];
        $actionOrder = ['view', 'create', 'upload', 'edit', 'manage', 'delete'];

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $response = $this->actingAs($superadmin)->get('/admin/roles');
        $response->assertOk();

        $permissions = $response->viewData('permissions');
        $this->assertNotEmpty($permissions);

        $rankOf = function (string $value, array $list): int {
            $index = array_search($value, $list, true);

            return $index === false ? PHP_INT_MAX : $index;
        };

        $previous = null;
        foreach ($permissions as $permission) {
            $action = substr($permission->slug, strrpos($permission->slug, '.') + 1);
            $current = [
                $rankOf($permission->group, $groupOrder),
                $rankOf($permission->module, $moduleOrder),
                $rankOf($action, $actionOrder),
            ];

            if ($previous !== null) {
                $this->assertLessThanOrEqual(
                    0,
                    $previous <=> $current,
                    "Permiso {$permission->slug} fuera de orden tras ".json_encode($previous)
                );
            }
            $previous = $current;
        }

        $slugs = $permissions->pluck('slug')->all();
        $this->assertLessThan(
            array_search('users.view', $slugs, true),
            array_search('news.view', $slugs, true),
            'Gestión Noticias debe ordenarse antes que Usuarios'
        );
    }

    public function test_gacetas_are_ordered_by_numero_numerically_within_same_date(): void
    {
        $path = 'gacetas/test-ordenacion.pdf';
        $base = [
            'path' => $path,
            'original_name' => 'test-ordenacion.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];

        Gaceta::create($base + ['numero' => '2', 'fecha' => '2026-05-01']);
        Gaceta::create($base + ['numero' => '10', 'fecha' => '2026-05-01']);
        Gaceta::create($base + ['numero' => '3', 'fecha' => '2026-05-01']);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/admin/gacetas');
        $response->assertOk();

        $this->assertSame(
            ['10', '3', '2'],
            $response->viewData('gacetas')->pluck('numero')->all(),
            'numero debe ordenarse como número (10 > 3 > 2), no como texto'
        );
    }

    public function test_dashboard_aggregates_published_drafts_and_active_users(): void
    {
        $this->makePublishedPost('Publicada uno', 'publicada-uno');
        $this->makePublishedPost('Publicada dos', 'publicada-dos');

        $category = Category::factory()->create();
        $author = User::factory()->create(['role' => 'superadmin']);
        Post::create([
            'title' => 'Borrador',
            'slug' => 'borrador-uno',
            'excerpt' => 'Borrador',
            'content' => '<p>Borrador</p>',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'draft',
        ]);

        User::factory()->create(['role' => 'editor', 'is_active' => true]);
        User::factory()->create(['role' => 'editor', 'is_active' => false]);

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $response = $this->actingAs($superadmin)->get('/admin');
        $response->assertOk();

        $postStats = $response->viewData('postStats');
        $this->assertSame(3, (int) $postStats->total);
        $this->assertSame(2, (int) $postStats->published, 'SUM de publicadas mal computado');
        $this->assertSame(1, (int) $postStats->drafts, 'SUM de borradores mal computado');

        $userStats = $response->viewData('userStats');
        // 6 usuarios creados: 2 autores publicadas + 1 autor borrador +
        // 2 editores + 1 superadmin final; solo 1 inactivo -> 5 activos.
        $this->assertSame(5, (int) $userStats->active, 'SUM de usuarios activos mal computado');
    }

    public function test_search_matches_titles_case_insensitively(): void
    {
        $this->makePublishedPost('Reforma Urbana Aprobada', 'reforma-urbana-aprobada');

        $response = $this->get('/buscar?q=REFORMA');
        $response->assertOk();
        $response->assertSee('Reforma Urbana Aprobada');
    }

    public function test_news_show_accumulates_daily_views_in_a_single_row(): void
    {
        $post = $this->makePublishedPost('Noticia con vistas', 'noticia-con-vistas');

        $this->get('/noticias/'.$post->slug)->assertOk();
        $this->get('/noticias/'.$post->slug)->assertOk();

        $this->assertSame(2, $post->fresh()->views);

        $row = DB::table('daily_views')
            ->where('day', now()->toDateString())
            ->where('post_id', $post->id)
            ->first();

        $this->assertNotNull($row, 'Debe existir una única fila diaria por post');
        $this->assertSame(2, (int) $row->count);
        $this->assertSame(
            1,
            DB::table('daily_views')->where('post_id', $post->id)->count(),
            'No deben crearse filas duplicadas para el mismo día'
        );
    }
}
