$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'token no encontrado'
}
function Post-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Login($email) {
    # Los usuarios creados por el panel quedan con password_changed_at = NULL y
    # el middleware los redirige al cambio forzado de contraseña (AUTH-02). El
    # flujo de cambio esta cubierto por PHPUnit (PasswordChangeTest); aqui se
    # marca como ya cambiado para que el arnes valide CRUD/permisos/last_login_at.
    Sql "UPDATE portal2026.users SET password_changed_at = NOW() WHERE email='$email' AND password_changed_at IS NULL;" | Out-Null
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    $r2 = Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=$([uri]::EscapeDataString('password'))&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing
    $r2 | Add-Member -NotePropertyName Sess -NotePropertyValue $s -Force
    return $r2
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

# --- Limpieza quirurgica: solo usuarios E2E de corridas anteriores ---
Sql "DELETE FROM portal2026.users WHERE email IN ('e2e-activo@prueba.local','e2e-inactivo@prueba.local');" | Out-Null

Write-Output '--- 1. Login superadmin + columnas del listado ---'
$super = Login 'admin@gdc.gob.ve'
if ($super.Content -notmatch 'Admin GDC') { throw 'login superadmin fallido' }
Ok 'LOGIN superadmin'
$r = Invoke-WebRequest "$base/admin/usuarios" -WebSession $super.Sess -UseBasicParsing
Check ($r.Content -match '>Estado<') 'columna Estado' 'falta columna Estado'
Check ($r.Content -match 'ltima conexi') 'columna Ultima conexion' 'falta columna Ultima conexion'
Check ($r.Content -match 'Activo') 'badge Activo presente' 'sin badges de estado'

Write-Output '--- 2. Crear usuario ACTIVO (checkbox marcado) ---'
$token = Get-Token $r.Content
$r = Post-Form "$base/admin/usuarios" @{
    _token = $token; name = 'E2E Activo'; email = 'e2e-activo@prueba.local'
    password = 'password'; password_confirmation = 'password'; role = 'editor'; is_active = 'on'
} $super.Sess
Check ($r.FinalUri -match '/admin/usuarios$') 'redirect al listado' "uri: $($r.FinalUri)"
$row = Sql "SELECT is_active FROM portal2026.users WHERE email='e2e-activo@prueba.local';"
Check ($row -eq '1') "usuario activo (is_active=$row)" "is_active incorrecto: $row"

Write-Output '--- 3. Login usuario activo -> entra y registra last_login_at ---'
$r = Login 'e2e-activo@prueba.local'
Check ($r.Content -match 'Admin GDC') 'usuario activo entra al admin' 'no pudo entrar'
$l = Sql "SELECT last_login_at IS NOT NULL FROM portal2026.users WHERE email='e2e-activo@prueba.local';"
Check ($l -eq '1') "last_login_at registrado ($l)" "last_login_at no se registro: $l"

Write-Output '--- 4. Crear usuario INACTIVO (checkbox ausente) ---'
$r = Invoke-WebRequest "$base/admin/usuarios" -WebSession $super.Sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Post-Form "$base/admin/usuarios" @{
    _token = $token; name = 'E2E Inactivo'; email = 'e2e-inactivo@prueba.local'
    password = 'password'; password_confirmation = 'password'; role = 'editor'
} $super.Sess
Check ($r.FinalUri -match '/admin/usuarios$') 'redirect al listado' "uri: $($r.FinalUri)"
$row = Sql "SELECT is_active FROM portal2026.users WHERE email='e2e-inactivo@prueba.local';"
Check ($row -eq '0') "usuario inactivo (is_active=$row)" "is_active incorrecto: $row"

Write-Output '--- 5. Login usuario inactivo -> rechazado (mensaje generico) ---'
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
$token = Get-Token $r.Content
$r = Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=e2e-inactivo%40prueba.local&password=$([uri]::EscapeDataString('password'))&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing
Check ($r.Content -match 'Credenciales') 'mensaje generico Credenciales' 'sin mensaje de error'
Check ($r.Content -notmatch 'inactiva') 'sin revelar estado de la cuenta' 'revela que la cuenta esta inactiva'
Check ($r.Content -notmatch 'Admin GDC') 'no entra al admin' 'entro estando inactivo'
$l = Sql "SELECT last_login_at IS NULL FROM portal2026.users WHERE email='e2e-inactivo@prueba.local';"
Check ($l -eq '1') 'last_login_at sigue NULL' 'last_login_at fue seteado'

Write-Output '--- 6. Activarlo -> ahora si entra ---'
$id = Sql "SELECT id FROM portal2026.users WHERE email='e2e-inactivo@prueba.local';"
$r = Invoke-WebRequest "$base/admin/usuarios/$id/editar" -WebSession $super.Sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Post-Form "$base/admin/usuarios/$id" @{
    _token = $token; _method = 'PUT'; name = 'E2E Inactivo'; email = 'e2e-inactivo@prueba.local'
    role = 'editor'; is_active = 'on'
} $super.Sess
Check ($r.FinalUri -match '/admin/usuarios$') 'update -> redirect' "uri: $($r.FinalUri)"
$r = Login 'e2e-inactivo@prueba.local'
Check ($r.Content -match 'Admin GDC') 'usuario ya activo entra' 'no entra tras activar'

Write-Output '--- 7. Guard: no puedes desactivarte a ti mismo ---'
$selfId = Sql "SELECT id FROM portal2026.users WHERE email='admin@gdc.gob.ve';"
$r = Invoke-WebRequest "$base/admin/usuarios/$selfId/editar" -WebSession $super.Sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Post-Form "$base/admin/usuarios/$selfId" @{
    _token = $token; _method = 'PUT'; name = 'Super Admin'; email = 'admin@gdc.gob.ve'
    role = 'superadmin'
} $super.Sess
Check ($r.Content -match 'No puedes desactivarte a ti mismo') 'mensaje de guard visible' 'sin mensaje de guard'
$a = Sql "SELECT is_active FROM portal2026.users WHERE id=$selfId;"
Check ($a -eq '1') "superadmin sigue activo (is_active=$a)" "se auto-desactivo: $a"

Write-Output '--- 8. Listado muestra estado + ultima conexion ---'
$r = Invoke-WebRequest "$base/admin/usuarios" -WebSession $super.Sess -UseBasicParsing
Check ($r.Content -match 'e2e-activo@prueba.local') 'usuario E2E en listado' 'falta usuario en listado'
Check (($r.Content -match 'Activo') -and ($r.Content -match 'Inactivo')) 'badges Activo e Inactivo' 'badges incompletos'
Check ($r.Content -match '\d{2}/\d{2}/\d{4} \d{2}:\d{2}') 'ultima conexion con formato fecha' 'sin formato de fecha'

Write-Output '--- 9. Cleanup: eliminar usuarios de prueba ---'
foreach ($u in @('e2e-activo@prueba.local', 'e2e-inactivo@prueba.local')) {
    $uid = Sql "SELECT id FROM portal2026.users WHERE email='$u';"
    if ($uid) {
        $r = Invoke-WebRequest "$base/admin/usuarios" -WebSession $super.Sess -UseBasicParsing
        $token = Get-Token $r.Content
        Invoke-WebRequest "$base/admin/usuarios/$uid" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $super.Sess -UseBasicParsing | Out-Null
    }
}
$cnt = Sql "SELECT COUNT(*) FROM portal2026.users WHERE email IN ('e2e-activo@prueba.local','e2e-inactivo@prueba.local');"
Check ($cnt -eq '0') 'usuarios de prueba eliminados' "restan: $cnt"
$real = Sql "SELECT COUNT(*) FROM portal2026.users;"
Check ($real -ge '3') "usuarios reales intactos ($real)" "usuarios reales alterados: $real"

Write-Output '--- 10. Smoke ---'
foreach ($p in @('/', '/noticias', '/gacetas')) {
    try { $r2 = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r2.StatusCode -eq 200) "$p 200" "$p $($r2.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== USUARIOS: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== USUARIOS: $fail FALLAS ==="; exit 1 }
