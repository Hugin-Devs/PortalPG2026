$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($cond, $ok, $bad) { if ($cond) { Ok $ok } else { Bad $bad } }
function Get-Token($html) { if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }; throw 'token no encontrado' }
function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing | Out-Null
    return $s
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }
function Put-Settings($session, $fields) {
    $r = Invoke-WebRequest "$base/admin/configuracion" -WebSession $session -UseBasicParsing
    $t = Get-Token $r.Content
    $pairs = @("_token=$([uri]::EscapeDataString($t))", "_method=PUT")
    foreach ($k in $fields.Keys) { $pairs += "$k=$([uri]::EscapeDataString([string]$fields[$k]))" }
    return Invoke-WebRequest -Uri "$base/admin/configuracion" -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $session -UseBasicParsing -MaximumRedirection 5
}
function Send-Multipart($url, $fields, $files, $sess) {
    $boundary = [Guid]::NewGuid().ToString()
    $enc = [System.Text.Encoding]::UTF8
    $ms = New-Object System.IO.MemoryStream
    foreach ($k in $fields.Keys) {
        $part = "--$boundary`r`nContent-Disposition: form-data; name=`"$k`"`r`n`r`n$($fields[$k])`r`n"
        $b = $enc.GetBytes($part); $ms.Write($b, 0, $b.Length)
    }
    foreach ($key in $files.Keys) {
        foreach ($path in @($files[$key])) {
            $bytes = [System.IO.File]::ReadAllBytes($path)
            $head = "--$boundary`r`nContent-Disposition: form-data; name=`"$key`"; filename=`"$(Split-Path $path -Leaf)`"`r`nContent-Type: image/png`r`n`r`n"
            $b = $enc.GetBytes($head); $ms.Write($b, 0, $b.Length)
            $ms.Write($bytes, 0, $bytes.Length)
            $tail = "`r`n"; $b = $enc.GetBytes($tail); $ms.Write($b, 0, $b.Length)
        }
    }
    $end = $enc.GetBytes("--$boundary--`r`n"); $ms.Write($end, 0, $end.Length)
    $body = $ms.ToArray(); $ms.Dispose()
    Invoke-WebRequest -Uri $url -Method Post -Body $body -ContentType "multipart/form-data; boundary=$boundary" -WebSession $sess -UseBasicParsing -MaximumRedirection 5
}

# --- Snapshot: preservar configuracion preexistente del usuario ---
$keys = @('site_name', 'site_logo', 'nav_funcionarios_url', 'nav_jubilados_url')
$snap = @{}
foreach ($line in ((Sql 'SELECT `key`, `value` FROM portal2026.settings WHERE `key` IN (''site_name'',''site_logo'',''nav_funcionarios_url'',''nav_jubilados_url'');') -split "`n")) {
    if ($line) { $parts = $line -split "`t", 2; $snap[$parts[0]] = $parts[1] }
}
Write-Output "snapshot settings: $($snap.Count)/4 claves preservadas"
$hadLogoFile = $false
$snapLogoCopy = $null
$hadLogoWebp = $false
$snapLogoWebpCopy = $null
if ($snap.ContainsKey('site_logo') -and $snap['site_logo'] -ne '') {
    $src = Join-Path $root ('storage\app\public\' + ($snap['site_logo'] -replace '/', '\'))
    if (Test-Path $src) { $snapLogoCopy = Join-Path $tmp 'e2e-logo-orig'; Copy-Item $src $snapLogoCopy -Force; $hadLogoFile = $true }
    $srcWp = [System.IO.Path]::ChangeExtension($src, 'webp')
    if (Test-Path $srcWp) { $snapLogoWebpCopy = Join-Path $tmp 'e2e-logo-orig.webp'; Copy-Item $srcWp $snapLogoWebpCopy -Force; $hadLogoWebp = $true }
}

Write-Output '--- 1. Rutas y permisos ---'
$r = Invoke-WebRequest "$base/admin/configuracion" -UseBasicParsing
Check ($r.BaseResponse.ResponseUri.AbsoluteUri -match '/admin/login') 'sin sesion -> login' "no redirige a login ($($r.BaseResponse.ResponseUri.AbsoluteUri))"
Check ($r.Content -match 'Acceso administrativo') 'renderiza formulario de login' 'sin formulario de login'
$super = Login 'admin@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/configuracion" -WebSession $super -UseBasicParsing
Check ($r.StatusCode -eq 200) 'superadmin GET 200' "GET $($r.StatusCode)"
foreach ($f in @('site_name', 'logo', 'nav_funcionarios_url', 'nav_jubilados_url', 'remove_logo')) {
    if ($f -eq 'remove_logo') { continue }
    Check ($r.Content -match "name=`"$f`"") "input $f" "falta input $f"
}
Check ($r.Content -match '<h1>Identidad / Enlaces') 'titulo Identidad / Enlaces' 'sin titulo'
Check ($r.Content -match 'Identidad del sitio') 'card Identidad' 'sin card Identidad'
Check ($r.Content -match 'Enlaces de') 'card Enlaces' 'sin card Enlaces'
foreach ($pair in @(@('admin2@gdc.gob.ve', 'admin'), @('editor@gdc.gob.ve', 'editor'))) {
    $s2 = Login $pair[0]
    try { Invoke-WebRequest "$base/admin/configuracion" -WebSession $s2 -UseBasicParsing -ErrorAction Stop | Out-Null; Bad "$($pair[1]) acceso inesperado" }
    catch { $sc = [int]$_.Exception.Response.StatusCode; Check ($sc -eq 403) "$($pair[1]) -> 403" "$($pair[1]) -> $sc" }
}
$r = Invoke-WebRequest "$base/admin" -WebSession $super -UseBasicParsing
Check ($r.Content -match '/admin/configuracion') 'superadmin ve item sidebar' 'superadmin sin item sidebar'
$adm = Login 'admin2@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin" -WebSession $adm -UseBasicParsing
Check (-not ($r.Content -match '/admin/configuracion')) 'admin NO ve item sidebar' 'admin ve item Configuracion'

Write-Output '--- 2. Guardar nombre y enlaces (URLs absolutas) ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = 'https://intranet.example.gob.ve/'; nav_jubilados_url = 'https://jubilados.example.gob.ve/' }
Check ($r.StatusCode -eq 200) 'PUT 200' "PUT $($r.StatusCode)"
Check ($r.Content -match 'actualizada correctamente') 'alerta de exito' 'sin alerta de exito'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''site_name'';') -join ''
Check ($v -eq 'Portal GDC E2E') "site_name guardado (got $v)" 'site_name no guardado'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq 'https://intranet.example.gob.ve/') "nav_funcionarios guardado (got $v)" 'nav_funcionarios no guardado'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_jubilados_url'';') -join ''
Check ($v -eq 'https://jubilados.example.gob.ve/') "nav_jubilados guardado (got $v)" 'nav_jubilados no guardado'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.Content -match '<title>Inicio - Portal GDC E2E</title>') 'titulo home usa site_name' 'titulo home no usa site_name'
Check ($r.Content -match 'href="https://intranet.example.gob.ve/"') 'navbar muestra Funcionarios' 'navbar sin Funcionarios'
Check ($r.Content -match 'href="https://jubilados.example.gob.ve/">Jubilados</a>') 'navbar muestra Jubilados' 'navbar sin Jubilados'
Check ($r.Content -match 'target="_blank" rel="noopener"') 'enlaces target blank' 'sin target blank'
$r = Invoke-WebRequest "$base/admin/login" -UseBasicParsing
Check ($r.Content -match '<title>Admin - Portal GDC E2E</title>') 'titulo login usa site_name' 'titulo login no usa site_name'
$r = Invoke-WebRequest "$base/admin/configuracion" -WebSession $super -UseBasicParsing
Check ($r.Content -match '<title>Admin - Portal GDC E2E</title>') 'titulo panel usa site_name' 'titulo panel no usa site_name'

Write-Output '--- 3. Campo vacio oculta el item del navbar ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = 'https://intranet.example.gob.ve/'; nav_jubilados_url = '' }
Check ($r.StatusCode -eq 200) 'PUT 200' "PUT $($r.StatusCode)"
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_jubilados_url'';') -join ''
Check ($v -eq '') "nav_jubilados vacio guardado (got $v)" 'nav_jubilados no vacio'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.Content -match 'href="https://intranet.example.gob.ve/">Funcionarios Activos</a>') 'Funcionarios visible' 'Funcionarios oculto'
Check (-not ($r.Content -match '>Jubilados</a>')) 'Jubilados oculto' 'Jubilados aun visible'
Check ($r.Content -match 'Intranet GDC') 'dropdown Intranet GDC presente' 'sin dropdown Intranet GDC'

Write-Output '--- 4. Validacion: URL invalida rechazada ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = 'javascript:alert(1)'; nav_jubilados_url = '' }
Check ($r.StatusCode -eq 200) 'redirige con errores (200)' "status $($r.StatusCode)"
Check ($r.Content -match 'is-invalid' -or $r.Content -match 'invalid-feedback') 'campo en error' 'sin validacion visible'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq 'https://intranet.example.gob.ve/') "BD intacta (got $v)" 'BD alterada'

Write-Output '--- 5. Validacion: ruta sin "/" rechazada ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = 'documentos/algo.pdf'; nav_jubilados_url = '' }
Check ($r.Content -match 'debe comenzar con') 'mensaje de error' 'sin mensaje de error'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq 'https://intranet.example.gob.ve/') "BD intacta (got $v)" 'BD alterada'

Write-Output '--- 6. Validacion: ruta inexistente rechazada ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = '/documentos/NOEXISTE-E2E.pdf'; nav_jubilados_url = '' }
Check ($r.Content -match 'El archivo o directorio no existe') 'mensaje archivo inexistente' 'sin mensaje de archivo inexistente'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq 'https://intranet.example.gob.ve/') "BD intacta (got $v)" 'BD alterada'

Write-Output '--- 7. Validacion: ruta con ".." rechazada ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = '/images/../.env'; nav_jubilados_url = '' }
Check ($r.Content -match 'no puede contener') 'mensaje de traversal' 'sin mensaje de traversal'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq 'https://intranet.example.gob.ve/') "BD intacta (got $v)" 'BD alterada'

Write-Output '--- 8. Ruta valida del servidor aceptada ---'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = '/images/logo.png'; nav_jubilados_url = '' }
Check ($r.Content -match 'actualizada correctamente') 'guardado OK' 'no guardo'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''nav_funcionarios_url'';') -join ''
Check ($v -eq '/images/logo.png') "nav_funcionarios=/images/logo.png (got $v)" 'valor no guardado'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.Content -match 'href="/images/logo.png">Funcionarios Activos</a>') 'navbar con ruta relativa' 'navbar sin ruta relativa'

Write-Output '--- 9. site_name obligatorio ---'
$r = Put-Settings $super @{ site_name = ''; nav_funcionarios_url = '/images/logo.png'; nav_jubilados_url = '' }
Check ($r.Content -match 'is-invalid' -or $r.Content -match 'invalid-feedback') 'campo site_name en error' 'sin validacion en site_name'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''site_name'';') -join ''
Check ($v -eq 'Portal GDC E2E') "site_name conservado (got $v)" 'site_name alterado'

Write-Output '--- 10. Logo: subir, previsualizar, quitar ---'
$png = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
$logoPath = Join-Path $tmp 'e2e-logo.png'
[System.IO.File]::WriteAllBytes($logoPath, $png)
$r = Send-Multipart "$base/admin/configuracion" @{ _token = (Get-Token (Invoke-WebRequest "$base/admin/configuracion" -WebSession $super -UseBasicParsing).Content); _method = 'PUT'; site_name = 'Portal GDC E2E'; nav_funcionarios_url = '/images/logo.png'; nav_jubilados_url = '' } @{ logo = $logoPath } $super
Check ($r.StatusCode -eq 200) 'upload 200' "upload $($r.StatusCode)"
Check ($r.Content -match 'actualizada correctamente') 'upload guardado' 'sin alerta de exito'
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''site_logo'';') -join ''
Check ($v -eq 'settings/logo.png') "site_logo=settings/logo.png (got $v)" 'site_logo mal guardado'
$logoFile = Join-Path $root 'storage\app\public\settings\logo.png'
Check (Test-Path $logoFile) 'archivo logo.png en disco' 'archivo no existe en disco'
$logoWebp = Join-Path $root 'storage\app\public\settings\logo.webp'
Check (Test-Path $logoWebp) 'sidecar logo.webp generado en upload' 'falta sidecar logo.webp'
$r2 = Invoke-WebRequest "$base/storage/settings/logo.png" -UseBasicParsing
Check ($r2.StatusCode -eq 200) 'GET /storage/settings/logo.png -> 200' "GET $($r2.StatusCode)"
Check ($r2.Headers['Content-Type'] -match 'image') 'content-type image' "content-type $($r2.Headers['Content-Type'])"
try { $r3 = Invoke-WebRequest "$base/storage/settings/logo.webp" -UseBasicParsing; Check ($r3.StatusCode -eq 200) 'GET /storage/settings/logo.webp -> 200' "GET webp $($r3.StatusCode)" }
catch { Bad "GET /storage/settings/logo.webp fallo: $($_.Exception.Message)" }
$r = Invoke-WebRequest "$base/admin/configuracion" -WebSession $super -UseBasicParsing
Check ($r.Content -match 'storage/settings/logo.png') 'panel previsualiza logo nuevo' 'panel no previsualiza logo'
Check ($r.Content -match 'Quitar logo actual') 'checkbox quitar logo' 'sin checkbox quitar logo'
$r = Put-Settings $super @{ site_name = 'Portal GDC E2E'; nav_funcionarios_url = '/images/logo.png'; nav_jubilados_url = ''; remove_logo = '1' }
Check ($r.StatusCode -eq 200) 'PUT remove 200' "PUT $($r.StatusCode)"
$v = (Sql 'SELECT value FROM portal2026.settings WHERE `key`=''site_logo'';') -join ''
Check ($v -eq '') "site_logo vaciado (got $v)" 'site_logo no vaciado'
Check (-not (Test-Path $logoFile)) 'archivo logo.png borrado del disco' 'archivo logo.png sigue en disco'
Check (-not (Test-Path $logoWebp)) 'sidecar logo.webp borrado del disco' 'sidecar logo.webp sigue en disco'
$r = Invoke-WebRequest "$base/admin/configuracion" -WebSession $super -UseBasicParsing
Check ($r.Content -match 'Logo por defecto') 'panel vuelve a logo por defecto' 'sin indicador de logo por defecto'
Check (-not ($r.Content -match 'Quitar logo actual')) 'checkbox quitar logo oculto' 'checkbox quitar logo aun visible'

Write-Output '--- 11. Titulos y smoke ---'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.Content -match '<title>Inicio - Portal GDC E2E</title>') 'titulo home usa site_name' 'titulo home malo'
try { Invoke-WebRequest "$base/NOEXISTE-E2E-XYZ" -UseBasicParsing | Out-Null; Bad '404 no devuelto' }
catch {
    $sr = New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())
    $html = $sr.ReadToEnd()
    Check ($html -match '<title>Error 404 - Portal GDC E2E</title>') 'titulo 404 usa site_name' 'titulo 404 no usa site_name'
}
foreach ($p in @('/', '/noticias', '/contacto', '/gacetas', '/entes')) {
    try { $r2 = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 15; Check ($r2.StatusCode -eq 200) "$p 200" "$p $($r2.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output '--- 12. Permisos en BD ---'
$c = (Sql 'SELECT COUNT(*) FROM portal2026.permissions;') -join ''
Check ($c -eq '40') "40 permisos (got $c)" "permisos != 40 (got $c)"
$c = (Sql 'SELECT COUNT(*) FROM portal2026.role_permission;') -join ''
Check ($c -eq '67') "67 asignaciones 40+22+5 (got $c)" "asignaciones != 67 (got $c)"
$c = (Sql 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug=''settings.manage'' AND `group` LIKE ''Configuraci%Portal'';') -join ''
Check ($c -eq '1') "settings.manage en grupo Configuracion Portal (got $c)" "settings.manage mal ubicado (got $c)"
$c = (Sql 'SELECT COUNT(DISTINCT `group`) FROM portal2026.permissions;') -join ''
Check ($c -eq '3') "3 grupos de permisos = secciones sidebar (got $c)" "grupos != 3 (got $c)"
$c = (Sql 'SELECT COUNT(DISTINCT `module`) FROM portal2026.permissions;') -join ''
Check ($c -eq '15') "15 modulos = items sidebar (got $c)" "modulos != 15 (got $c)"
$c = (Sql 'SELECT COUNT(*) FROM portal2026.permissions WHERE `module` IS NULL OR `module`='''';') -join ''
Check ($c -eq '0') "0 permisos sin modulo (got $c)" "permisos sin modulo: $c"
$c = (Sql 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug=''settings.manage'' AND name=''Editar identidad y enlaces'';') -join ''
Check ($c -eq '1') "permiso settings.manage renombrado (got $c)" "settings.manage nombre viejo"
$r = Invoke-WebRequest "$base/admin/roles" -WebSession $super -UseBasicParsing
Check ($r.StatusCode -eq 200) '/admin/roles 200' "roles $($r.StatusCode)"
$n = ([regex]::Matches($r.Content, 'perm-module')).Count
Check ($n -eq 15) "vista: 15 cabeceras de modulo (got $n)" "vista: cabeceras modulo = $n"
Check ($r.Content -match 'Editar identidad y enlaces') 'vista: permiso renombrado visible' 'vista sin permiso renombrado'
$roles = (Sql 'SELECT rp.role FROM portal2026.role_permission rp JOIN portal2026.permissions p ON p.id = rp.permission_id WHERE p.slug=''settings.manage'';') -join ','
Check ($roles -eq 'superadmin') "solo superadmin (got $roles)" "roles inesperados: $roles"

Write-Output '--- 13. Restaurar snapshot ---'
foreach ($k in $keys) {
    if ($snap.ContainsKey($k)) {
        $val = $snap[$k] -replace "'", "''"
        Sql ('UPDATE portal2026.settings SET value=''' + $val + ''' WHERE `key`=''' + $k + ''';') | Out-Null
    } else {
        Sql ('DELETE FROM portal2026.settings WHERE `key`=''' + $k + ''';') | Out-Null
    }
}
if ($hadLogoFile) {
    $dst = Join-Path $root ('storage\app\public\' + ($snap['site_logo'] -replace '/', '\'))
    New-Item -ItemType Directory -Force -Path (Split-Path $dst) | Out-Null
    Copy-Item $snapLogoCopy $dst -Force
    if ($hadLogoWebp) { Copy-Item $snapLogoWebpCopy ([System.IO.Path]::ChangeExtension($dst, 'webp')) -Force }
} else {
    $dir = Join-Path $root 'storage\app\public\settings'
    if (Test-Path $dir) { Get-ChildItem $dir -Filter 'logo.*' -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue }
}
$restored = $true
foreach ($k in $keys) {
    if ($snap.ContainsKey($k)) {
        $actual = (Sql ('SELECT value FROM portal2026.settings WHERE `key`=''' + $k + ''';')) -join ''
        if ($actual -ne $snap[$k]) { $restored = $false }
    } else {
        $rows = (Sql ('SELECT COUNT(*) FROM portal2026.settings WHERE `key`=''' + $k + ''';')) -join ''
        if ($rows -ne '0') { $restored = $false }
    }
}
Check $restored 'configuracion preexistente restaurada' 'NO se restauro la configuracion previa'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.StatusCode -eq 200) 'home 200 tras restaurar' "home $($r.StatusCode)"

Write-Output ''
if ($fail -eq 0) { Write-Output '=== CONFIGURACION GENERAL: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
