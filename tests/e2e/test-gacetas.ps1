$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$pub = Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'storage\app\public'
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
        $path = $files[$key]
        $bytes = [System.IO.File]::ReadAllBytes($path)
        $ct = if ($path -like '*.pdf') { 'application/pdf' } else { 'image/png' }
        $head = "--$boundary`r`nContent-Disposition: form-data; name=`"$key`"; filename=`"$(Split-Path $path -Leaf)`"`r`nContent-Type: $ct`r`n`r`n"
        $b = $enc.GetBytes($head); $ms.Write($b, 0, $b.Length)
        $ms.Write($bytes, 0, $bytes.Length)
        $tail = "`r`n"; $b = $enc.GetBytes($tail); $ms.Write($b, 0, $b.Length)
    }
    $end = $enc.GetBytes("--$boundary--`r`n"); $ms.Write($end, 0, $end.Length)
    $body = $ms.ToArray(); $ms.Dispose()
    Invoke-WebRequest -Uri $url -Method Post -Body $body -ContentType "multipart/form-data; boundary=$boundary" -WebSession $sess -UseBasicParsing -MaximumRedirection 5
}
function Put-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    $body = "email=$([uri]::EscapeDataString($email))&password=$([uri]::EscapeDataString('password'))&_token=$([uri]::EscapeDataString($t))"
    Invoke-WebRequest "$base/admin/login" -Method Post -Body $body -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing | Out-Null
    $s
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

# --- Archivos de prueba ---
$png1 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
$png2 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC')
[System.IO.File]::WriteAllBytes("$tmp\gac-img1.png", $png1)
[System.IO.File]::WriteAllBytes("$tmp\gac-img2.png", $png2)
$pdfTxt = "%PDF-1.4`n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj`n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj`n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj`ntrailer<</Root 1 0 R>>`n%%EOF`n"
[System.IO.File]::WriteAllBytes("$tmp\gac-test1.pdf", [System.Text.Encoding]::ASCII.GetBytes($pdfTxt))
$pdfTxt2 = "%PDF-1.4`n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj`n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj`n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 300]>>endobj`ntrailer<</Root 1 0 R>>`n%%EOF`n"
[System.IO.File]::WriteAllBytes("$tmp\gac-test2.pdf", [System.Text.Encoding]::ASCII.GetBytes($pdfTxt2))
[System.IO.File]::WriteAllBytes("$tmp\gac-fake.txt", [System.Text.Encoding]::UTF8.GetBytes('hola'))

# --- Limpieza quirúrgica: SOLO artefactos de corridas anteriores de esta suite ---
# (nunca borrar gacetas reales del usuario)
$rows = (& $mysql -uroot -N -e "SELECT id, path FROM portal2026.gacetas WHERE original_name IN ('gac-test1.pdf','gac-test2.pdf','gac-img1.png','gac-img2.png');") -join "`n"
foreach ($row in ($rows -split "`n")) {
    if (-not $row) { continue }
    $rid = ($row -split "`t")[0]
    $rpath = ($row -split "`t")[1]
    if ($rpath -and (Test-Path "$pub\$rpath")) { Remove-Item "$pub\$rpath" -Force }
    $rwp = if ($rpath) { [System.IO.Path]::ChangeExtension("$pub\$rpath", 'webp') } else { $null }
    if ($rwp -and (Test-Path $rwp)) { Remove-Item $rwp -Force }
    & $mysql -uroot -N -e "DELETE FROM portal2026.gacetas WHERE id=$rid;" | Out-Null
}
Write-Output "pre-limpieza quirurgica: artefactos de la suite removidos (gacetas reales intactas)"
$baseCount = ((& $mysql -uroot -N -e 'SELECT COUNT(*) FROM portal2026.gacetas;') -join '').Trim()
Write-Output "gacetas preexistentes (se preservan): $baseCount"

Write-Output '--- 1. Login superadmin + navbar publico ---'
$sess = Login 'admin@gdc.gob.ve'
Ok 'LOGIN superadmin'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.Content -match 'Gacetas Oficiales') 'navbar publico tiene item Gacetas Oficiales' 'falta item en navbar'
$iG = $r.Content.IndexOf('Gacetas Oficiales'); $iS = $r.Content.IndexOf('Sobre Nosotros'); $iI = $r.Content.IndexOf('Intranet GDC')
Check (($iS -gt 0) -and ($iG -gt $iS) -and ($iI -gt $iG)) 'orden navbar: Sobre Nosotros < Gacetas < Intranet' "orden incorrecto (S=$iS G=$iG I=$iI)"

Write-Output '--- 2. Vista publica vacia ---'
$r = Invoke-WebRequest "$base/gacetas" -UseBasicParsing
Check ($r.StatusCode -eq 200) '/gacetas 200' "status $($r.StatusCode)"
Check ($r.Content -match 'Gacetas Oficiales</h1>') 'H1 Gacetas Oficiales' 'falta H1'
if ($baseCount -eq '0') {
    Check ($r.Content -match 'no hay gacetas publicadas') 'estado vacio presente' 'falta estado vacio'
} else {
    Check (-not ($r.Content -match 'no hay gacetas publicadas')) 'sin estado vacio (hay gacetas reales)' 'estado vacio incorrecto con gacetas presentes'
}

Write-Output '--- 3. Admin: formulario Nueva Gaceta ---'
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'Nueva Gaceta') 'formulario Nueva Gaceta' 'falta formulario'
Check ($r.Content -match 'name="file"') 'input file' 'falta input file'
Check ($r.Content -match 'name="numero"') 'input numero' 'falta input numero'
Check ($r.Content -match 'name="fecha"') 'input fecha' 'falta input fecha'
$token = Get-Token $r.Content

Write-Output '--- 4. Cargar gaceta PDF (#045) ---'
$r = Send-Multipart "$base/admin/gacetas" @{ _token = $token; numero = '045'; fecha = '2026-02-01' } @{ file = "$tmp\gac-test1.pdf" } $sess
Check ($r.BaseResponse.ResponseUri.AbsolutePath -eq '/admin/gacetas') 'redirect a listado' "uri: $($r.BaseResponse.ResponseUri.AbsolutePath)"
$row = Sql "SELECT id, path, mime_type FROM portal2026.gacetas WHERE numero='045';"
Check ($row -match 'gacetas/') "BD fila PDF ($row)" 'no se creo la fila PDF'
$path1 = ($row -split "`t")[1]
Check (Test-Path "$pub\$path1") 'archivo PDF en disco' 'archivo PDF no existe'
Check (-not (Test-Path ([System.IO.Path]::ChangeExtension("$pub\$path1", 'webp')))) 'PDF sin sidecar webp' 'PDF genero sidecar webp (mal)'

Write-Output '--- 5. Cargar gaceta imagen (#050) ---'
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/gacetas" @{ _token = $token; numero = '050'; fecha = '2026-03-01' } @{ file = "$tmp\gac-img1.png" } $sess
$row = Sql "SELECT path, mime_type FROM portal2026.gacetas WHERE numero='050';"
Check ($row -like 'gacetas/*' + "`t" + 'image/*') "BD fila imagen ($row)" 'no se creo la fila imagen'
$path2 = ($row -split "`t")[0]
Check (Test-Path "$pub\$path2") 'archivo imagen en disco' 'archivo imagen no existe'
Check (Test-Path ([System.IO.Path]::ChangeExtension("$pub\$path2", 'webp'))) 'webp generado para imagen' 'falta sidecar webp de la imagen'

Write-Output '--- 6. Vista publica: cards, orden y enlaces ---'
$r = Invoke-WebRequest "$base/gacetas" -UseBasicParsing
Check ($r.Content -match 'Gaceta Oficial N[^0-9]{1,6}050') 'titulo Gaceta Oficial N° 050' 'falta titulo 050'
Check ($r.Content -match 'Gaceta Oficial N[^0-9]{1,6}045') 'titulo Gaceta Oficial N° 045' 'falta titulo 045'
$i50 = $r.Content.IndexOf('050'); $i45 = $r.Content.IndexOf('045')
Check (($i50 -gt 0) -and ($i45 -gt $i50)) 'orden: 050 (fecha mas reciente) antes que 045' "orden incorrecto ($i50 vs $i45)"
Check ($r.Content -match 'target="_blank"') 'enlaces target _blank' 'falta target _blank'
Check ($r.Content -match [regex]::Escape("storage/$path1")) 'link al PDF' 'falta link al PDF'
Check ($r.Content -match 'fa-file-pdf') 'icono PDF para el PDF' 'falta icono PDF'
Check ($r.Content -match [regex]::Escape("storage/$path2")) 'miniatura de la imagen' 'falta miniatura imagen'
Check ($r.Content -match 'loading="lazy"') 'miniaturas con lazy loading' 'falta lazy'

Write-Output '--- 7. Editar: numero + fecha + reemplazar archivo ---'
$id1 = (Sql "SELECT id FROM portal2026.gacetas WHERE numero='045';")
$r = Invoke-WebRequest "$base/admin/gacetas/$id1/editar" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'value="045"') 'edit precarga numero' 'no precarga numero'
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/gacetas/$id1" @{
    _token = $token; _method = 'PUT'; numero = '046'; fecha = '2026-02-15'
} $sess
Check ($r.FinalUri -match '/admin/gacetas$') 'update sin archivo -> redirect' "uri: $($r.FinalUri)"
$row = Sql "SELECT numero, fecha FROM portal2026.gacetas WHERE id=$id1;"
Check ($row -like '046*2026-02-15') "BD actualizada ($row)" "BD no cambio: $row"
Check (Test-Path "$pub\$path1") 'archivo conservado al editar solo campos' 'archivo borrado (mal)'

$r = Invoke-WebRequest "$base/admin/gacetas/$id1/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/gacetas/$id1" @{ _token = $token; _method = 'PUT'; numero = '046'; fecha = '2026-02-15' } @{ file = "$tmp\gac-img2.png" } $sess
$row = Sql "SELECT path FROM portal2026.gacetas WHERE id=$id1;"
$path3 = ($row -split "`t")[0]
Check ($path3 -ne $path1) "archivo reemplazado ($path3)" 'path no cambio'
Check (-not (Test-Path "$pub\$path1")) 'archivo anterior borrado del disco' 'archivo anterior quedo en disco'
Check (Test-Path "$pub\$path3") 'nuevo archivo en disco' 'nuevo archivo no existe'

Write-Output '--- 8. Validaciones ---'
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/gacetas" @{ _token = $token; numero = '099'; fecha = '2026-04-01' } @{ file = "$tmp\gac-fake.txt" } $sess
$cnt = Sql "SELECT COUNT(*) FROM portal2026.gacetas WHERE numero='099';"
Check ($cnt -eq '0') 'archivo .txt rechazado' "se creo fila: $cnt"
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/gacetas" @{ _token = $token; numero = 'abc'; fecha = '2026-04-01' } @{ file = "$tmp\gac-test2.pdf" } $sess
$cnt = Sql "SELECT COUNT(*) FROM portal2026.gacetas WHERE numero='abc';"
Check ($cnt -eq '0') 'numero no numerico rechazado' "se creo fila: $cnt"
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/gacetas" @{ _token = $token; numero = '1234567'; fecha = '2026-04-01' } @{ file = "$tmp\gac-test2.pdf" } $sess
$cnt = Sql "SELECT COUNT(*) FROM portal2026.gacetas WHERE numero='1234567';"
Check ($cnt -eq '0') 'numero de 7 digitos rechazado' "se creo fila: $cnt"

Write-Output '--- 9. Permisos ---'
$editor = Login 'editor@gdc.gob.ve'
try { Invoke-WebRequest "$base/admin/gacetas" -WebSession $editor -UseBasicParsing -ErrorAction Stop | Out-Null; Bad 'editor NO recibio 403' } catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 403) { Ok 'editor recibe 403' } else { Bad "editor status $($_.Exception.Response.StatusCode.value__)" }
}
$admin2 = Login 'admin2@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $admin2 -UseBasicParsing
Check ($r.Content -match 'Nueva Gaceta') 'rol admin puede gestionar gacetas' 'rol admin no puede gestionar'
Check ($r.Content -match 'Gacetas</a>') 'sidebar admin con Gacetas' 'falta Gacetas en sidebar'
$pc = Sql 'SELECT COUNT(*) FROM portal2026.permissions;'
Check ($pc -eq '39') "permisos totales 39 ($pc)" "permisos: $pc"
$rc = Sql 'SELECT role, COUNT(*) FROM portal2026.role_permission GROUP BY role;'
Check (($rc -match 'admin') -and ($rc -match '21')) "reparto admin 21 ($($rc -replace "`n",' | '))" "reparto admin inesperado: $rc"

Write-Output '--- 10. Eliminar ---'
$r = Invoke-WebRequest "$base/admin/gacetas" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
foreach ($id in @($id1, (Sql "SELECT id FROM portal2026.gacetas WHERE numero='050';"))) {
    Invoke-WebRequest "$base/admin/gacetas/$id" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
}
$cnt = Sql 'SELECT COUNT(*) FROM portal2026.gacetas;'
$testCnt = (& $mysql -uroot -N -e "SELECT COUNT(*) FROM portal2026.gacetas WHERE original_name IN ('gac-test1.pdf','gac-test2.pdf','gac-img1.png','gac-img2.png');") -join ''
Check (($testCnt -eq '0') -and ($cnt -eq $baseCount)) "gacetas de la suite eliminadas; preexistentes intactas (total $cnt)" "restos: suite=$testCnt total=$cnt (esperado $baseCount)"
foreach ($p in @($path2, $path3)) {
    Check (-not (Test-Path "$pub\$p")) "archivo $p borrado del disco" "archivo $p quedo en disco"
    Check (-not (Test-Path ([System.IO.Path]::ChangeExtension("$pub\$p", 'webp')))) "sidecar webp de $p borrado" "sidecar webp de $p quedo en disco"
}

Write-Output '--- 11. Smoke ---'
foreach ($u in @('/', '/noticias', '/gacetas', '/entes', '/contacto')) {
    $r = Invoke-WebRequest "$base$u" -UseBasicParsing
    Check ($r.StatusCode -eq 200) "$u 200" "$u -> $($r.StatusCode)"
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== GACETAS: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== GACETAS: $fail FALLAS ==="; exit 1 }
