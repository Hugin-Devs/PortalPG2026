<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\Latin9;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de la adaptación LATIN9 (docs/DOCUMENTACION.md §14.K): la BD de
 * producción es PostgreSQL ISO-8859-15 con client_encoding UTF8; la app
 * translitera texto antes de que cruce el cable.
 *
 * Las aserciones de comportamiento (Eloquent, HTTP) corren en ambos gates
 * (SQLite y PostgreSQL); las de codificación del servidor se saltan si no
 * hay PostgreSQL disponible.
 */
class Latin9EncodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function requirePgsql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL (gate de dialecto)');
        }
    }

    private function serverEncoding(): string
    {
        return strtolower((string) DB::selectOne('SHOW server_encoding')->server_encoding);
    }

    private function makePublishedPost(string $title): Post
    {
        $category = Category::factory()->create();
        $author = User::factory()->create(['role' => 'superadmin']);

        return Post::create([
            'title' => $title,
            'slug' => 'post-latin9-'.md5($title),
            'excerpt' => $title,
            'content' => '<p>'.$title.'</p>',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_connection_uses_utf8_client_encoding(): void
    {
        $this->requirePgsql();
        $client = strtolower((string) DB::selectOne('SHOW client_encoding')->client_encoding);

        $this->assertSame('utf8', $client, 'La conexión debe ir en UTF8 aunque la BD sea LATIN9');
    }

    public function test_eloquent_transliterates_special_chars_on_write(): void
    {
        $post = $this->makePublishedPost('Vuelos “directos” Caracas–Roma…');

        $post = $post->fresh();
        $this->assertSame('Vuelos "directos" Caracas-Roma...', $post->title);
        $this->assertTrue(Latin9::isSafe($post->title));
        $this->assertTrue(Latin9::isSafe($post->content));
    }

    public function test_emojis_are_stripped_from_model_writes(): void
    {
        $post = $this->makePublishedPost('Galería nueva 📷 del Liceo');

        $this->assertSame('Galería nueva  del Liceo', $post->fresh()->title);
    }

    public function test_spanish_text_with_accents_and_euro_is_preserved_verbatim(): void
    {
        $post = $this->makePublishedPost('El Liceo Ábalos añadió ñ con un costo de 100 €');

        $this->assertSame(
            'El Liceo Ábalos añadió ñ con un costo de 100 €',
            $post->fresh()->title
        );
    }

    public function test_http_form_input_is_sanitized_before_storage(): void
    {
        $this->post('/contacto', [
            'name' => 'María “Peña” 😀',
            'email' => 'maria@example.com',
            'subject' => 'Consulta — urgente…',
            'message' => 'Buenos días, ¿hay información del evento… 😀 Gracias.',
        ])->assertRedirect('/');

        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertSame('María "Peña"', $contact->name);
        $this->assertSame('Consulta - urgente...', $contact->subject);
        $this->assertTrue(Latin9::isSafe($contact->message));
        $this->assertStringContainsString('Buenos días', $contact->message);
    }

    public function test_search_with_unrepresentable_chars_returns_200(): void
    {
        $this->makePublishedPost('Noticia de prueba');

        // Un "…" o comilla en el parámetro provocaría el error 22021 de
        // PostgreSQL si el middleware no lo transliterara antes del ILIKE.
        $this->get('/buscar?q='.urlencode('niño…“'))->assertOk();
        $this->get('/buscar?q='.urlencode('¿ÑOÑO 😀?'))->assertOk();
    }

    public function test_encrypted_pii_roundtrips_through_latin9(): void
    {
        $this->post('/contacto', [
            'name' => 'José Ñúñez',
            'email' => 'jose@example.com',
            'subject' => 'Asunto con ñ y €',
            'message' => 'Mensaje con acentuación: á é í ó ú.',
        ])->assertRedirect('/');

        $contact = Contact::first();
        $this->assertSame('José Ñúñez', $contact->name);
        $this->assertSame('Asunto con ñ y €', $contact->subject);
    }

    public function test_raw_unsanitized_write_fails_only_on_latin9_servers(): void
    {
        $this->requirePgsql();

        if ($this->serverEncoding() !== 'latin9') {
            $this->markTestSkipped('Aserción específica de servidores LATIN9');
        }

        // Sin sanitizar: PostgreSQL rechaza el carácter (22021). La
        // sub-transacción (savepoint) aísla el fallo de la transacción de
        // RefreshDatabase para poder seguir asertiendo después.
        try {
            DB::transaction(function () {
                DB::table('settings')->insert(['key' => 'e2e.latin9', 'value' => 'texto…', 'created_at' => now(), 'updated_at' => now()]);
            });
            $this->fail('Un INSERT crudo con "…" debió fallar en una BD LATIN9');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // ...pero el modelo (que translitera) sí escribe correctamente.
        Setting::create(['key' => 'e2e.latin9', 'value' => 'texto…']);
        $this->assertSame('texto...', Setting::where('key', 'e2e.latin9')->value('value'));
    }

    public function test_json_request_bodies_are_sanitized_by_the_global_middleware(): void
    {
        // Rama isJson() de SanitizeLatin9Input::clean(): el body JSON también
        // se translitera antes de llegar al controlador. Un POST JSON con
        // campos incompletos responde 422 y sin desbordar.
        $this->json('POST', '/contacto', ['name' => 'José ⚡'])
            ->assertStatus(422);
    }
}
