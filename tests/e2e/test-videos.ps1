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
    $nl = "`r`n"
    $ms = New-Object System.IO.MemoryStream
    foreach ($k in $fields.Keys) {
        $part = '--' + $boundary + $nl + 'Content-Disposition: form-data; name="' + $k + '"' + $nl + $nl + $fields[$k] + $nl
        $b = $enc.GetBytes($part); $ms.Write($b, 0, $b.Length)
    }
    foreach ($key in $files.Keys) {
        $path = $files[$key]
        $bytes = [System.IO.File]::ReadAllBytes($path)
        $fname = Split-Path $path -Leaf
        $head = '--' + $boundary + $nl + 'Content-Disposition: form-data; name="' + $key + '"; filename="' + $fname + '"' + $nl + 'Content-Type: video/mp4' + $nl + $nl
        $b = $enc.GetBytes($head); $ms.Write($b, 0, $b.Length)
        $ms.Write($bytes, 0, $bytes.Length)
        $b = $enc.GetBytes($nl); $ms.Write($b, 0, $b.Length)
    }
    $end = $enc.GetBytes('--' + $boundary + '--' + $nl); $ms.Write($end, 0, $end.Length)
    $body = $ms.ToArray(); $ms.Dispose()
    Invoke-WebRequest -Uri $url -Method Post -Body $body -ContentType "multipart/form-data; boundary=$boundary" -WebSession $sess -UseBasicParsing -MaximumRedirection 5
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
function Count-Videos { ((Sql 'SELECT COUNT(*) FROM portal2026.videos;') -join '').Trim() }

# --- Fixture: MP4 minimo valido (ftyp isom) ---
$mp4 = "$tmp\vidtest.mp4"
$bytes = [byte[]](0x00,0x00,0x00,0x18) + [System.Text.Encoding]::ASCII.GetBytes('ftypisom') + [byte[]](0x00,0x00,0x02,0x00) + [System.Text.Encoding]::ASCII.GetBytes('isomiso2mp41')
[System.IO.File]::WriteAllBytes($mp4, $bytes)

# --- Pre-limpieza quirurgica: solo artefactos de corridas anteriores de esta suite ---
$oldRows = Sql 'SELECT id, path FROM portal2026.videos WHERE title LIKE ''VidTest%'';'
foreach ($row in ($oldRows -split "`n")) {
    if (-not $row) { continue }
    $p = ($row -split "`t")[1]
    if ($p -and (Test-Path "$pub\$p")) { Remove-Item "$pub\$p" -Force }
    $id = ($row -split "`t")[0]
    Sql "DELETE FROM portal2026.videos WHERE id=$id;" | Out-Null
}
Write-Output 'pre-limpieza: artefactos VidTest* removidos'

Write-Output '--- 1. Permisos videos.* ---'
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan db:seed --class=PermissionSeeder --force 2>&1 | Out-Null
$pc = ((Sql 'SELECT COUNT(*) FROM portal2026.permissions;') -join '').Trim()
Check ($pc -eq '38') "38 permisos tras videos (got $pc)" "permisos != 38 (got $pc)"
$rc = ((Sql 'SELECT COUNT(*) FROM portal2026.role_permission;') -join '').Trim()
Check ($rc -eq '64') "64 asignaciones 38+21+5 (got $rc)" "asignaciones != 64 (got $rc)"
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug=''videos.view'' AND `group` LIKE ''Gesti%Noticias'' AND `module`=''Videos'';') -join '').Trim()
Check ($c -eq '1') 'videos.view en grupo Gestion Noticias, modulo Videos' "videos.view mal ubicado (got $c)"
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.permissions WHERE slug LIKE ''videos.%'';') -join '').Trim()
Check ($c -eq '4') "4 permisos videos.* (got $c)" "permisos videos != 4 (got $c)"
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.role_permission rp JOIN portal2026.permissions p ON p.id=rp.permission_id WHERE p.slug LIKE ''videos.%'' AND rp.role IN (''admin'',''editor'');') -join '').Trim()
Check ($c -eq '0') 'admin/editor SIN permisos videos' "admin/editor tienen videos.* (got $c)"

Write-Output '--- 2. Sidebar: seccion + dropdown por rol ---'
$ss = Login 'admin@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
Check ($r.StatusCode -eq 200) 'superadmin ve /admin/videos' "status $($r.StatusCode)"
Check ($r.Content -match 'Gesti.n Noticias</span>') 'sidebar: titulo de seccion' 'falta titulo de seccion'
Check ($r.Content -match 'id="mmMenu"') 'sidebar: dropdown Multimedia' 'falta dropdown mmMenu'
Check ($r.Content -match 'nav flex-column flex-nowrap[^"]*" id="mmMenu"') 'sidebar: mmMenu con flex-nowrap (sin wrap lateral en la animacion)' 'mmMenu sin flex-nowrap'
Check ($r.Content -match 'href="[^"]*admin/videos"') 'superadmin ve item Videos' 'falta item Videos'
Check ($r.Content -match 'tab-cargar' -and $r.Content -match 'tab-portada') 'pestanas Cargar + Portada' 'faltan pestanas'
Check ($r.Content -match 'name="type"' -and $r.Content -match 'id="typeFile"') 'form con toggle YouTube/Archivo' 'falta toggle origen'

$admin = Login 'admin2@gdc.gob.ve'
$adminStatus = try { (Invoke-WebRequest "$base/admin/videos" -WebSession $admin -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop).StatusCode } catch { $_.Exception.Response.StatusCode.value__ }
Check ($adminStatus -eq 403) 'admin NO accede /admin/videos (403)' "admin status $adminStatus"
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $admin -UseBasicParsing
Check ($r.Content -match 'Gesti.n Noticias</span>') 'admin SI ve la seccion (tiene news/media)' 'admin sin seccion'
Check ($r.Content -match 'id="mmMenu"') 'admin ve dropdown Multimedia' 'admin sin dropdown'
Check ($r.Content -match '>Medios</a>') 'admin ve Medios en dropdown' 'admin sin Medios'
Check ($r.Content -notmatch 'href="[^"]*admin/videos"') 'admin NO ve item Videos' 'admin ve item Videos indebidamente'

$ed = Login 'editor@gdc.gob.ve'
$edStatus = try { (Invoke-WebRequest "$base/admin/videos" -WebSession $ed -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop).StatusCode } catch { $_.Exception.Response.StatusCode.value__ }
Check ($edStatus -eq 403) 'editor NO accede /admin/videos (403)' "editor status $edStatus"
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $ed -UseBasicParsing
Check ($r.Content -match 'Gesti.n Noticias</span>') 'editor SI ve la seccion' 'editor sin seccion'
Check ($r.Content -notmatch 'href="[^"]*admin/videos"') 'editor NO ve item Videos' 'editor ve item Videos indebidamente'

Write-Output '--- 3. Cargar video YouTube ---'
$before = Count-Videos
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
$token = Get-Token $r.Content
$form = "_token=$([uri]::EscapeDataString($token))&title=VidTest+YT&type=youtube&url=$([uri]::EscapeDataString('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))"
Invoke-WebRequest "$base/admin/videos" -Method Post -Body $form -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$after = Count-Videos
Check ($after -eq ([int]$before + 1)) "alta youtube creada ($before -> $after)" "alta youtube fallo ($before -> $after)"
$row = ((Sql 'SELECT type, url FROM portal2026.videos WHERE title=''VidTest YT'';') -join '').Trim()
Check ($row -match 'youtube' -and $row -match 'dQw4w9WgXcQ') "datos youtube correctos ($row)" "datos youtube mal ($row)"

Write-Output '--- 4. URL invalida rechazada ---'
$before = Count-Videos
$form = "_token=$([uri]::EscapeDataString($token))&title=VidTest+Bad&type=youtube&url=$([uri]::EscapeDataString('https://example.com/no-youtube'))"
Invoke-WebRequest "$base/admin/videos" -Method Post -Body $form -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$after = Count-Videos
Check ($after -eq $before) "URL invalida rechazada ($after)" "URL invalida fue aceptada ($before -> $after)"

Write-Output '--- 5. Subir archivo mp4 ---'
$r = Send-Multipart "$base/admin/videos" @{ _token = $token; title = 'VidTest File'; type = 'file' } @{ file = $mp4 } $ss
$vidRow = ((Sql 'SELECT id, path FROM portal2026.videos WHERE title=''VidTest File'';') -join '').Trim()
Check ($vidRow -match "`t") 'registro archivo creado' "registro archivo fallo ($vidRow)"
$vidPath = ($vidRow -split "`t")[1]
Check ($vidPath -match '^videos/') "path en carpeta videos/ (got $vidPath)" "path fuera de videos/ (got $vidPath)"
Check (Test-Path "$pub\$vidPath") 'archivo mp4 en disco' 'archivo mp4 no existe en disco'
$c = ((Sql 'SELECT type FROM portal2026.videos WHERE title=''VidTest File'';') -join '').Trim()
Check ($c -eq 'file') "type=file (got $c)" "type incorrecto (got $c)"

Write-Output '--- 6. Portada: destacar / cambiar / ninguno ---'
$idYT = ((Sql "SELECT id FROM portal2026.videos WHERE title='VidTest YT';") -join '').Trim()
$idFile = ((Sql "SELECT id FROM portal2026.videos WHERE title='VidTest File';") -join '').Trim()
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
$token = Get-Token $r.Content
Check ($r.Content -match "name=`"video_id`"") 'radios en pestana portada' 'faltan radios'
Check ($r.Content -match 'featNone') 'opcion Ninguno presente' 'falta opcion Ninguno'

Invoke-WebRequest "$base/admin/videos/destacar" -Method Post -Body "_token=$([uri]::EscapeDataString($token))&video_id=$idYT" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.videos WHERE is_featured=1;') -join '').Trim()
Check ($c -eq '1') "destacar: 1 featured (got $c)" "destacar: featured=$c"
$c = ((Sql "SELECT id FROM portal2026.videos WHERE is_featured=1;") -join '').Trim()
Check ($c -eq $idYT) "destacar apunta al video elegido ($c)" "destacar apunta a $c (esperado $idYT)"

Invoke-WebRequest "$base/admin/videos/destacar" -Method Post -Body "_token=$([uri]::EscapeDataString($token))&video_id=$idFile" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.videos WHERE is_featured=1;') -join '').Trim()
Check ($c -eq '1') "cambio de destacado: sigue en 1 (got $c)" "cambio: featured=$c"
$c = ((Sql "SELECT id FROM portal2026.videos WHERE is_featured=1;") -join '').Trim()
Check ($c -eq $idFile) 'destacado ahora es el archivo' "destacado es $c (esperado $idFile)"

Invoke-WebRequest "$base/admin/videos/destacar" -Method Post -Body "_token=$([uri]::EscapeDataString($token))&video_id=" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$c = ((Sql 'SELECT COUNT(*) FROM portal2026.videos WHERE is_featured=1;') -join '').Trim()
Check ($c -eq '0') "Ninguno limpia destacados (got $c)" "Ninguno: featured=$c"

Write-Output '--- 7. Home refleja la portada ---'
$h = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($h.Content -notmatch 'VIDEOS INSTITUCIONALES') 'home SIN seccion sin destacado' 'home muestra seccion sin destacado'
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
$token = Get-Token $r.Content
Invoke-WebRequest "$base/admin/videos/destacar" -Method Post -Body "_token=$([uri]::EscapeDataString($token))&video_id=$idYT" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$h = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($h.Content -match 'VIDEOS INSTITUCIONALES') 'home muestra seccion con destacado' 'home sin seccion con destacado'
Check ($h.Content -match 'youtube.com/embed/dQw4w9WgXcQ') 'home embebe YouTube destacado' 'home sin embed youtube'
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
$token = Get-Token $r.Content
Invoke-WebRequest "$base/admin/videos/destacar" -Method Post -Body "_token=$([uri]::EscapeDataString($token))&video_id=$idFile" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
$h = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($h.Content -match 'VIDEOS INSTITUCIONALES') 'home muestra seccion con archivo' 'home sin seccion con archivo'
Check ($h.Content -match '<video' -and $h.Content -match [regex]::Escape("storage/$vidPath")) 'home embebe video subido' 'home sin video subido'

Write-Output '--- 8. Eliminar + limpieza ---'
$r = Invoke-WebRequest "$base/admin/videos" -WebSession $ss -UseBasicParsing
$token = Get-Token $r.Content
foreach ($id in @($idYT, $idFile)) {
    if ($id) {
        Invoke-WebRequest "$base/admin/videos/$id" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $ss -UseBasicParsing | Out-Null
    }
}
$c = ((Sql "SELECT COUNT(*) FROM portal2026.videos WHERE title LIKE 'VidTest%';") -join '').Trim()
Check ($c -eq '0') "videos de la suite eliminados (got $c)" "restos: $c"
Check (-not (Test-Path "$pub\$vidPath")) 'archivo mp4 borrado del disco' 'archivo mp4 quedo en disco'
if (Test-Path $mp4) { Remove-Item $mp4 -Force }
Check (-not (Test-Path $mp4)) 'fixture temporal limpio' 'fixture temporal quedo'

Write-Output '--- 9. Smoke ---'
foreach ($u in @('/', '/noticias', '/entes')) {
    $r = Invoke-WebRequest "$base$u" -UseBasicParsing
    Check ($r.StatusCode -eq 200) "$u 200" "$u -> $($r.StatusCode)"
}
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $ss -UseBasicParsing
Check ($r.StatusCode -eq 200) '/admin/medios 200 (superadmin)' "status $($r.StatusCode)"
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $ss -UseBasicParsing
Check ($r.StatusCode -eq 200) '/admin/noticias 200' "status $($r.StatusCode)"

Write-Output ''
if ($fail -eq 0) { Write-Output '=== VIDEOS: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== VIDEOS: $fail FALLAS ==="; exit 1 }
