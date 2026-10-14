$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($cond, $ok, $bad) { if ($cond) { Ok $ok } else { Bad $bad } }

function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'CSRF token no encontrado'
}
function Put-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Post-Raw($url, $body, $sess) {
    Invoke-WebRequest -Uri $url -Method Post -Body $body -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
}
function Send-Multipart($url, $fields, $files, $sess, $contentType = 'image/png') {
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
            $head = "--$boundary`r`nContent-Disposition: form-data; name=`"$key`"; filename=`"$(Split-Path $path -Leaf)`"`r`nContent-Type: $contentType`r`n`r`n"
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
function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    $r = Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing
    if ($r.StatusCode -ne 200) { throw "Login fallido: $email" }
    $s
}

# --- Limpieza previa ---
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E Aprob%';" | Out-Null
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E Multimedia%';" | Out-Null
Sql "DELETE FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%';" | Out-Null
Sql "DELETE FROM portal2026.media WHERE original_name LIKE '%-e2e.png';" | Out-Null

Write-Output '--- 1. Login admin ---'
$sess = Login 'admin@gdc.gob.ve'
Ok 'LOGIN admin'

Write-Output '--- 2. Crear borradores y publicada ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
Check ($r.Content -match 'name="published_at"') 'form crear: campo programacion presente' 'falta campo programacion'
Check ($r.Content -match 'name="status"') 'form crear: select estado presente' 'falta select status'

foreach ($t in @('E2E Aprob Borrador A', 'E2E Aprob Borrador B', 'E2E Aprob Borrador C')) {
    $r = Put-Form "$base/admin/noticias" @{
        _token = $token; title = $t; category_id = '1'
        excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'; status = 'draft'
    } $sess
    Check ($r.FinalUri -match '/admin/noticias$') "creado borrador: $t" "fallo crear: $t ($($r.FinalUri))"
}
$r = Put-Form "$base/admin/noticias" @{
    _token = $token; title = 'E2E Aprob Publicada'; category_id = '1'
    excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'; status = 'published'
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'creada publicada' 'fallo crear publicada'

$idA = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Borrador A';"
$idB = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Borrador B';"
$idC = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Borrador C';"
$idP = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Publicada';"
Check ($idA -and $idB -and $idC -and $idP) "ids A=$idA B=$idB C=$idC P=$idP" 'faltan filas creadas'
$pubAt = Sql "SELECT published_at FROM portal2026.posts WHERE id=$idP;"
Check ($pubAt -ne '') "publicada con published_at ($pubAt)" 'published_at vacio en publicada inmediata'

Write-Output '--- 3. Index admin: badges y UI de aprobacion ---'
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'Pendiente de aprobaci') 'badge Pendiente visible' 'falta badge Pendiente'
Check ($r.Content -match 'Publicado') 'badge Publicado visible' 'falta badge Publicado'
Check ($r.Content -match 'Publicar seleccionadas') 'boton masivo presente' 'falta boton masivo'
Check ($r.Content -match 'name="posts\[\]"') 'checkboxes presentes' 'faltan checkboxes'
Check ($r.Content -match 'selectAll') 'seleccionar todo presente' 'falta seleccionar todo'
Check ($r.Content -notmatch 'alert alert-warning') 'sin alert warning en index limpio' 'alert warning inesperado'

Write-Output '--- 4. Aprobar individual ---'
$token = Get-Token $r.Content
$r = Post-Raw "$base/admin/noticias/$idA/aprobar" "_token=$([uri]::EscapeDataString($token))" $sess
Check ($r.Content -match "Noticia aprobada: E2E Aprob Borrador A") 'flash de aprobacion individual' 'sin flash de aprobacion'
$row = Sql "SELECT status, published_at IS NOT NULL FROM portal2026.posts WHERE id=$idA;"
Check ($row -match "^published`t1") "borrador A publicado ($($row -replace "`t",','))" "estado A: $($row -replace "`t",',')"
$aAudit = Sql "SELECT COUNT(*) FROM portal2026.activity_logs WHERE action='news.approved' AND entity_id=$idA;"
Check ($aAudit -eq '1') "audit news.approved en A ($aAudit)" "audit A: $aAudit"

Write-Output '--- 5. Aprobacion masiva (borrador B + publicada P) ---'
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Post-Raw "$base/admin/noticias/publicar" "_token=$([uri]::EscapeDataString($token))&posts[]=$idB&posts[]=$idP" $sess
Check ($r.Content -match '1 noticia\(s\) publicada\(s\)') 'flash conteo publicadas' 'sin flash de conteo'
Check ($r.Content -match '1 omitida') 'flash conteo omitidas' 'sin flash de omitidas'
$row = Sql "SELECT status FROM portal2026.posts WHERE id=$idB;"
Check ($row -eq 'published') "borrador B publicado ($row)" "estado B: $row"
$pStatus = Sql "SELECT status FROM portal2026.posts WHERE id=$idP;"
Check ($pStatus -eq 'published') "publicada P intacta ($pStatus)" "estado P: $pStatus"
$bAudit = Sql "SELECT COUNT(*) FROM portal2026.activity_logs WHERE action='news.approved' AND entity_id IN ($idA,$idB);"
Check ($bAudit -eq '2') "audit masivo solo A+B ($bAudit)" "audit: $bAudit"

Write-Output '--- 6. Editor: sin UI de aprobacion, 403, formularios disabled ---'
$editor = Login 'editor@gdc.gob.ve'
$rE = Invoke-WebRequest "$base/admin/noticias" -WebSession $editor -UseBasicParsing
Check ($rE.Content -notmatch 'name="posts\[\]"') 'editor: sin checkboxes' 'editor ve checkboxes'
Check ($rE.Content -notmatch 'Publicar seleccionadas') 'editor: sin boton masivo' 'editor ve boton masivo'
Check ($rE.Content -notmatch '>Aprobar</button>') 'editor: sin boton Aprobar' 'editor ve boton Aprobar'
Check ($rE.Content -match 'Pendiente de aprobaci') 'editor: badge Pendiente visible (informativo)' 'editor no ve badge'
$tokenE = Get-Token $rE.Content
try {
    Post-Raw "$base/admin/noticias/$idC/aprobar" "_token=$([uri]::EscapeDataString($tokenE))" $editor | Out-Null
    Bad 'editor NO recibio 403 en aprobar'
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 403) { Ok 'editor recibe 403 en aprobar' } else { Bad "aprobar status $($_.Exception.Response.StatusCode.value__)" }
}
try {
    Post-Raw "$base/admin/noticias/publicar" "_token=$([uri]::EscapeDataString($tokenE))&posts[]=$idC" $editor | Out-Null
    Bad 'editor NO recibio 403 en masivo'
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 403) { Ok 'editor recibe 403 en masivo' } else { Bad "masivo status $($_.Exception.Response.StatusCode.value__)" }
}
$rE = Invoke-WebRequest "$base/admin/noticias/$idC/editar" -WebSession $editor -UseBasicParsing
Check ($rE.Content -match 'name="status"[^>]*disabled') 'editor: select estado disabled' 'select estado NO disabled para editor'
Check ($rE.Content -match 'name="published_at"[^>]*disabled') 'editor: campo programacion disabled' 'datetime NO disabled para editor'
$tokenE = Get-Token $rE.Content
$r = Put-Form "$base/admin/noticias/$idC" @{
    _token = $tokenE; _method = 'PUT'; title = 'E2E Aprob Borrador C'; category_id = '1'
    excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'; status = 'published'
} $editor
Check ($r.Content -match 'queda pendiente de aprobaci') 'flash warning al editor por intentar publicar' 'sin flash warning para editor'
$cStatus = Sql "SELECT status FROM portal2026.posts WHERE id=$idC;"
Check ($cStatus -eq 'draft') "C sigue en borrador ($cStatus)" "C cambio de estado: $cStatus"

Write-Output '--- 7. Programacion de publicacion ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$future = Get-Date (Get-Date).AddDays(2) -Format 'yyyy-MM-ddTHH:mm'
$r = Put-Form "$base/admin/noticias" @{
    _token = $token; title = 'E2E Aprob Programada'; category_id = '1'
    excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'
    status = 'published'; published_at = $future
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'programada creada' 'fallo crear programada'
$idS = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Programada';"
Check $idS 'fila programada existe' 'no se creo la programada'
$slugS = Sql "SELECT slug FROM portal2026.posts WHERE id=$idS;"
$st = Sql "SELECT status FROM portal2026.posts WHERE id=$idS;"
$pa = Sql "SELECT published_at FROM portal2026.posts WHERE id=$idS;"
Check ($st -eq 'published') "estado published ($st)" "estado: $st"
Check ($pa -gt (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')) "published_at futuro ($pa)" "published_at no futuro: $pa"

try {
    Invoke-WebRequest "$base/noticias/$slugS" -UseBasicParsing -ErrorAction Stop | Out-Null
    Bad 'publico NO devolvio 404 para programada'
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 404) { Ok 'publico 404 para programada' } else { Bad "show status $($_.Exception.Response.StatusCode.value__)" }
}
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.Content -notmatch 'E2E Aprob Programada') 'listado publico oculta programada' 'listado publico la muestra'
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'Programada') 'badge Programada en admin' 'falta badge Programada'

$r = Invoke-WebRequest "$base/admin/noticias/$idS/editar" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'name="published_at"') 'edit admin: campo programacion presente' 'falta campo programacion en edit'
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/noticias/$idS" @{
    _token = $token; _method = 'PUT'; title = 'E2E Aprob Programada'; category_id = '1'
    excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'
    status = 'published'; published_at = ''
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'update limpiar fecha exitoso' 'fallo update programada'
$pa = Sql "SELECT published_at FROM portal2026.posts WHERE id=$idS;"
Check ($pa -le (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')) "fecha vaciada = publicada ya ($pa)" "sigue futura: $pa"
$r = Invoke-WebRequest "$base/noticias/$slugS" -UseBasicParsing
Check ($r.StatusCode -eq 200) 'publico 200 tras vaciar fecha' "status $($r.StatusCode)"

Write-Output '--- 8. Editor editando programada no la adelanta ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$future2 = Get-Date (Get-Date).AddDays(3) -Format 'yyyy-MM-ddTHH:mm'
$r = Put-Form "$base/admin/noticias" @{
    _token = $token; title = 'E2E Aprob Futura 2'; category_id = '1'
    excerpt = 'Extracto E2E Aprob'; content = '<p>Contenido E2E Aprob</p>'
    status = 'published'; published_at = $future2
} $sess
$idF = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Aprob Futura 2';"
$paBefore = Sql "SELECT published_at FROM portal2026.posts WHERE id=$idF;"
$rE = Invoke-WebRequest "$base/admin/noticias/$idF/editar" -WebSession $editor -UseBasicParsing
$tokenE = Get-Token $rE.Content
$r = Put-Form "$base/admin/noticias/$idF" @{
    _token = $tokenE; _method = 'PUT'; title = 'E2E Aprob Futura 2 Editada'; category_id = '1'
    excerpt = 'Extracto editado'; content = '<p>Editado</p>'
} $editor
Check ($r.FinalUri -match '/admin/noticias$') 'editor pudo editar contenido' 'editor no pudo editar'
$paAfter = Sql "SELECT published_at FROM portal2026.posts WHERE id=$idF;"
$titleAfter = Sql "SELECT title FROM portal2026.posts WHERE id=$idF;"
Check ($paAfter -eq $paBefore) "fecha conservada por editor ($paAfter)" "fecha cambio: $paBefore -> $paAfter"
Check ($titleAfter -eq 'E2E Aprob Futura 2 Editada') 'edicion de contenido aplicada' 'edicion no aplico'

Write-Output '--- 9. Multimedia: galeria, videos y carrusel (D15) ---'
$pub = 'C:\Users\aegir\Documents\GitHub\Portal2026\storage\app\public'
$png = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
[System.IO.File]::WriteAllBytes("$tmp\portada-e2e.png", $png)
[System.IO.File]::WriteAllBytes("$tmp\gal1-e2e.png", $png)
[System.IO.File]::WriteAllBytes("$tmp\gal2-e2e.png", $png)
$mp4 = "$tmp\galvid-e2e.mp4"
$bytes = [byte[]](0x00,0x00,0x00,0x18) + [System.Text.Encoding]::ASCII.GetBytes('ftypisom') + [byte[]](0x00,0x00,0x02,0x00) + [System.Text.Encoding]::ASCII.GetBytes('isomiso2mp41')
[System.IO.File]::WriteAllBytes($mp4, $bytes)

$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/noticias" @{
    _token = $token; title = 'E2E Multimedia'; category_id = '1'
    excerpt = 'Extracto multimedia'; content = '<p>Contenido multimedia</p>'; status = 'published'
    new_video_urls = 'https://youtu.be/dQw4w9WgXcQ'
} @{ featured_image_file = "$tmp\portada-e2e.png"; 'gallery_images[]' = @("$tmp\gal1-e2e.png", "$tmp\gal2-e2e.png"); 'new_video_files[]' = $mp4 } $sess
Check ($r.BaseResponse.ResponseUri.AbsoluteUri -match '/admin/noticias$') 'noticia multimedia creada' 'fallo crear noticia multimedia'
$idM = Sql "SELECT id FROM portal2026.posts WHERE title='E2E Multimedia';"
Check $idM "fila multimedia existe ($idM)" 'no se creo la fila multimedia'

$pmCnt = Sql "SELECT COUNT(*) FROM portal2026.post_media WHERE post_id=$idM;"
Check ($pmCnt -eq '4') "post_media: 2 imagenes + 2 videos ($pmCnt)" "post_media: $pmCnt"
$dated = Sql "SELECT COUNT(*) FROM portal2026.post_media WHERE post_id=$idM AND type='image' AND path LIKE CONCAT('uploads/', YEAR(CURDATE()), '/', LPAD(MONTH(CURDATE()),2,'0'), '/%');"
Check ($dated -eq '2') "imagenes en uploads/YYYY/MM ($dated)" "rutas sin ano/mes: $dated"
$vidCnt = Sql "SELECT COUNT(*) FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%';"
Check ($vidCnt -eq '2') "videos creados en modulo Videos ($vidCnt)" "videos en modulo: $vidCnt"
$featOff = Sql "SELECT COUNT(*) FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%' AND is_featured=0;"
Check ($featOff -eq '2') 'videos sin is_featured (no invaden portada)' 'videos quedaron destacados'

$slugM = Sql "SELECT slug FROM portal2026.posts WHERE id=$idM;"
$fiName = Sql "SELECT SUBSTRING_INDEX(featured_image, '/', -1) FROM portal2026.posts WHERE id=$idM;"
$r = Invoke-WebRequest "$base/noticias/$slugM" -UseBasicParsing
Check ($r.Content -match 'id="newsGallery"') 'detalle con carrusel' 'sin carrusel en detalle'
$items = ([regex]::Matches($r.Content, 'carousel-item')).Count
Check ($items -eq '5') "5 items en carrusel (portada+2img+2vid) ($items)" "items: $items"
Check ($r.Content -match 'youtube\.com/embed/dQw4w9WgXcQ') 'video youtube embebido' 'sin embed youtube'
Check ($r.Content -match '<video') 'video archivo embebido' 'sin video archivo'
$portadaOk = $r.Content -match ('(?s)carousel-item active[^>]*>.*' + [regex]::Escape($fiName))
Check $portadaOk 'portada primera en el carrusel' "portada no es la primera ($fiName)"

$r = Invoke-WebRequest "$base/admin/noticias/$idM/editar" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'gallery-items') 'edit muestra items de galeria' 'edit sin galeria'
Check ($r.Content -match 'direction=up') 'botones de orden presentes' 'faltan botones de orden'
$token = Get-Token $r.Content
$firstId = Sql "SELECT id FROM portal2026.post_media WHERE post_id=$idM ORDER BY position, id LIMIT 1;"
$secondId = Sql "SELECT id FROM portal2026.post_media WHERE post_id=$idM ORDER BY position, id LIMIT 1 OFFSET 1;"
Post-Raw "$base/admin/noticias/$idM/media/$firstId/move?direction=down" "_token=$([uri]::EscapeDataString($token))" $sess | Out-Null
$p1 = Sql "SELECT position FROM portal2026.post_media WHERE id=$firstId;"
$p2 = Sql "SELECT position FROM portal2026.post_media WHERE id=$secondId;"
Check ($p1 -eq '1' -and $p2 -eq '0') "reorden aplicado ($p1/$p2)" "orden no cambio ($p1/$p2)"

$imgId = Sql "SELECT id FROM portal2026.post_media WHERE post_id=$idM AND type='image' ORDER BY position LIMIT 1;"
$r = Put-Form "$base/admin/noticias/$idM" @{
    _token = $token; _method = 'PUT'; title = 'E2E Multimedia'; category_id = '1'
    excerpt = 'Extracto multimedia'; content = '<p>Contenido multimedia</p>'; status = 'published'
    'remove_media[]' = $imgId
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'update con remove_media exitoso' 'fallo update remove_media'
$afterRm = Sql "SELECT COUNT(*) FROM portal2026.post_media WHERE post_id=$idM;"
Check ($afterRm -eq '3') "imagen eliminada de la galeria ($afterRm)" "post_media tras remove: $afterRm"

$r = Invoke-WebRequest "$base/admin/noticias/$idM/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/noticias/$idM" @{
    _token = $token; _method = 'PUT'; title = 'E2E Multimedia'; category_id = '1'
    excerpt = 'Extracto multimedia'; content = '<p>Contenido multimedia</p>'; status = 'published'
    new_video_urls = "https://youtu.be/dQw4w9WgXcQ`nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ`nhttps://www.youtube.com/embed/dQw4w9WgXcQ"
} $sess
Check ($r.FinalUri -match 'editar') 'tope de videos rechaza y vuelve al form' "no rechazo: $($r.FinalUri)"
Check ($r.Content -match 'ximo 3 videos') 'mensaje de tope de videos visible' 'sin mensaje de tope'
$vidAfter = Sql "SELECT COUNT(*) FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%';"
Check ($vidAfter -eq '2') "no se crearon videos de mas ($vidAfter)" "videos: $vidAfter"

Write-Output '--- 10. Cleanup ---'
foreach ($id in @($idA, $idB, $idC, $idP, $idS, $idF)) {
    if ($id) {
        $r = Invoke-WebRequest "$base/admin/noticias/$id/editar" -WebSession $sess -UseBasicParsing -ErrorAction SilentlyContinue
        if ($r) {
            $token = Get-Token $r.Content
            Post-Raw "$base/admin/noticias/$id" "_token=$([uri]::EscapeDataString($token))&_method=DELETE" $sess | Out-Null
        }
    }
}
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E Aprob%';" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.posts WHERE title LIKE 'E2E Aprob%';"
Check ($cnt -eq '0') 'posts de prueba purgados de la BD' "restos: $cnt"
$orphan = Sql "SELECT COUNT(*) FROM portal2026.activity_logs WHERE action='news.approved' AND entity_id IN ($idA,$idB);"
Check ($orphan -eq '2') "auditoria de aprobaciones registrada ($orphan)" "auditoria: $orphan"

$imgPaths = (Sql "SELECT pm.path FROM portal2026.post_media pm JOIN portal2026.posts p ON p.id=pm.post_id WHERE p.title LIKE 'E2E Multimedia%' AND pm.path IS NOT NULL;") -split "`n"
$vidPaths = (Sql "SELECT path FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%' AND path IS NOT NULL;") -split "`n"
$libPaths = (Sql "SELECT path FROM portal2026.media WHERE original_name LIKE '%-e2e.png';") -split "`n"
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E Multimedia%';" | Out-Null
Sql "DELETE FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%';" | Out-Null
Sql "DELETE FROM portal2026.media WHERE original_name LIKE '%-e2e.png';" | Out-Null
foreach ($p in @($imgPaths + $vidPaths + $libPaths)) {
    $p = $p.Trim()
    if ($p -and (Test-Path "$pub\$p")) { Remove-Item "$pub\$p" -Force }
    $wp = if ($p) { [System.IO.Path]::ChangeExtension("$pub\$p", 'webp') } else { $null }
    if ($wp -and (Test-Path $wp)) { Remove-Item $wp -Force }
}
Remove-Item "$tmp\portada-e2e.png", "$tmp\gal1-e2e.png", "$tmp\gal2-e2e.png", "$tmp\galvid-e2e.mp4" -Force -ErrorAction SilentlyContinue
$mmLeft = Sql "SELECT COUNT(*) FROM portal2026.post_media pm JOIN portal2026.posts p ON p.id=pm.post_id WHERE p.title LIKE 'E2E Multimedia%';"
Check ($mmLeft -eq '0') 'post_media multimedia purgado' "restos: $mmLeft"
$vidLeft = Sql "SELECT COUNT(*) FROM portal2026.videos WHERE title LIKE 'E2E Multimedia%';"
Check ($vidLeft -eq '0') 'videos de prueba purgados' "restos videos: $vidLeft"

Write-Output '--- 11. Smoke ---'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.StatusCode -eq 200) '/noticias 200' "/noticias -> $($r.StatusCode)"
$r = Invoke-WebRequest "$base/admin/noticias" -WebSession $sess -UseBasicParsing
Check ($r.StatusCode -eq 200) '/admin/noticias 200' "admin -> $($r.StatusCode)"

Write-Output ''
if ($fail -eq 0) { Write-Output '=== NOTICIAS/APROBACION: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== NOTICIAS/APROBACION: $fail FALLAS ==="; exit 1 }
