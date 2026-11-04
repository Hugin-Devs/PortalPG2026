<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exportación individual de mensajes de contacto a PDF (permiso contact.export,
 * solo superadmin por defecto como audit.export). El PDF se genera y se
 * descarga al momento (no se persiste) y la auditoría registra únicamente el
 * ID del mensaje, nunca PII.
 */
class AdminContactPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_superadmin_exports_a_message_as_pdf_with_the_institutional_header(): void
    {
        $contact = $this->contact();

        $response = $this->actingAs($this->user('superadmin'))
            ->get(route('admin.contact.message.pdf', $contact));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('mensaje-contacto-'.$contact->id.'-', (string) $response->headers->get('content-disposition'));

        $content = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        // El cintillo institucional (banner + logo, compuesto con GD) va embebido:
        // sin él el PDF no superaría este tamaño.
        $this->assertGreaterThan(50000, strlen($content), 'El PDF debe llevar el cintillo embebido');
    }

    public function test_export_is_audited_without_pii(): void
    {
        $contact = $this->contact();

        $this->actingAs($this->user('superadmin'))
            ->get(route('admin.contact.message.pdf', $contact))
            ->assertOk();

        $log = ActivityLog::where('action', 'contact.exported')->firstOrFail();
        $this->assertSame($contact->id, $log->entity_id);

        $haystack = $log->description.' '.json_encode($log->metadata);
        $this->assertStringNotContainsString($contact->email, $haystack, 'La auditoría no debe contener PII');
        $this->assertStringNotContainsString($contact->name, $haystack, 'La auditoría no debe contener PII');
        $this->assertStringNotContainsString($contact->subject, $haystack, 'La auditoría no debe contener PII');
    }

    public function test_admin_with_contact_view_but_without_export_gets_403(): void
    {
        $contact = $this->contact();

        // El rol admin tiene contact.view (puede leer el mensaje) pero no
        // contact.export: demuestra que la exportación usa el permiso nuevo.
        $this->actingAs($this->user('admin'))
            ->get(route('admin.contact.message.pdf', $contact))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $contact = $this->contact();

        $this->get(route('admin.contact.message.pdf', $contact))
            ->assertRedirect('/admin/login');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function contact(): Contact
    {
        return Contact::create([
            'name' => 'Ciudadana Ejemplo',
            'email' => 'ciudadana.ejemplo@example.com',
            'subject' => 'Solicitud de información',
            'message' => "Buenas tardes,\n\nSolicito información sobre el trámite de referencia.\n\nGracias.",
        ]);
    }
}
