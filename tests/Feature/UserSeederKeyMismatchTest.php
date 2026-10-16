<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Regresión del fallo «The MAC is invalid» en producción: si el APP_KEY
 * cambia tras haberse creado una fila seed de users, su name cifrado no se
 * puede descifrar. El seeder debe detenerse con una guía accionable (y no
 * con un stack trace crudo) — DOCUMENTACION.md §14.D.
 */
class UserSeederKeyMismatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_with_same_key_is_idempotent_and_restores_canonical_values(): void
    {
        $this->seed(UserSeeder::class);

        // Drift simulado: alguien cambió el rol desde /admin/usuarios.
        User::where('email', 'admin@gdc.gob.ve')->update(['role' => 'editor']);

        $this->seed(UserSeeder::class);

        $user = User::where('email', 'admin@gdc.gob.ve')->first();
        $this->assertNotNull($user);
        $this->assertSame('superadmin', $user->role, 'El re-seed debe restaurar el rol canónico');
        $this->assertSame('Super Administrador', $user->name);
    }

    public function test_key_change_halts_seeding_with_actionable_guidance(): void
    {
        $this->seed(UserSeeder::class);

        // Simular una regeneración del APP_KEY en .env (key:generate) con
        // filas ya cifradas con la clave anterior.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $output = new BufferedOutput;

        // Sin esto, Laravel mockea la salida de consola en tests y el mensaje
        // del seeder se pierde en el mock en vez de llegar al buffer.
        $this->withoutMockingConsoleOutput();

        try {
            Artisan::call('db:seed', ['--class' => UserSeeder::class], $output);
            $this->fail('Debió lanzar DecryptException ante un APP_KEY cambiado');
        } catch (DecryptException $e) {
            $this->assertStringContainsString('The MAC is invalid', $e->getMessage());
        }

        $guia = $output->fetch();
        $this->assertStringContainsString('migrate:fresh --seed', $guia, 'Debe sugerir el reseed completo');
        $this->assertStringContainsString(
            "DELETE FROM users WHERE email = 'admin@gdc.gob.ve'",
            $guia,
            'Debe indicar el SQL crudo (sin descifrar) para borrar la fila afectada'
        );
        $this->assertStringContainsString('§14.D', $guia, 'Debe referenciar la sección de documentación');
    }
}
