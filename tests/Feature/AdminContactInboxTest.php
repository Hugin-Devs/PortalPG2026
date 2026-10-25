<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactInboxTest extends TestCase
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

    private function contact(): Contact
    {
        return Contact::create([
            'name' => 'Ciudadano',
            'email' => 'ciudadano@example.com',
            'subject' => 'Consulta',
            'message' => 'Mensaje de prueba',
        ]);
    }

    public function test_edit_info_renders_settings_form(): void
    {
        $this->actingAs($this->superadmin())->get('/admin/contacto')->assertOk();
    }

    public function test_update_info_saves_all_fields_and_audits(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/contacto', [
            'address' => 'Av. Principal, Caracas',
            'phone' => '+58 212-0000000',
            'email' => 'info@gdc.gob.ve',
            'hours' => 'Lun a Vie 8-16',
            'map_url' => 'https://maps.example.gob.ve/ve',
        ])->assertRedirect('/admin/contacto')->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'contact_address', 'value' => 'Av. Principal, Caracas']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_phone', 'value' => '+58 212-0000000']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_email', 'value' => 'info@gdc.gob.ve']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_hours', 'value' => 'Lun a Vie 8-16']);
        $this->assertDatabaseHas('settings', ['key' => 'contact_map_url', 'value' => 'https://maps.example.gob.ve/ve']);
    }

    public function test_update_info_rejects_invalid_email_and_keeps_previous_value(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/contacto', [
            'email' => 'no-es-un-email',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('settings', ['key' => 'contact_email', 'value' => 'no-es-un-email']);
    }

    public function test_update_info_rejects_map_url_without_scheme(): void
    {
        $this->actingAs($this->superadmin())->put('/admin/contacto', [
            'map_url' => 'maps.example.gob.ve/ve',
        ])->assertSessionHasErrors('map_url');
    }

    public function test_messages_list_renders_and_shows_unread_contact(): void
    {
        $contact = $this->contact();

        $this->actingAs($this->superadmin())->get('/admin/contacto/mensajes')
            ->assertOk()
            ->assertSee('Consulta');
    }

    public function test_show_message_marks_read_once_and_audits(): void
    {
        $contact = $this->contact();
        $this->assertNull($contact->read_at);

        $this->actingAs($this->superadmin())->get("/admin/contacto/mensajes/{$contact->id}")->assertOk();

        $contact->refresh();
        $this->assertNotNull($contact->read_at);
        $readAt = $contact->read_at;

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'contact.viewed',
            'entity_type' => Contact::class,
            'entity_id' => $contact->id,
        ]);

        // Segunda visita: read_at no se pisa.
        $this->actingAs($this->superadmin())->get("/admin/contacto/mensajes/{$contact->id}")->assertOk();
        $this->assertTrue($contact->refresh()->read_at->equalTo($readAt));
    }

    public function test_destroy_message_removes_it_and_audits(): void
    {
        $contact = $this->contact();

        $this->actingAs($this->superadmin())->delete("/admin/contacto/mensajes/{$contact->id}")
            ->assertRedirect('/admin/contacto/mensajes');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'contact.deleted',
            'entity_id' => $contact->id,
        ]);
    }

    public function test_admin_has_contact_permissions_but_editor_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'editor']);
        $contact = $this->contact();

        $this->actingAs($admin)->get('/admin/contacto/mensajes')->assertOk();
        $this->actingAs($admin)->get("/admin/contacto/mensajes/{$contact->id}")->assertOk();

        $this->actingAs($editor)->get('/admin/contacto')->assertForbidden();
        $this->actingAs($editor)->get('/admin/contacto/mensajes')->assertForbidden();
        $this->actingAs($editor)->delete("/admin/contacto/mensajes/{$contact->id}")->assertForbidden();
    }
}
