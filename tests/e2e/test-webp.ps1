$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$pub = Join-Path $root 'storage\app\public'
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($cond, $ok, $bad) { if ($cond) { Ok $ok } else { Bad $bad } }
function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'CSRF token no encontrado'
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
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }
function Sidecar($p) { [System.IO.Path]::ChangeExtension($p, 'webp') }

# --- Archivos de prueba ---
$png = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
$gif = [Convert]::FromBase64String('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
[System.IO.File]::WriteAllBytes("$tmp\webp-img.png", $png)
[System.IO.File]::WriteAllBytes("$tmp\webp-news.png", $png)
[System.IO.File]::WriteAllBytes("$tmp\webp-img.gif", $gif)

Write-Output '--- 1. Comando webp:generate (backfill) ---'
Push-Location -LiteralPath $root
$out1 = (& $php artisan webp:generate | Out-String).Trim()
$ec1 = $LASTEXITCODE
$out2 = (& $php artisan webp:generate | Out-String).Trim()
$ec2 = $LASTEXITCODE
Pop-Location
Check ($ec1 -eq 0) 'webp:generate exit 0' "exit $ec1 (salida: $out1)"
Check ($out1 -match 'WebP: \d+ convertidos') "resumen corrida 1 ($out1)" 'sin resumen de corrida 1'
Check ($ec2 -eq 0) 'webp:generate 2a corrida exit 0' "exit $ec2"
Check ($out2 -match 'WebP: 0 convertidos') "2a corrida 0 convertidos ($out2)" '2a corrida reconvirtio (no idempotente)'
Check ($out2 -match '\d+ ya existentes') '2a corrida reporta ya existentes' 'sin contador de existentes'

Write-Output '--- 2. Login ---'
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=admin%40gdc.gob.ve&password=password&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing
if ($r.Content -notmatch 'Admin GDC') { throw 'Login fallido' }
Ok 'LOGIN superadmin'

Write-Output '--- 3. Upload a biblioteca genera sidecar .webp ---'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/medios" @{ _token = $token } @{ 'files[]' = "$tmp\webp-img.png" } $sess
Check ($r.StatusCode -in 200, 302) "POST upload -> $($r.StatusCode)" "upload fallo: $($r.StatusCode)"
$pathA = (Sql "SELECT path FROM portal2026.media WHERE original_name='webp-img.png' ORDER BY id DESC LIMIT 1;")
$pathA = "$pathA".Trim()
Check ($pathA -like 'uploads/*') "path media=$pathA" 'upload no guardo path'
Check (Test-Path "$pub\$pathA") 'original en disco' 'original no existe'
Check (Test-Path (Sidecar "$pub\$pathA")) 'sidecar .webp creado en upload' 'NO se creo sidecar webp'
$webpRel = "$pathA" -replace '\.[^.]+$', '.webp'
try {
    $r2 = Invoke-WebRequest "$base/storage/$webpRel" -UseBasicParsing
    Check ($r2.StatusCode -eq 200) 'GET /storage/*.webp -> 200' "GET webp $($r2.StatusCode)"
    Check ($r2.Headers['Content-Type'] -match 'image/webp') 'content-type image/webp' "content-type $($r2.Headers['Content-Type'])"
} catch {
    Bad "GET /storage/*.webp fallo: $($_.Exception.Message)"
}
try {
    $r2 = Invoke-WebRequest "$base/storage/$pathA" -UseBasicParsing
    Check ($r2.StatusCode -eq 200) 'GET original -> 200 (fallback disponible)' "GET original $($r2.StatusCode)"
} catch {
    Bad "GET original fallo: $($_.Exception.Message)"
}

Write-Output '--- 4. GIF no genera sidecar (solo jpg/png) ---'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/medios" @{ _token = $token } @{ 'files[]' = "$tmp\webp-img.gif" } $sess
$pathC = (Sql "SELECT path FROM portal2026.media WHERE original_name='webp-img.gif' ORDER BY id DESC LIMIT 1;")
$pathC = "$pathC".Trim()
Check ($pathC -like 'uploads/*') "path gif=$pathC" 'gif no subio'
Check (Test-Path "$pub\$pathC") 'gif en disco' 'gif no existe en disco'
Check (-not (Test-Path (Sidecar "$pub\$pathC"))) 'gif NO genera sidecar webp' 'gif genero sidecar (mal)'

Write-Output '--- 4b. PNG corrupto: sin sidecar y webp:generate no falla ---'
$badPng = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC')
[System.IO.File]::WriteAllBytes("$tmp\webp-corrupt.png", $badPng)
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/medios" @{ _token = $token } @{ 'files[]' = "$tmp\webp-corrupt.png" } $sess
$pathD = (Sql "SELECT path FROM portal2026.media WHERE original_name='webp-corrupt.png' ORDER BY id DESC LIMIT 1;")
$pathD = "$pathD".Trim()
Check ($pathD -like 'uploads/*') "path png corrupto=$pathD" 'png corrupto no subio'
Check (Test-Path "$pub\$pathD") 'png corrupto en disco' 'png corrupto no existe'
Check (-not (Test-Path (Sidecar "$pub\$pathD"))) 'png corrupto NO genera sidecar' 'corrupto genero sidecar (mal)'
Push-Location -LiteralPath $root
$out3 = (& $php artisan webp:generate | Out-String).Trim()
$ec3 = $LASTEXITCODE
Pop-Location
Check ($ec3 -eq 0) 'webp:generate exit 0 con png corrupto presente' "exit $ec3 (salida: $out3)"
Check ($out3 -match ', [1-9]\d* ilegibles, 0 fallidos') "contador ilegibles ($out3)" 'sin ilegibles o con fallidos'

Write-Output '--- 5. Noticia destacada renderiza <picture> con fallback ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/noticias" @{
    _token = $token; title = 'E2E WebP Destacada'; category_id = '1'
    excerpt = 'Extracto WebP E2E'; content = '<p>Contenido WebP E2E</p>'; status = 'published'
} @{ featured_image_file = "$tmp\webp-news.png" } $sess
Check ($r.StatusCode -in 200, 302) "POST noticia -> $($r.StatusCode)" "noticia fallo: $($r.StatusCode)"
$fi = (Sql "SELECT featured_image FROM portal2026.posts WHERE title='E2E WebP Destacada';")
$fi = "$fi".Trim()
Check ($fi -like 'uploads/*') "featured_image=$fi" 'noticia sin imagen'
Check (Test-Path (Sidecar "$pub\$fi")) 'sidecar de la imagen destacada' 'falta sidecar de destacada'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.StatusCode -eq 200) '/noticias 200' "status $($r.StatusCode)"
Check ($r.Content -match '<picture>') '/noticias usa <picture>' 'sin <picture> en /noticias'
$fiWebp = "$fi" -replace '\.[^.]+$', '.webp'
Check ($r.Content -match ('<source srcset="[^"]*' + [regex]::Escape($fiWebp) + '" type="image/webp">')) 'source webp apunta al sidecar' 'falta source webp correcto'
Check ($r.Content -match ('<img src="[^"]*' + [regex]::Escape($fi) + '"')) 'img conserva src original (fallback)' 'img no conserva el original'
Check ($r.Content -match 'card-img-top[^>]*loading="lazy"') 'card con lazy loading' 'card sin lazy'

Write-Output '--- 6. Destruir media borra original + sidecar ---'
$idA = (Sql "SELECT id FROM portal2026.media WHERE path='$pathA';")
$idA = "$idA".Trim()
Check ($idA -match '^\d+$') "media id=$idA" 'id de media invalido'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
Invoke-WebRequest "$base/admin/medios/$idA" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
Check (-not (Test-Path "$pub\$pathA")) 'original borrado del disco' 'original quedo en disco'
Check (-not (Test-Path (Sidecar "$pub\$pathA"))) 'sidecar borrado del disco' 'sidecar quedo en disco'
$c = (Sql "SELECT COUNT(*) FROM portal2026.media WHERE path='$pathA';")
$c = "$c".Trim()
Check ($c -eq '0') 'fila media eliminada' "fila aun existe: $c"

Write-Output '--- 7. CSS construido incluye picture{display:contents} ---'
$manifest = Get-Content "$root\public\build\manifest.json" -Raw | ConvertFrom-Json
$cssFile = $manifest.'resources/css/app.scss'.file
Check ($cssFile) 'manifest registra resources/css/app.scss' 'clave ausente en manifest'
if ($cssFile) {
    $cssContent = Get-Content (Join-Path "$root\public\build" $cssFile) -Raw
    Check ($cssContent -match 'picture\{display:contents\}') 'build CSS contiene picture{display:contents}' 'regla ausente (falta npm run build)'
}

Write-Output '--- 8. Smoke publico con <picture> ---'
foreach ($u in @('/', '/noticias', '/entes', '/gacetas')) {
    try { $r2 = Invoke-WebRequest "$base$u" -UseBasicParsing -TimeoutSec 15; Check ($r2.StatusCode -eq 200) "$u 200" "$u $($r2.StatusCode)" }
    catch { Bad "$u ERROR" }
}

Write-Output '--- 9. Cleanup ---'
$pidW = (Sql "SELECT id FROM portal2026.posts WHERE title='E2E WebP Destacada';")
$pidW = "$pidW".Trim()
if ($pidW -match '^\d+$') {
    Invoke-WebRequest "$base/admin/noticias/$pidW" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
    Sql "DELETE FROM portal2026.posts WHERE title='E2E WebP Destacada';" | Out-Null
    $cnt = (Sql "SELECT COUNT(*) FROM portal2026.posts WHERE title='E2E WebP Destacada';")
    Check ("$cnt".Trim() -eq '0') 'post de prueba purgado' "restos: $cnt"
}
$leftover = @()
foreach ($row in ((Sql "SELECT path FROM portal2026.media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');") -split "`n")) {
    $row = "$row".Trim()
    if ($row) { $leftover += $row }
}
foreach ($p in $leftover) {
    if (Test-Path "$pub\$p") { Remove-Item "$pub\$p" -Force; Ok "borrado disco: $p" }
    $wp = Sidecar "$pub\$p"
    if (Test-Path $wp) { Remove-Item $wp -Force; Ok "borrado disco sidecar: $(Split-Path $wp -Leaf)" }
}
Sql "DELETE FROM portal2026.media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');" | Out-Null
$c = (Sql "SELECT COUNT(*) FROM portal2026.media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');")
Check ("$c".Trim() -eq '0') 'media de prueba limpia' "restos media: $c"
Remove-Item "$tmp\webp-img.png", "$tmp\webp-news.png", "$tmp\webp-img.gif", "$tmp\webp-corrupt.png" -Force -ErrorAction SilentlyContinue

Write-Output ''
if ($fail -eq 0) { Write-Output '=== WEBP: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== WEBP: $fail FALLAS ==="; exit 1 }
