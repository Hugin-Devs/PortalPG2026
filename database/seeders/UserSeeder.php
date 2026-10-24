<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // El seed es autoritativo en name/role: si alguien cambió el rol desde
        // /admin/usuarios, un re-seed restaura el estado canónico.
        // El password solo se asigna al crear (no se pisa en usuarios existentes).
        $users = [
            ['email' => 'admin@gdc.gob.ve', 'name' => 'Super Administrador', 'role' => 'superadmin'],
            ['email' => 'editor@gdc.gob.ve', 'name' => 'Editor Principal', 'role' => 'editor'],
            ['email' => 'admin2@gdc.gob.ve', 'name' => 'Administrador', 'role' => 'admin'],
        ];

        foreach ($users as $attrs) {
            try {
                $this->seedUser($attrs);
            } catch (DecryptException $e) {
                // La fila existe pero su name cifrado no se puede descifrar
                // con el APP_KEY actual: el APP_KEY cambió tras haberse creado
                // la fila (DOCUMENTACION.md §14.D). Detener el seed con una
                // guía clara en vez de un stack trace crudo.
                $this->command?->error(sprintf(
                    "No se pudo descifrar users.name de «%s» con el APP_KEY actual (%s).\n".
                    "Causa habitual: el APP_KEY cambió después de haberse creado la fila.\n".
                    "Solución (elegir una):\n".
                    "  1. BD descartable:  php artisan migrate:fresh --seed --force\n".
                    "  2. Conservar la fila: borrar SOLO ese usuario con SQL crudo (psql):\n".
                    "       DELETE FROM users WHERE email = '%s';\n".
                    "     y re-ejecutar php artisan db:seed\n".
                    "  3. Restaurar la APP_KEY original en .env\n".
                    'Ver DOCUMENTACION.md §14.D.',
                    $attrs['email'],
                    $e->getMessage(),
                    $attrs['email']
                ));

                throw $e;
            }
        }
    }

    private function seedUser(array $attrs): void
    {
        // Los usuarios seed son fixtures de desarrollo/prueba: en local/testing
        // se crean con password_changed_at ya establecido para que los arneses
        // E2E y el login manual funcionen sin fricción. En producción sí se
        // deja en null para forzar el cambio de contraseña en el primer login.
        //
        // En producción la contraseña NO es 'password': se genera una aleatoria
        // (CSPRNG de Laravel) que nadie conoce, para cerrar la ventana entre
        // `db:seed` y la ejecución de install.php (que borra estos usuarios).
        // Si install.php no se ejecuta, nadie puede entrar con estas cuentas.
        $user = User::firstOrCreate(
            ['email' => $attrs['email']],
            [
                'name' => $attrs['name'],
                'password' => Hash::make(
                    app()->environment('production') ? Str::random(40) : 'password'
                ),
            ]
        );
        // `role` y `password_changed_at` no son mass-assignable (anti-mass-assignment).
        $user->forceFill([
            'role' => $attrs['role'],
            'password_changed_at' => app()->environment('production') ? null : now(),
        ])->save();

        if ($user->name !== $attrs['name'] || $user->role !== $attrs['role']) {
            $user->forceFill([
                'name' => $attrs['name'],
                'role' => $attrs['role'],
            ])->save();
        }

        // En entornos no productivos, alinear también password_changed_at de
        // filas ya existentes (re-seed) para no bloquear los arneses E2E.
        if (! app()->environment('production') && is_null($user->password_changed_at)) {
            $user->forceFill(['password_changed_at' => now()])->save();
        }
    }
}
