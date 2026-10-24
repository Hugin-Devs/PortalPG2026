<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_seed_keeps_the_known_password_for_harnesses(): void
    {
        // Seeder directo (sin artisan db:seed) para no atravesar el
        // confirmToProceed de SeedCommand.
        (new UserSeeder)->run();

        $user = User::where('email', 'admin@gdc.gob.ve')->first();

        $this->assertNotNull($user);
        $this->assertTrue(
            Hash::check('password', $user->password),
            'En entornos no productivos el password seed debe seguir siendo «password» (arneses E2E y login manual).'
        );
    }

    public function test_production_seed_uses_an_unknown_random_password(): void
    {
        // Simula APP_ENV=production para el seeder.
        app()->detectEnvironment(fn () => 'production');

        (new UserSeeder)->run();

        $user = User::where('email', 'admin@gdc.gob.ve')->first();

        $this->assertNotNull($user);
        $this->assertFalse(
            Hash::check('password', $user->password),
            'En producción la contraseña seed no debe ser adivinable (ventana db:seed → install.php).'
        );
        $this->assertFalse(
            Hash::check('', $user->password),
            'La contraseña seed de producción no debe ser vacía.'
        );
        // Fuerza el cambio en el primer login (aunque nadie conoce la contraseña,
        // install.php la reemplaza/borra; el middleware sigue siendo la defensa).
        $this->assertNull($user->password_changed_at);
    }
}
