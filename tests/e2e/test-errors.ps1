$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) { if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }; throw 'token no encontrado' }

function Invoke-Error($url, $method, $body, $contentType, $sess) {
    try {
        $p = @{ Uri = $url; Method = $method; UseBasicParsing = $true; TimeoutSec = 15; ErrorAction = 'Stop' }
        if ($body) { $p.Body = $body; $p.ContentType = $contentType }
        if ($sess) { $p.WebSession = $sess }
        $r = Invoke-WebRequest @p
        $sc = [int]$r.StatusCode
        $html = $r.Content
    } catch {
        $resp = $_.Exception.Response
        if (-not $resp) { return @{ Status = 0; Html = $_.Exception.Message } }
        $sc = [int]$resp.StatusCode
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream(), [System.Text.Encoding]::UTF8)
        $html = $reader.ReadToEnd()
        $reader.Close()
    }
    @{ Status = $sc; Html = $html }
}

Write-Output '--- 1. 404 (pagina inexistente) ---'
$r = Invoke-Error "$base/pagina-que-no-existe-xyz" 'GET' $null $null $null
Check ($r.Status -eq 404) "status 404 (got $($r.Status))" "status != 404"
Check ($r.Html -match 'P.gina no encontrada') 'titulo en espanol' 'titulo no encontrado'
Check ($r.Html -match '>Volver<') 'boton Volver' 'sin boton Volver'
Check ($r.Html -match 'Ir al inicio') 'boton Ir al inicio' 'sin boton inicio'

Write-Output '--- 2. 403 (editor en /admin/contacto) ---'
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r0 = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
$t = Get-Token $r0.Content
Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=editor%40gdc.gob.ve&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
$r = Invoke-Error "$base/admin/contacto" 'GET' $null $null $sess
Check ($r.Status -eq 403) "status 403 (got $($r.Status))" "status != 403"
Check ($r.Html -match 'Acceso denegado') 'titulo en espanol' 'titulo no encontrado'
Check ($r.Html -match '>Volver<') 'boton Volver' 'sin boton Volver'
Check ($r.Html -match 'Ir al panel') 'boton Ir al panel (auth)' 'sin boton panel'

Write-Output '--- 3. 405 (DELETE a GET/POST route) ---'
$r = Invoke-Error "$base/contacto" 'DELETE' $null $null $null
Check ($r.Status -eq 405) "status 405 (got $($r.Status))" "status != 405"
Check ($r.Html -match 'M&eacute;todo no permitido|M.todo no permitido') 'titulo en espanol' 'titulo no encontrado'
Check ($r.Html -match '>Volver<') 'boton Volver' 'sin boton Volver'

Write-Output '--- 4. 419 (POST con token invalido) ---'
$cntBefore = & $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.contacts;' 2>&1
$r = Invoke-Error "$base/contacto" 'POST' '_token=token-invalido-prueba&name=X&email=x@x.com&subject=X&message=X' 'application/x-www-form-urlencoded' $null
Check ($r.Status -eq 419) "status 419 (got $($r.Status))" "status != 419"
Check ($r.Html -match 'Sesi.n expirada') 'titulo en espanol' 'titulo no encontrado'
Check ($r.Html -match 'Recargar e intentar') 'boton Recargar' 'sin boton recargar'
Check ($r.Html -match '>Volver<') 'boton Volver' 'sin boton Volver'
$cntAfter = & $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.contacts;' 2>&1
Check ($cntBefore -eq $cntAfter) 'contacts intacto (no escribio)' 'contacts cambio'

Write-Output '--- 5. Render PHP de las 12 vistas ---'
& $php "$PSScriptRoot\render_errors.php" 2>&1 | ForEach-Object { "  $_" }
if ($LASTEXITCODE -ne 0) { $fail++ }

Write-Output '--- 6. 503 real via maintenance mode ---'
try {
    & $php artisan down --no-interaction 2>&1 | Out-Null
    $r = Invoke-Error "$base/" 'GET' $null $null $null
    Check ($r.Status -eq 503) "status 503 (got $($r.Status))" "status != 503"
    Check ($r.Html -match 'Servicio no disponible') 'titulo en espanol' 'titulo no encontrado'
    Check ($r.Html -match 'Reintentar') 'boton Reintentar' 'sin boton reintentar'
    Check ($r.Html -match 'Ir al inicio') 'boton Ir al inicio' 'sin boton inicio'
} finally {
    & $php artisan up --no-interaction 2>&1 | Out-Null
}

Write-Output '--- 7. Smoke (site de vuelta) ---'
foreach ($p in @('/', '/contacto', '/noticias')) {
    try { $r2 = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r2.StatusCode -eq 200) "$p 200" "$p $($r2.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== ERRORES: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
