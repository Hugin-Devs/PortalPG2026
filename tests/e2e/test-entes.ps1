$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'CSRF token no encontrado'
}
function Q($sql) { & $mysql -uroot -N -e $sql 2>&1 }

# --- Login ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$body = "email=admin%40gdc.gob.ve&password=password&_token=$([uri]::EscapeDataString($token))"
Invoke-WebRequest "$base/admin/login" -Method Post -Body $body -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
Write-Output 'LOGIN OK'

# --- 1. /entes vacio: estado vacio + nav activo ---
$r = Invoke-WebRequest "$base/entes" -UseBasicParsing
if ($r.Content -notmatch 'Pr') { throw 'falta estado vacio' }
if ($r.Content -notmatch '<a class="nav-link active" href="[^"]*">Entes</a>') { throw 'nav Entes NO activo en /entes' }
Write-Output 'VACIO + NAV ACTIVO OK'

# --- 2. Crear ente A (con url) y B (sin url) desde el panel ---
foreach ($p in @(
    @{ name = 'Ente Demo A'; desc = 'Descripcion de prueba A.'; url = 'https://example.gob.ve' },
    @{ name = 'Ente Demo B'; desc = 'Descripcion de prueba B.'; url = '' }
)) {
    $r = Invoke-WebRequest "$base/admin/entes/crear" -WebSession $sess -UseBasicParsing
    $token = Get-Token $r.Content
    $form = "_token=$([uri]::EscapeDataString($token))&name=$([uri]::EscapeDataString($p.name))&description=$([uri]::EscapeDataString($p.desc))&url=$([uri]::EscapeDataString($p.url))&order=1"
    Invoke-WebRequest "$base/admin/entes" -Method Post -Body $form -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
}
$rowA = Q "SELECT slug, description FROM portal2026.partners WHERE name='Ente Demo A';"
$rowB = Q "SELECT slug FROM portal2026.partners WHERE name='Ente Demo B';"
Write-Output "A: $rowA"
Write-Output "B: $rowB"
if ($rowA -notlike "*ente-demo-a*") { throw 'slug A mal' }
if ($rowB -ne 'ente-demo-b') { throw 'slug B mal' }
Write-Output 'CREAR + SLUG OK'

# --- 3. Listado publico: cards con descripcion ---
$r = Invoke-WebRequest "$base/entes" -UseBasicParsing
if ($r.Content -notmatch 'Ente Demo A' -or $r.Content -notmatch 'Descripcion de prueba A') { throw 'card A no muestra nombre/descripcion' }
if ($r.Content -notmatch 'Ir al sitio') { throw 'falta boton Ir al sitio (ente con url)' }
if ($r.Content -notmatch 'href="[^"]*entes/ente-demo-b"') { throw 'falta link Ver mas al detalle (ente sin url)' }
Write-Output 'LISTADO OK'

# --- 4. Detalle ---
$r = Invoke-WebRequest "$base/entes/ente-demo-a" -UseBasicParsing
if ($r.Content -notmatch 'Descripcion de prueba A') { throw 'detalle A sin descripcion' }
$r = Invoke-WebRequest "$base/entes/ente-demo-b" -UseBasicParsing
if ($r.Content -notmatch 'Ente Demo B') { throw 'detalle B fallo' }
try { Invoke-WebRequest "$base/entes/no-existe" -UseBasicParsing -ErrorAction Stop; throw 'debio dar 404' } catch { if ($_.Exception.Response.StatusCode.value__ -ne 404) { throw } }
Write-Output 'DETALLE + 404 OK'

# --- 5. /servicios redirige a /entes ---
$r = Invoke-WebRequest "$base/servicios" -UseBasicParsing -MaximumRedirection 5
if ($r.BaseResponse.ResponseUri.AbsolutePath -ne '/entes') { throw "redirect fallo: $($r.BaseResponse.ResponseUri.AbsolutePath)" }
Write-Output 'REDIRECT /servicios -> /entes OK'

# --- 6. Renombrar ente B: cambia slug ---
$r = Invoke-WebRequest "$base/admin/entes" -WebSession $sess -UseBasicParsing
$idB = Q "SELECT id FROM portal2026.partners WHERE slug='ente-demo-b';"
$r = Invoke-WebRequest "$base/admin/entes/$idB/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$form = "_token=$([uri]::EscapeDataString($token))&_method=PUT&name=Ente Demo B Renombrado&description=Descripcion de prueba B.&order=1&is_active=1"
Invoke-WebRequest "$base/admin/entes/$idB" -Method Post -Body $form -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
$slugB = Q "SELECT slug FROM portal2026.partners WHERE id=$idB;"
Write-Output "slug B tras renombrar: $slugB"
if ($slugB -ne 'ente-demo-b-renombrado') { throw 'slug no se actualizo' }
$r = Invoke-WebRequest "$base/entes/ente-demo-b-renombrado" -UseBasicParsing
Write-Output 'RENOMBRAR -> SLUG OK'

# --- 7. Nav activo en /noticias y en home ---
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
if ($r.Content -notmatch '<a class="nav-link active" href="[^"]*">Noticias</a>') { throw 'nav Noticias NO activo' }
if ($r.Content -match '<a class="nav-link active" href="[^"]*">Entes</a>') { throw 'nav Entes activo indebidamente en /noticias' }
Write-Output 'NAV ACTIVO OK'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
if ($r.Content -notmatch '<a class="nav-link active" href="[^"]*"[^>]*>Inicio</a>') {
    if ($r.Content -notmatch 'nav-link active') { throw 'ningun nav activo en home' } else { Write-Output 'AVISO: revisar cual link esta activo en home' }
} else { Write-Output 'HOME OK' }

# --- 8. Limpieza ---
$ids = Q "SELECT id FROM portal2026.partners WHERE name LIKE 'Ente Demo%';"
foreach ($id in ($ids -split "`n")) {
    if ($id) {
        $form = "_method=DELETE&_token=$([uri]::EscapeDataString($token))"
        Invoke-WebRequest "$base/admin/entes/$id" -Method Post -Body $form -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
    }
}
$restan = Q "SELECT COUNT(*) FROM portal2026.partners WHERE name LIKE 'Ente Demo%';"
Write-Output "limpieza: quedan $restan (esperado 0)"
Write-Output '=== TODAS LAS PRUEBAS OK ==='
