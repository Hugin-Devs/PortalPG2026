<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $navFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        // Archivo real en public/ para la rama "ruta válida" de validateLink.
        $this->navFixture = 'e2e-nav-fixture.txt';
        file_put_contents(public_path($this->navFixture), 'fixture');
    }

    protected function tearDown(): void
    {
        @unlink(public_path($this->navFixture));
        parent::tearDown();
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_index_renders_with_superadmin(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/configuracion')->assertOk();
    }

    public function test_site_name_is_required(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => '',
            'nav_funcionarios_url' => '',
            'nav_jubilados_url' => '',
        ])->assertSessionHasErrors('site_name');
    }

    public function test_absolute_url_is_saved(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => 'https://intranet.example.gob.ve/',
            'nav_jubilados_url' => '',
        ])->assertRedirect('/admin/configuracion')->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'key' => 'nav_funcionarios_url',
            'value' => 'https://intranet.example.gob.ve/',
        ]);
    }

    public function test_malformed_absolute_url_is_rejected(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => 'http://',
            'nav_jubilados_url' => '',
        ])->assertSessionHasErrors('nav_funcionarios_url');

        $this->assertDatabaseMissing('settings', ['key' => 'nav_funcionarios_url', 'value' => 'http://']);
    }

    public function test_relative_path_without_slash_is_rejected(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => 'documentos/convocatoria.pdf',
            'nav_jubilados_url' => '',
        ])->assertSessionHasErrors('nav_funcionarios_url');
    }

    public function test_traversal_in_relative_path_is_rejected(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => '/images/../.env',
            'nav_jubilados_url' => '',
        ])->assertSessionHasErrors('nav_funcionarios_url');
    }

    public function test_nonexistent_public_file_is_rejected(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => '/documentos/NOEXISTE-E2E.pdf',
            'nav_jubilados_url' => '',
        ])->assertSessionHasErrors('nav_funcionarios_url');
    }

    public function test_existing_relative_path_is_saved(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => '/'.$this->navFixture,
            'nav_jubilados_url' => '',
        ])->assertRedirect('/admin/configuracion')->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'key' => 'nav_funcionarios_url',
            'value' => '/'.$this->navFixture,
        ]);
    }

    public function test_logo_upload_stores_file_and_sidecar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => '',
            'nav_jubilados_url' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 4, 4),
        ])->assertRedirect('/admin/configuracion')->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'site_logo', 'value' => 'settings/logo.png']);
        Storage::disk('public')->assertExists('settings/logo.png');
        Storage::disk('public')->assertExists('settings/logo.webp');
    }

    public function test_remove_logo_clears_setting_and_deletes_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', 'x');
        Setting::updateOrCreate(['key' => 'site_logo'], ['value' => 'settings/logo.png']);

        $this->actingAs($this->superadmin())->put('/admin/configuracion', [
            'site_name' => 'Portal GDC',
            'nav_funcionarios_url' => '',
            'nav_jubilados_url' => '',
            'remove_logo' => '1',
        ])->assertRedirect('/admin/configuracion');

        $this->assertDatabaseHas('settings', ['key' => 'site_logo', 'value' => '']);
        Storage::disk('public')->assertMissing('settings/logo.png');
    }

    public function test_settings_are_restricted_to_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)->get('/admin/configuracion')->assertForbidden();
        $this->actingAs($editor)->get('/admin/configuracion')->assertForbidden();
        $this->actingAs($editor)->put('/admin/configuracion', ['site_name' => 'X'])->assertForbidden();
    }
}
