<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ContactSettingSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeders sin ningún test previo (huecos de cobertura de la fase B):
 * PartnerSeeder (borrador vacío), ContactSettingSeeder y DatabaseSeeder.
 */
class SeederCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_seeder_is_idempotent_with_empty_draft(): void
    {
        $this->seed(PartnerSeeder::class);
        $this->assertSame(0, Partner::count(), 'El borrador viene vacío a la espera del contenido del cliente');

        $this->seed(PartnerSeeder::class);
        $this->assertSame(0, Partner::count());
    }

    public function test_contact_setting_seeder_creates_defaults_and_is_idempotent(): void
    {
        $this->seed(ContactSettingSeeder::class);

        $this->assertDatabaseHas('settings', ['key' => 'contact_address', 'value' => 'Av. Principal, Distrito Capital']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_phone']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'contacto@gdc.gob.ve']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_hours']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_map_url']);

        // No pisa valores ya editados
        Setting::where('key', 'contact_email')->update(['value' => 'editado@gdc.gob.ve']);
        $this->seed(ContactSettingSeeder::class);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'editado@gdc.gob.ve']);
    }

    public function test_database_seeder_runs_all_seeders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Category::count(), 'CategorySeeder');
        $this->assertGreaterThan(0, Post::count(), 'NoticiasPrecargadasSeeder');
        $this->assertGreaterThan(0, User::count(), 'UserSeeder');
        $this->assertGreaterThan(0, Permission::count(), 'PermissionSeeder');
        $this->assertDatabaseHas('settings', ['key' => 'contact_email']);
        $this->assertDatabaseHas('settings', ['key' => 'social_facebook']);
        $this->assertSame(0, Partner::count(), 'PartnerSeeder (borrador)');

        // Idempotencia básica del orquestador
        $before = [Post::count(), User::count(), Permission::count()];
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, [Post::count(), User::count(), Permission::count()]);
    }

    public function test_category_seeder_direct_run(): void
    {
        $this->seed(CategorySeeder::class);
        $count = Category::count();

        $this->seed(CategorySeeder::class);
        $this->assertSame($count, Category::count(), 'CategorySeeder idempotente');
    }
}
