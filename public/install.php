<?php

/**
 * Instalador one-shot de usuarios (superadministrador + administrador).
 *
 * Ver DOCUMENTACION.md §14.E/§14.F.1. Se ejecuta UNA sola vez tras desplegar,
 * justo después de `php artisan migrate --seed`, y se auto-elimina al
 * completarse (o al detectar que el portal ya está configurado).
 *
 * Flujo (similar al instalador de Nextcloud): formulario web con los datos de
 * los dos usuarios reales; al enviarlo crea/actualiza esos usuarios, reasigna
 * al superadmin real todo el contenido sembrado (noticias, galerías, gacetas,
 * videos) que estuviera firmado por los usuarios seed, borra los usuarios seed
 * de prueba (`password`) y se borra a sí mismo. El contenido cargado con
 * `php artisan db:seed` (p. ej. NoticiasPrecargadasSeeder) NO se ve afectado.
 */

use App\Models\Gaceta;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Support\Audit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

/** Emails seed de desarrollo/prueba a eliminar (§14.F.1). */
$seedEmails = ['admin@gdc.gob.ve', 'admin2@gdc.gob.ve', 'editor@gdc.gob.ve'];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function eliminar_script(): bool
{
    // En Linux (producción) el unlink durante la ejecución funciona; en
    // Windows el archivo en uso puede no poder borrarse (fallback manual).
    @unlink(__FILE__);

    return ! file_exists(__FILE__);
}

function pagina(string $titulo, string $cuerpoHtml): never
{
    echo '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>'.h($titulo).' — Portal GDC</title>
<style>
    body { font-family: system-ui, sans-serif; background: #f3f4f8; display: flex; justify-content: center; padding: 3rem 1rem; margin: 0; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 18px rgba(20, 30, 80, .12); padding: 2rem 2.5rem; max-width: 560px; width: 100%; }
    h1 { color: #212a5e; font-size: 1.4rem; margin-top: 0; }
    h2 { color: #212a5e; font-size: 1.05rem; margin: 1.5rem 0 .5rem; border-bottom: 1px solid #e3e6f0; padding-bottom: .35rem; }
    label { display: block; font-weight: 600; margin: .8rem 0 .25rem; color: #333; }
    input { width: 100%; padding: .55rem .7rem; border: 1px solid #ccd; border-radius: 6px; box-sizing: border-box; font-size: .95rem; }
    button { margin-top: 1.5rem; background: #212a5e; color: #fff; border: 0; border-radius: 6px; padding: .7rem 1.4rem; font-size: 1rem; cursor: pointer; width: 100%; }
    button:hover { background: #2f3c86; }
    .error { background: #fdecea; color: #a12622; border: 1px solid #f5c6c2; border-radius: 6px; padding: .7rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
    .ok { background: #e8f6ec; color: #1d6b34; border: 1px solid #bfe3c8; border-radius: 6px; padding: .7rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
    .hint { color: #667; font-size: .82rem; margin-top: .2rem; }
    p { color: #444; line-height: 1.55; }
</style>
</head>
<body>
<div class="card">
<h1>Portal GDC — Instalación de usuarios</h1>
'.$cuerpoHtml.'
</div>
</body>
</html>';
    exit;
}

// ---------------------------------------------------------------------------
// Guardas
// ---------------------------------------------------------------------------

try {
    $seedRestantes = User::whereIn('email', $seedEmails)->count();
    $superadmins = User::where('role', 'superadmin')->count();
    // Aviso de codificación: la BD de producción debe ser LATIN9 (§14.K).
    $serverEncoding = '';
    if (DB::connection()->getDriverName() === 'pgsql') {
        $row = DB::selectOne('SHOW server_encoding');
        $serverEncoding = strtolower((string) ($row->server_encoding ?? ''));
    }
    $latin9Ok = $serverEncoding === '' || str_starts_with($serverEncoding, 'latin');
} catch (Throwable $e) {
    pagina('No disponible', '<p class="error">No se pudo conectar con la base de datos. Ejecuta primero <code>php artisan migrate --seed</code> y revisa la configuración de <code>.env</code>.</p>');
}

$avisoEncoding = '';
if (! $latin9Ok) {
    $avisoEncoding = '<p class="error">La base de datos usa la codificación <code>'.h($serverEncoding ?: 'desconocida').'</code>; la BD de producción debe ser <strong>LATIN9</strong> (ISO-8859-15). Ver DOCUMENTACION.md §14.K.</p>';
}

// El portal ya está configurado: no tocar nada, solo retirar el instalador.
if ($superadmins > 0 && $seedRestantes === 0) {
    $eliminado = eliminar_script();
    $nota = $eliminado
        ? 'El instalador se ha eliminado automáticamente.'
        : '<strong>Elimina este archivo manualmente</strong> (<code>public/install.php</code>): no se pudo borrar solo.';
    pagina('Ya configurado', '<div class="ok">El portal ya tiene un superadministrador y no quedan usuarios de prueba.</div><p>'.$nota.'</p>');
}

// ---------------------------------------------------------------------------
// Formulario
// ---------------------------------------------------------------------------

$errores = [];
$old = [
    'sa_name' => '', 'sa_email' => 'admin@gdc.gob.ve',
    'ad_name' => '', 'ad_email' => 'admin2@gdc.gob.ve',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($old) as $key) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $saPassword = (string) ($_POST['sa_password'] ?? '');
    $saConfirm = (string) ($_POST['sa_confirm'] ?? '');
    $adPassword = (string) ($_POST['ad_password'] ?? '');
    $adConfirm = (string) ($_POST['ad_confirm'] ?? '');

    if ($old['sa_name'] === '') {
        $errores[] = 'El nombre del superadministrador es obligatorio.';
    }
    if (! filter_var($old['sa_email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email del superadministrador no es válido.';
    }
    if (strlen($saPassword) < 8) {
        $errores[] = 'La contraseña del superadministrador debe tener al menos 8 caracteres.';
    }
    if ($saPassword !== $saConfirm) {
        $errores[] = 'Las contraseñas del superadministrador no coinciden.';
    }
    if ($old['ad_name'] === '') {
        $errores[] = 'El nombre del administrador es obligatorio.';
    }
    if (! filter_var($old['ad_email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email del administrador no es válido.';
    }
    if (strlen($adPassword) < 8) {
        $errores[] = 'La contraseña del administrador debe tener al menos 8 caracteres.';
    }
    if ($adPassword !== $adConfirm) {
        $errores[] = 'Las contraseñas del administrador no coinciden.';
    }

    if (! $errores) {
        DB::transaction(function () use ($old, $saPassword, $adPassword, $seedEmails) {
            $superadmin = User::updateOrCreate(
                ['email' => $old['sa_email']],
                ['name' => $old['sa_name'], 'password' => Hash::make($saPassword)]
            );
            $superadmin->forceFill(['role' => 'superadmin', 'is_active' => true, 'password_changed_at' => now()])->save();
            $admin = User::updateOrCreate(
                ['email' => $old['ad_email']],
                ['name' => $old['ad_name'], 'password' => Hash::make($adPassword)]
            );
            $admin->forceFill(['role' => 'admin', 'is_active' => true, 'password_changed_at' => now()])->save();

            // El contenido sembrado (noticias, galerías, gacetas, videos) va
            // firmado por los usuarios seed: reasignarlo al superadmin real
            // ANTES de borrarlos para que el borrado no afecte el contenido ya
            // cargado (media/posts son FK NO ACTION; gacetas/videos SET NULL).
            $seedIds = User::whereIn('email', $seedEmails)->pluck('id');
            Media::whereIn('user_id', $seedIds)->update(['user_id' => $superadmin->id]);
            Post::whereIn('user_id', $seedIds)->update(['user_id' => $superadmin->id]);
            Gaceta::whereIn('user_id', $seedIds)->update(['user_id' => $superadmin->id]);
            Video::whereIn('user_id', $seedIds)->update(['user_id' => $superadmin->id]);

            User::whereIn('email', $seedEmails)->delete();

            Audit::log('install.users', User::class, $superadmin->id, 'Instalador: altas de superadmin y admin + borrado de usuarios seed', [
                'superadmin' => $superadmin->email,
                'admin' => $admin->email,
                'seed_eliminados' => $seedEmails,
            ]);
        });

        $eliminado = eliminar_script();
        $nota = $eliminado
            ? '<p>El instalador se ha eliminado automáticamente. No volverá a ejecutarse.</p>'
            : '<p><strong>Elimina este archivo manualmente</strong> (<code>public/install.php</code>): no se pudo borrar solo.</p>';
        pagina('Instalación completada', '<div class="ok">Usuarios creados correctamente. Ya puedes iniciar sesión en <a href="/admin/login">/admin/login</a> con el email del superadministrador.</div>'.$nota);
    }
}

$bloqueErrores = '';
if ($errores) {
    $bloqueErrores = '<div class="error"><ul style="margin: 0; padding-left: 1.1rem;">'.implode('', array_map(fn ($e) => '<li>'.h($e).'</li>', $errores)).'</ul></div>';
}

echo '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Instalación — Portal GDC</title>
<style>
    body { font-family: system-ui, sans-serif; background: #f3f4f8; display: flex; justify-content: center; padding: 3rem 1rem; margin: 0; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 18px rgba(20, 30, 80, .12); padding: 2rem 2.5rem; max-width: 560px; width: 100%; }
    h1 { color: #212a5e; font-size: 1.4rem; margin-top: 0; }
    h2 { color: #212a5e; font-size: 1.05rem; margin: 1.5rem 0 .5rem; border-bottom: 1px solid #e3e6f0; padding-bottom: .35rem; }
    label { display: block; font-weight: 600; margin: .8rem 0 .25rem; color: #333; }
    input { width: 100%; padding: .55rem .7rem; border: 1px solid #ccd; border-radius: 6px; box-sizing: border-box; font-size: .95rem; }
    button { margin-top: 1.5rem; background: #212a5e; color: #fff; border: 0; border-radius: 6px; padding: .7rem 1.4rem; font-size: 1rem; cursor: pointer; width: 100%; }
    button:hover { background: #2f3c86; }
    .error { background: #fdecea; color: #a12622; border: 1px solid #f5c6c2; border-radius: 6px; padding: .7rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
    .hint { color: #667; font-size: .82rem; margin-top: .2rem; }
    p { color: #444; line-height: 1.55; }
</style>
</head>
<body>
<div class="card">
<h1>Portal GDC — Instalación de usuarios</h1>
<p>Crea las cuentas reales de <strong>superadministrador</strong> y <strong>administrador</strong>. Los usuarios de prueba se eliminarán. Este script se borrará solo al terminar (ejecución única).</p>
'.$avisoEncoding.'
'.$bloqueErrores.'
<form method="post" action="">
    <h2>Superadministrador</h2>
    <label for="sa_name">Nombre</label>
    <input id="sa_name" name="sa_name" required value="'.h($old['sa_name']).'">
    <label for="sa_email">Email</label>
    <input id="sa_email" name="sa_email" type="email" required value="'.h($old['sa_email']).'">
    <label for="sa_password">Contraseña</label>
    <input id="sa_password" name="sa_password" type="password" required minlength="8" autocomplete="new-password">
    <label for="sa_confirm">Repetir contraseña</label>
    <input id="sa_confirm" name="sa_confirm" type="password" required minlength="8" autocomplete="new-password">

    <h2>Administrador</h2>
    <label for="ad_name">Nombre</label>
    <input id="ad_name" name="ad_name" required value="'.h($old['ad_name']).'">
    <label for="ad_email">Email</label>
    <input id="ad_email" name="ad_email" type="email" required value="'.h($old['ad_email']).'">
    <label for="ad_password">Contraseña</label>
    <input id="ad_password" name="ad_password" type="password" required minlength="8" autocomplete="new-password">
    <label for="ad_confirm">Repetir contraseña</label>
    <input id="ad_confirm" name="ad_confirm" type="password" required minlength="8" autocomplete="new-password">

    <p class="hint">Mínimo 8 caracteres por contraseña. Después del primer inicio de sesión se recomienda cambiarlas desde el panel.</p>
    <button type="submit">Instalar y eliminar este script</button>
</form>
</div>
</body>
</html>';
