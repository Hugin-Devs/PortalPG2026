<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAboutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_index_renders_existing_settings(): void
    {
        Setting::create(['key' => 'about_history', 'value' => 'Historia del GDC']);

        $this->actingAs(User::factory()->create(['role' => 'superadmin']))
            ->get('/admin/nosotros')
            ->assertOk()
            ->assertSee('Historia del GDC');
    }

    public function test_update_persists_all_fields_and_audits(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin']))
            ->put('/admin/nosotros', [
                'history' => 'Historia nueva',
                'mission' => 'Misión',
                'vision' => 'Visión',
            ])->assertRedirect('/admin/nosotros')->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'about_history', 'value' => 'Historia nueva']);
        $this->assertDatabaseHas('settings', ['key' => 'about_mission', 'value' => 'Misión']);
        $this->assertDatabaseHas('settings', ['key' => 'about_vision', 'value' => 'Visión']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'about.updated']);
    }

    public function test_update_allows_empty_fields(): void
    {
        Setting::create(['key' => 'about_history', 'value' => 'Previo']);

        $this->actingAs(User::factory()->create(['role' => 'superadmin']))
            ->put('/admin/nosotros', ['history' => '', 'mission' => '', 'vision' => ''])
            ->assertRedirect('/admin/nosotros');

        $this->assertDatabaseHas('settings', ['key' => 'about_history', 'value' => '']);
    }

    public function test_about_requires_about_permission(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $admin = User::factory()->create(['role' => 'admin']);

        // about.edit lo tienen superadmin y admin; editor no
        $this->actingAs($editor)->get('/admin/nosotros')->assertForbidden();
        $this->actingAs($editor)->put('/admin/nosotros', ['history' => 'x'])->assertForbidden();
        $this->actingAs($admin)->put('/admin/nosotros', ['history' => 'De admin'])->assertRedirect('/admin/nosotros');
        $this->assertDatabaseHas('settings', ['key' => 'about_history', 'value' => 'De admin']);
    }
}
