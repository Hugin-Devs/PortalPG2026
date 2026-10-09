<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PiiEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_encrypts_legacy_plaintext_rows(): void
    {
        DB::table('contacts')->insert([
            'name' => 'Legacy Sin Cifrar',
            'email' => 'legacy@example.com',
            'subject' => 'Asunto legacy',
            'message' => 'Mensaje en texto plano.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'name' => 'Usuario Legacy',
            'email' => 'legacy-user@gdc.gob.ve',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('pii:encrypt', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run: 5 valor(es)')
            ->assertExitCode(0);

        // El dry-run no debe haber escrito
        $this->assertStringNotContainsString('eyJ', DB::table('contacts')->value('name'));

        $this->artisan('pii:encrypt')->assertExitCode(0);

        // Los valores raw deben estar cifrados (prefijo base64 de JSON)
        $this->assertStringStartsWith('eyJ', DB::table('contacts')->value('name'));
        $this->assertStringStartsWith('eyJ', DB::table('contacts')->value('email'));
        $this->assertStringStartsWith('eyJ', DB::table('contacts')->value('subject'));
        $this->assertStringStartsWith('eyJ', DB::table('contacts')->value('message'));
        $this->assertStringStartsWith('eyJ', DB::table('users')->value('name'));

        // Los modelos deben poder leerlos descifrados
        $contact = Contact::first();
        $this->assertSame('Legacy Sin Cifrar', $contact->name);
        $this->assertSame('legacy@example.com', $contact->email);
        $this->assertSame('Asunto legacy', $contact->subject);
        $this->assertSame('Mensaje en texto plano.', $contact->message);

        $user = User::where('email', 'legacy-user@gdc.gob.ve')->first();
        $this->assertSame('Usuario Legacy', $user->name);
    }

    public function test_command_is_idempotent_and_skips_already_encrypted_rows(): void
    {
        // Fila ya cifrada por el cast
        $contact = Contact::create([
            'name' => 'Ya Cifrada',
            'email' => 'cifrada@example.com',
            'subject' => 'Asunto',
            'message' => 'Mensaje.',
        ]);

        $this->artisan('pii:encrypt')->assertExitCode(0);

        $rawName = DB::table('contacts')->where('id', $contact->id)->value('name');
        $this->assertSame('Ya Cifrada', $contact->fresh()->name);
        $this->assertStringStartsWith('eyJ', $rawName);

        // Segunda pasada: no debe reportar trabajo pendiente
        $this->artisan('pii:encrypt', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run: 0 valor(es)')
            ->assertExitCode(0);

        $this->assertSame($rawName, DB::table('contacts')->where('id', $contact->id)->value('name'));
    }

    public function test_command_handles_empty_database(): void
    {
        $this->artisan('pii:encrypt')
            ->expectsOutputToContain('ya esta cifrado')
            ->assertExitCode(0);
    }
}
