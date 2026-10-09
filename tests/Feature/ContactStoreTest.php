<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_renders(): void
    {
        $this->get('/contacto')->assertOk();
    }

    public function test_valid_submission_stores_the_message(): void
    {
        $data = [
            'name' => 'Usuario de Prueba',
            'email' => 'prueba@example.com',
            'subject' => 'Consulta E2E',
            'message' => 'Este es un mensaje de prueba del formulario de contacto.',
        ];

        $response = $this->post('/contacto', $data);

        $response->assertRedirect('/');
        $response->assertSessionHas('success');

        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertSame($data['name'], $contact->name);
        $this->assertSame($data['email'], $contact->email);
        $this->assertSame($data['subject'], $contact->subject);
        $this->assertSame($data['message'], $contact->message);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_invalid_submission_is_rejected_and_stores_nothing(): void
    {
        $response = $this->post('/contacto', [
            'email' => 'no-es-un-email',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_read_at_is_not_settable_from_the_form(): void
    {
        $this->post('/contacto', [
            'name' => 'Otro',
            'email' => 'otro@example.com',
            'subject' => 'Asunto',
            'message' => 'Mensaje.',
            'read_at' => '2026-01-01 00:00:00',
        ]);

        $row = Contact::first();
        $this->assertNotNull($row);
        $this->assertNull($row->read_at, 'read_at no debe ser manipulable desde el formulario');
    }
}
