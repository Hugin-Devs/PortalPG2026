$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) { if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }; throw 'token no encontrado' }
function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing | Out-Null
    return $s
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }
function Put-Social($session, $fields) {
    $r = Invoke-WebRequest "$base/admin/rrss" -WebSession $session -UseBasicParsing
    $t = Get-Token $r.Content
    $pairs = @("_token=$([uri]::EscapeDataString($t))", "_method=PUT")
    foreach ($k in @('facebook','instagram','youtube','x','threads','telegram','whatsapp')) {
        $v = if ($fields.ContainsKey($k)) { $fields[$k] } else { '' }
        $pairs += "$k=$([uri]::EscapeDataString($v))"
    }
    $p = @{ Uri = "$base/admin/rrss"; Method = 'Post'; Body = ($pairs -join '&'); ContentType = 'application/x-www-form-urlencoded'; WebSession = $session; UseBasicParsing = $true }
    return Invoke-WebRequest @p
}

# --- Snapshot: preservar valores sociales preexistentes del usuario ---
$socialSnapshot = @{}
foreach ($line in ((Sql 'SELECT `key`, `value` FROM portal2026.settings WHERE `key` LIKE ''social_%'';') -split "`n")) {
    if ($line) {
        $parts = $line -split "`t", 2
        $socialSnapshot[$parts[0]] = $parts[1]
    }
}
Write-Output "snapshot social: $($socialSnapshot.Count) claves preservadas"

Write-Output '--- 1. Seeders idempotentes ---'
& $php artisan db:seed --class=PermissionSeeder --force 2>&1 | Out-Null
& $php artisan db:seed --class=SocialSettingSeeder --force 2>&1 | Out-Null
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.permissions;') -join ''
Check ($c -eq '38') "38 permisos (got $c)" 'permisos != 38'
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''social_%'';') -join ''
Check ($c -eq '7') "7 settings sociales (got $c)" 'settings != 7'
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.role_permission;') -join ''
Check ($c -eq '64') "64 asignaciones 38+21+5 (got $c)" 'asignaciones != 64'
$c = (& $mysql -uroot -N -e 'SELECT slug FROM portal2026.permissions WHERE slug=''rrss.manage'';') -join ''
Check ($c -eq 'rrss.manage') 'slug rrss.manage existe' "falta rrss.manage (got $c)"
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug=''settings.manage'';') -join ''
Check ($c -eq '1') 'slug settings.manage existe (Configuración)' "settings.manage count != 1 (got $c)"
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug=''rrss.manage'' AND `group` LIKE ''Gesti%Portal'' AND `module`=''Redes Sociales'';') -join ''
Check ($c -eq '1') "rrss.manage en grupo Gestion Portal, modulo Redes Sociales (got $c)" 'grupo/modulo incorrecto'

Write-Output '--- 2. Seeder no pisa valores editados ---'
& $mysql -uroot -N -e 'UPDATE portal2026.settings SET value=''https://www.facebook.com/editado'' WHERE `key`=''social_facebook'';' 2>&1 | Out-Null
& $php artisan db:seed --class=SocialSettingSeeder --force 2>&1 | Out-Null
$v = (& $mysql -uroot -N -e 'SELECT value FROM portal2026.settings WHERE `key`=''social_facebook'';') -join ''
Check ($v -eq 'https://www.facebook.com/editado') 'valor editado conservado' "valor perdido (got $v)"

Write-Output '--- 3. Superadmin: formulario ---'
$super = Login 'admin@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/rrss" -WebSession $super -UseBasicParsing
Check ($r.StatusCode -eq 200) 'GET 200' "GET $($r.StatusCode)"
foreach ($f in @('facebook','instagram','youtube','x','threads','telegram','whatsapp')) {
    Check ($r.Content -match "name=`"$f`"") "input $f" "falta input $f"
}
Check ($r.Content -match 'Redes sociales') 'card Redes sociales' 'sin card'

Write-Output '--- 4. Guardar 7 URLs validas ---'
$urls = @{
    facebook = 'https://www.facebook.com/gdc'
    instagram = 'https://www.instagram.com/gdc'
    youtube = 'https://www.youtube.com/@gdc'
    x = 'https://x.com/gdcoficial'
    threads = 'https://www.threads.net/@gdc'
    telegram = 'https://t.me/gdc'
    whatsapp = 'https://wa.me/580000000000'
}
$r = Put-Social $super $urls
Check ($r.StatusCode -eq 200) 'PUT redirige y renderiza (200)' "PUT $($r.StatusCode)"
Check ($r.Content -match 'actualizadas correctamente') 'alerta de exito' 'sin alerta'
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''social_%'' AND value != '''';') -join ''
Check ($c -eq '7') "7 settings con valor (got $c)" 'valores no guardados'

Write-Output '--- 5. Publico: iconos SOLO en footer ---'
$r = Invoke-WebRequest "$base/" -UseBasicParsing -TimeoutSec 15
Check ($r.StatusCode -eq 200) 'home 200' "home $($r.StatusCode)"
$n = ([regex]::Matches($r.Content, 'fa-brands fa-')).Count
Check ($n -eq 7) "7 iconos (7 redes x solo footer) (got $n)" "iconos != 7 (header los incluye?)"
Check ($r.Content -match 'fa-brands fa-x-twitter') 'icono X' 'sin icono X'
Check ($r.Content -match 'fa-brands fa-threads') 'icono Threads' 'sin icono Threads'
Check ($r.Content -match 'rel="noopener noreferrer"') 'rel noopener' 'sin rel noopener'
Check ($r.Content -match 'href="https://wa.me/580000000000"') 'href whatsapp' 'sin href whatsapp'

Write-Output '--- 6. Vaciar 2 redes: se ocultan ---'
$partial = @{ facebook = $urls.facebook; instagram = $urls.instagram; youtube = $urls.youtube; threads = $urls.threads; whatsapp = $urls.whatsapp }
$r = Put-Social $super $partial
Check ($r.StatusCode -eq 200) 'PUT parcial 200' "PUT $($r.StatusCode)"
$c = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''social_%'' AND value != '''';') -join ''
Check ($c -eq '5') "5 settings con valor (got $c)" 'conteo != 5'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
$n = ([regex]::Matches($r.Content, 'fa-brands fa-')).Count
Check ($n -eq 5) "5 iconos (5 redes, solo footer) (got $n)" "iconos != 5"
Check (-not ($r.Content -match 'fa-brands fa-x-twitter')) 'X oculto' 'X sigue visible'

Write-Output '--- 7. URL invalida rechazada (BD intacta) ---'
$before = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''social_%'' AND value != '''';') -join ''
$r = Put-Social $super @{ facebook = 'javascript:alert(1)' }
Check ($r.StatusCode -eq 200) 'redirige con errores (200)' "status $($r.StatusCode)"
Check ($r.Content -match 'is-invalid' -or $r.Content -match 'invalid-feedback') 'campo en error' 'sin validacion visible'
$after = (& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''social_%'' AND value != '''';') -join ''
Check ($before -eq $after) 'BD no cambió' "BD cambio ($before -> $after)"
$v = (& $mysql -uroot -N -e 'SELECT value FROM portal2026.settings WHERE `key`=''social_facebook'';') -join ''
Check ($v -eq 'https://www.facebook.com/gdc') 'facebook conservado' "facebook alterado (got $v)"

Write-Output '--- 8. Permisos y sidebar ---'
$admin = Login 'admin2@gdc.gob.ve'
try {
    $r = Invoke-WebRequest "$base/admin/rrss" -WebSession $admin -UseBasicParsing -ErrorAction Stop
    Bad "admin acceso inesperado (status $($r.StatusCode))"
} catch {
    $sc = [int]$_.Exception.Response.StatusCode
    Check ($sc -eq 403) 'admin2 -> 403' "admin2 -> $sc"
}
$r = Invoke-WebRequest "$base/admin" -WebSession $admin -UseBasicParsing
Check (-not ($r.Content -match '/admin/rrss')) 'admin sin item sidebar' 'admin ve Redes Sociales'
$editor = Login 'editor@gdc.gob.ve'
try {
    $r = Invoke-WebRequest "$base/admin/rrss" -WebSession $editor -UseBasicParsing -ErrorAction Stop
    Bad "editor acceso inesperado (status $($r.StatusCode))"
} catch {
    $sc = [int]$_.Exception.Response.StatusCode
    Check ($sc -eq 403) 'editor -> 403' "editor -> $sc"
}
$r = Invoke-WebRequest "$base/admin" -WebSession $super -UseBasicParsing
Check ($r.Content -match '/admin/rrss') 'superadmin ve item sidebar' 'superadmin sin item'

Write-Output '--- 9. Smoke ---'
foreach ($p in @('/', '/noticias', '/contacto')) {
    try { $r2 = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r2.StatusCode -eq 200) "$p 200" "$p $($r2.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output '--- 10. Restaurar valores previos del usuario ---'
# Borrar claves creadas por la suite que no existian antes
foreach ($line in ((Sql 'SELECT `key` FROM portal2026.settings WHERE `key` LIKE ''social_%'';') -split "`n")) {
    if ($line -and -not $socialSnapshot.ContainsKey($line)) {
        Sql ('DELETE FROM portal2026.settings WHERE `key`=''' + $line + ''';') | Out-Null
    }
}
# Restaurar los valores originales
foreach ($k in $socialSnapshot.Keys) {
    $v = $socialSnapshot[$k] -replace "'", "''"
    Sql ('UPDATE portal2026.settings SET value=''' + $v + ''' WHERE `key`=''' + $k + ''';') | Out-Null
}
$restored = $true
foreach ($k in $socialSnapshot.Keys) {
    $v = (Sql ('SELECT value FROM portal2026.settings WHERE `key`=''' + $k + ''';') -join '')
    if ($v -ne $socialSnapshot[$k]) { $restored = $false }
}
Check $restored 'valores sociales previos restaurados' 'NO se restauraron los valores previos'

Write-Output ''
if ($fail -eq 0) { Write-Output '=== REDES: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
