$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) { if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }; throw 'token no encontrado' }
function Put-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Login($email, $sess) {
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
    $t = Get-Token $r.Content
    Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

Write-Output '--- 0. Estado inicial ---'
# Snapshot: preservar settings de contacto preexistentes del usuario
$contactSnapshot = @{}
foreach ($line in ((Sql 'SELECT `key`, `value` FROM portal2026.settings WHERE `key` LIKE ''contact_%'';') -split "`n")) {
    if ($line) {
        $parts = $line -split "`t", 2
        $contactSnapshot[$parts[0]] = $parts[1]
    }
}
Write-Output "settings contact_* previos: $($contactSnapshot.Count) claves preservadas"
Write-Output '--- 0b. Seeder idempotente (2a corrida) ---'
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan db:seed --class=PermissionSeeder --no-interaction 2>&1 | Out-Null
$cnt = Sql 'SELECT COUNT(*) FROM portal2026.permissions;'
Check ($cnt -eq '38') "seeder x2: 38 permisos ($cnt)" "permisos != 38: $cnt"

Write-Output '--- 1. Login superadmin + GET /admin/contacto ---'
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'admin@gdc.gob.ve' $sess
if ($r.Content -notmatch 'Admin GDC') { throw 'login fallido' }
$r = Invoke-WebRequest "$base/admin/contacto" -WebSession $sess -UseBasicParsing
Check ($r.StatusCode -eq 200) 'GET /admin/contacto -> 200' "status $($r.StatusCode)"
Check ($r.Content -match 'name="address"') 'campo address' 'falta address'
Check ($r.Content -match 'name="phone"') 'campo phone' 'falta phone'
Check ($r.Content -match 'name="email"') 'campo email' 'falta email'
Check ($r.Content -match 'name="hours"') 'campo hours' 'falta hours'
Check ($r.Content -match 'name="map_url"') 'campo map_url' 'falta map_url'
Check ($r.Content -match 'action="[^"]*admin/contacto"') 'action /admin/contacto' 'falta action'
Check ($r.Content -match '>Contacto</a>') 'item sidebar Contacto visible' 'sidebar sin Contacto'
$token = Get-Token $r.Content

Write-Output '--- 2. PUT con valores nuevos ---'
$mapTest = 'https://www.google.com/maps/embed?pb=TTEST123'
$r = Put-Form "$base/admin/contacto" @{
    _token = $token; _method = 'PUT'
    address = 'Edificio Toromaima, Caracas'; phone = '+58 212-555-1234'
    email = 'info@gdc.gob.ve'; hours = 'Lunes a viernes: 7:00 AM - 5:00 PM'
    map_url = $mapTest
} $sess
Check ($r.FinalUri -match '/admin/contacto$') 'redirect al form con exito' "no redirigio: $($r.FinalUri)"
$rows = Sql 'SELECT COUNT(*) FROM portal2026.settings WHERE `key` LIKE ''contact_%'';'
Check ($rows -eq '5') "5 settings creados ($rows)" "settings != 5: $rows"
Check ((Sql 'SELECT `value` FROM portal2026.settings WHERE `key` = ''contact_address'';') -eq 'Edificio Toromaima, Caracas') 'address guardado' 'address mal'
Check ((Sql 'SELECT `value` FROM portal2026.settings WHERE `key` = ''contact_map_url'';') -eq $mapTest) 'map_url guardado' 'map_url mal'

Write-Output '--- 3. Las 3 paginas publicas muestran los valores nuevos ---'
foreach ($p in @('/', '/contacto', '/sobre-nosotros')) {
    $r = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10
    $ok = ($r.Content -match 'Edificio Toromaima, Caracas') -and ($r.Content -match 'info@gdc\.gob\.ve') -and ($r.Content -match 'TTEST123')
    $ok2 = ($r.Content -notmatch 'Av\. Principal, Distrito Capital') -and ($r.Content -notmatch 'contacto@gdc\.gob\.ve')
    Check ($r.StatusCode -eq 200 -and $ok -and $ok2) "$p muestra valores nuevos (sin defaults viejos)" "$p no actualizo (200=$($r.StatusCode), nuevos=$ok, viejos ausentes=$ok2)"
}

Write-Output '--- 4. Validaciones: email y url invalidos ---'
$r = Invoke-WebRequest "$base/admin/contacto" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/contacto" @{
    _token = $token; _method = 'PUT'
    address = 'Edificio Toromaima, Caracas'; phone = 'x'
    email = 'no-es-email'; hours = 'h'; map_url = 'esto-no-es-url'
} $sess
Check ($r.FinalUri -match '/admin/contacto') "rechazado (volvio al form: $($r.FinalUri))" "NO rechazado: $($r.FinalUri)"
Check ((Sql 'SELECT `value` FROM portal2026.settings WHERE `key` = ''contact_email'';') -eq 'info@gdc.gob.ve') 'email no se piso con invalido' 'email se cambio'

Write-Output '--- 5. Valor vacio -> fallback a default en publico ---'
$r = Invoke-WebRequest "$base/admin/contacto" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/contacto" @{
    _token = $token; _method = 'PUT'
    address = ''; phone = '+58 212-555-1234'
    email = 'info@gdc.gob.ve'; hours = 'Lunes a viernes: 7:00 AM - 5:00 PM'
    map_url = $mapTest
} $sess
$r = Invoke-WebRequest "$base/contacto" -UseBasicParsing
Check ($r.Content -match 'Av\. Principal, Distrito Capital') 'address vacio muestra default' 'no muestra default'

Write-Output '--- 6. Permisos: admin2 ve, editor no ---'
$sess2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'admin2@gdc.gob.ve' $sess2
if ($r.Content -notmatch 'Admin GDC') { throw 'login admin2 fallido' }
$r = Invoke-WebRequest "$base/admin/contacto" -WebSession $sess2 -UseBasicParsing
Check ($r.StatusCode -eq 200) 'admin (rol) -> 200 en /admin/contacto' "admin: $($r.StatusCode)"
Check ($r.Content -match '>Contacto</a>') 'admin ve item sidebar' 'admin no ve item'
$sess3 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'editor@gdc.gob.ve' $sess3
if ($r.Content -notmatch 'Admin GDC') { throw 'login editor fallido' }
try {
    $r = Invoke-WebRequest "$base/admin/contacto" -WebSession $sess3 -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    Check ($false) '' "editor obtuvo $($r.StatusCode) (esperado 403)"
} catch {
    $sc = [int]$_.Exception.Response.StatusCode
    Check ($sc -eq 403) 'editor -> 403 en /admin/contacto' "editor: $sc"
}
$r = Invoke-WebRequest "$base/admin" -WebSession $sess3 -UseBasicParsing
Check ($r.Content -notmatch '>Contacto</a>') 'editor NO ve item sidebar' 'editor SI ve item'

Write-Output '--- 7. Smoke publico ---'
foreach ($p in @('/', '/contacto', '/sobre-nosotros', '/noticias')) {
    try { $r = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r.StatusCode -eq 200) "$p 200" "$p $($r.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output '--- 8. Cleanup: restaurar settings previos (nunca pisar datos reales) ---'
# Borrar claves creadas por la suite que no existian antes
foreach ($line in ((Sql 'SELECT `key` FROM portal2026.settings WHERE `key` LIKE ''contact_%'';') -split "`n")) {
    if ($line -and -not $contactSnapshot.ContainsKey($line)) {
        Sql ('DELETE FROM portal2026.settings WHERE `key`=''' + $line + ''';') | Out-Null
    }
}
# Restaurar los valores originales
foreach ($k in $contactSnapshot.Keys) {
    $v = $contactSnapshot[$k] -replace "'", "''"
    Sql ('UPDATE portal2026.settings SET value=''' + $v + ''' WHERE `key`=''' + $k + ''';') | Out-Null
}
$restored = $true
foreach ($k in $contactSnapshot.Keys) {
    $v = (Sql ('SELECT `value` FROM portal2026.settings WHERE `key`=''' + $k + ''';') -join '')
    if ($v -ne $contactSnapshot[$k]) { $restored = $false }
}
Check $restored 'settings de contacto previos restaurados' 'NO se restauraron los settings previos'
$r = Invoke-WebRequest "$base/contacto" -UseBasicParsing
if ($contactSnapshot.Count -eq 0) {
    Check (($r.Content -match 'Av\. Principal, Distrito Capital') -and ($r.Content -match 'contacto@gdc\.gob\.ve')) 'publico vuelve a defaults (no habia datos previos)' 'no volvio a defaults'
} else {
    $addr = $contactSnapshot['contact_address']
    if ($addr) {
        Check ($r.Content -match [regex]::Escape($addr)) 'publico muestra el valor previo restaurado' 'no restauro el valor previo en publico'
    } else {
        Ok 'address previo vacio: publico con default (correcto)'
    }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== CONTACTO INFO: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
