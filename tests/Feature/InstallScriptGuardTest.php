<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regresión del guard de public/install.php (revisión de despliegue
 * 2026-10-23). El guard anterior era AND ("superadmin existe Y no quedan
 * usuarios seed"), lo que mantenía el instalador abierto —y permitía resetear
 * la contraseña de cualquier cuenta sin autenticación— si un re-`db:seed`
 * dejaba usuarios seed conviviendo con un superadmin.
 *
 * El guard correcto es: basta UN superadministrador para cerrar el instalador.
 */
class InstallScriptGuardTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/public/install.php');
    }

    public function test_guard_refuses_when_any_superadmin_exists(): void
    {
        $this->assertStringContainsString(
            'if ($superadmins > 0)',
            $this->source(),
            'El guard de install.php debe cerrarse con solo existir un superadmin (sin condición AND con usuarios seed).'
        );
    }

    public function test_guard_no_longer_requires_zero_seed_users(): void
    {
        $this->assertStringNotContainsString(
            '$superadmins > 0 && $seedRestantes === 0',
            $this->source(),
            'La condición AND con $seedRestantes permitía reabrir el instalador con un superadmin ya creado (account takeover sin autenticación).'
        );
    }

    public function test_script_self_deletes_when_already_configured(): void
    {
        $this->assertStringContainsString(
            'eliminar_script()',
            $this->source(),
            'El instalador debe autoeliminarse al detectar que el portal ya está configurado.'
        );
    }
}
