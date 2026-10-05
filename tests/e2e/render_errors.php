<?php

$portal = dirname(__DIR__, 2);
require $portal . '/vendor/autoload.php';
$app = require $portal . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$expected = [
    '400' => 'Solicitud no',
    '401' => 'No autorizado',
    '403' => 'Acceso denegado',
    '404' => 'P&aacute;gina no encontrada',
    '405' => 'M&eacute;todo no permitido',
    '408' => 'Tiempo de espera',
    '410' => 'Contenido eliminado',
    '419' => 'Recargar e intentar',
    '422' => 'Datos no',
    '429' => 'Demasiadas solicitudes',
    '500' => 'Error interno del servidor',
    '503' => 'Servicio no disponible',
];

$fail = 0;
foreach ($expected as $code => $needle) {
    try {
        $html = view('errors.' . $code)->render();
        $needleDecoded = html_entity_decode($needle, ENT_QUOTES, 'UTF-8');
        $hasTitle = str_contains($html, $needleDecoded);
        $hasBack = str_contains($html, 'Volver');
        $hasHome = str_contains($html, $code === '500' || $code === '503' ? 'Ir al inicio' : 'Ir al inicio');
        $ok = $hasTitle && $hasBack && $hasHome;
        printf("[%s] %s titulo=%s volver=%s inicio=%s\n", $ok ? 'OK' : 'FAIL', $code, $hasTitle ? 'si' : 'NO', $hasBack ? 'si' : 'NO', $hasHome ? 'si' : 'NO');
        if (!$ok) {
            $fail++;
        }
    } catch (\Throwable $e) {
        printf("[FAIL] %s excepcion: %s\n", $code, $e->getMessage());
        $fail++;
    }
}

printf($fail === 0 ? "=== RENDER: 12/12 OK ===\n" : "=== RENDER: $fail fallas ===\n");
exit($fail === 0 ? 0 : 1);
