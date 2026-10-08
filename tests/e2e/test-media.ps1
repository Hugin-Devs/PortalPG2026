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
function Put-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

# --- PNGs de prueba ---
$png1 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
$png2 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC')
[System.IO.File]::WriteAllBytes("$tmp\notimg1.png", $png1)
[System.IO.File]::WriteAllBytes("$tmp\notimg2.png", $png2)
[System.IO.File]::WriteAllBytes("$tmp\fake.txt", [System.Text.Encoding]::UTF8.GetBytes('hola'))

# --- Login ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=admin%40gdc.gob.ve&password=password&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing
if ($r.Content -notmatch 'Admin GDC') { throw 'Login fallido' }
Ok 'LOGIN'

# --- Limpieza previa ---
Sql "DELETE FROM portal2026.posts WHERE title='E2E Imagen Destacada';" | Out-Null

Write-Output '--- 1. Formulario de creacion ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'multipart/form-data') 'enctype multipart presente' 'falta enctype multipart'
Check ($r.Content -match 'mediaLibraryModal') 'modal de biblioteca presente' 'falta modal biblioteca'
Check ($r.Content -match 'featured_image_file') 'input file presente' 'falta input file'
Check ($r.Content -match 'Elegir de la biblioteca') 'boton biblioteca presente' 'falta boton biblioteca'
$token = Get-Token $r.Content

Write-Output '--- 2. Crear noticia CON archivo nuevo ---'
$r = Send-Multipart "$base/admin/noticias" @{
    _token = $token; title = 'E2E Imagen Destacada'; category_id = '1'
    excerpt = 'Extracto E2E'; content = '<p>Contenido E2E</p>'; status = 'published'
} @{ featured_image_file = "$tmp\notimg1.png" } $sess
Check ($r.StatusCode -in 200, 302) "POST create -> $($r.StatusCode)" "POST create fallo: $($r.StatusCode)"

$row = Sql "SELECT id, featured_image FROM portal2026.posts WHERE title='E2E Imagen Destacada';"
$id, $fi = $row -split "`t"
Check $id "post id=$id" 'post no creado'
Check ($fi -like 'uploads/*') "featured_image=$fi" "featured_image invalido: $fi"
$mrow = Sql "SELECT mime_type FROM portal2026.media WHERE path='$fi';"
Check ($mrow -match '^image/') "media auto-registrada (mime=$mrow)" 'media NO auto-registrada'
Check (Test-Path "$pub\$fi") 'archivo en storage/app/public' 'archivo no existe en disco'
$img1Path = "$pub\$fi"

Write-Output '--- 3. Visible en publico (/noticias) ---'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.Content -match [regex]::Escape("storage/$fi")) 'home/listado muestra asset(storage/...)' 'imagen NO visible en /noticias'

Write-Output '--- 4. Subir 2a imagen a la biblioteca ---'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/medios" @{ _token = $token } @{ 'files[]' = "$tmp\notimg2.png" } $sess
$libPath = (Sql "SELECT path FROM portal2026.media ORDER BY id DESC LIMIT 1;")
Check ($libPath -like 'uploads/*') "biblioteca: $libPath" 'subida a biblioteca fallo'

Write-Output '--- 4b. Subida MULTIPLE (2 archivos en un request) ---'
$png3 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
[System.IO.File]::WriteAllBytes("$tmp\notimg3.png", $png3)
[System.IO.File]::WriteAllBytes("$tmp\notimg4.png", $png3)
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/medios" @{ _token = $token } @{ 'files[]' = @("$tmp\notimg3.png", "$tmp\notimg4.png") } $sess
Check ($r.Content -match '2 archivos subidos correctamente') 'flash plural 2 archivos' 'flash multiple incorrecto'
$mrow = Sql "SELECT COUNT(*) FROM portal2026.media WHERE original_name IN ('notimg3.png','notimg4.png');"
Check ($mrow -eq '2') "2 filas en media (got $mrow)" "filas != 2: $mrow"
$mpaths = (Sql "SELECT path FROM portal2026.media WHERE original_name IN ('notimg3.png','notimg4.png');") -split "`n"
$m1 = $mpaths[0].Trim(); $m2 = $mpaths[1].Trim()
Check ((Test-Path "$pub\$m1") -and (Test-Path "$pub\$m2")) 'ambos archivos en disco' 'faltan archivos en disco'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
Check (($r.Content -match [regex]::Escape($m1)) -and ($r.Content -match [regex]::Escape($m2))) 'grid muestra ambos archivos' 'grid incompleto'

Write-Output '--- 5. Edit page: modal con imagen de biblioteca + checkbox quitar ---'
$r = Invoke-WebRequest "$base/admin/noticias/$id/editar" -WebSession $sess -UseBasicParsing
Check ($r.Content -match [regex]::Escape("data-path=`"$libPath`"")) 'modal lista imagenes de biblioteca' 'modal no contiene la imagen'
Check ($r.Content -match 'Quitar imagen actual') 'checkbox quitar presente' 'falta checkbox quitar'
$token = Get-Token $r.Content

Write-Output '--- 6. Editar eligiendo de la BIBLIOTECA (sin archivo) ---'
$r = Put-Form "$base/admin/noticias/$id" @{
    _token = $token; _method = 'PUT'; title = 'E2E Imagen Destacada'; category_id = '1'
    excerpt = 'Extracto E2E'; content = '<p>Contenido E2E</p>'; status = 'published'
    featured_image = $libPath
} $sess
Check ($r.FinalUri -match '/admin/noticias$') "redirect exitoso a listado ($($r.FinalUri))" "no redirigio al listado: $($r.FinalUri)"
$now = Sql "SELECT featured_image FROM portal2026.posts WHERE id=$id;"
Check ($now -eq $libPath) "BD apunta a biblioteca ($now)" "BD no cambio: $now"
Check (Test-Path $img1Path) 'archivo del upload original NO se borro' 'archivo original erroneamente borrado'

Write-Output '--- 7. Path inexistente en media -> rechazado, conserva actual ---'
$r = Invoke-WebRequest "$base/admin/noticias/$id/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Put-Form "$base/admin/noticias/$id" @{
    _token = $token; _method = 'PUT'; title = 'E2E Imagen Destacada'; category_id = '1'
    excerpt = 'Extracto E2E'; content = '<p>Contenido E2E</p>'; status = 'published'
    featured_image = 'uploads/hack-no-existe.jpg'
} $sess
Check ($r.FinalUri -match '/editar') "validacion rechazo (volvio al form: $($r.FinalUri))" "NO rechazo: redirigio a $($r.FinalUri)"
$now = Sql "SELECT featured_image FROM portal2026.posts WHERE id=$id;"
Check ($now -eq $libPath) "path falso rechazado, conserva $libPath" "path falso ACEPTADO: $now"

Write-Output '--- 8. Editar SIN tocar imagen -> la conserva ---'
$r = Put-Form "$base/admin/noticias/$id" @{
    _token = $token; _method = 'PUT'; title = 'E2E Imagen Destacada'; category_id = '1'
    excerpt = 'Extracto E2E v2'; content = '<p>Contenido E2E</p>'; status = 'published'
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'update sin tocar imagen -> exitoso' "fallo: $($r.FinalUri)"
$now = Sql "SELECT featured_image FROM portal2026.posts WHERE id=$id;"
Check ($now -eq $libPath) "sin tocar conserva ($now)" "perdio la imagen: $now"
$exc = Sql "SELECT excerpt FROM portal2026.posts WHERE id=$id;"
Check ($exc -eq 'Extracto E2E v2') 'el resto del update si aplico' "el update no aplico: $exc"

Write-Output '--- 9. Quitar imagen (checkbox) ---'
$r = Put-Form "$base/admin/noticias/$id" @{
    _token = $token; _method = 'PUT'; title = 'E2E Imagen Destacada'; category_id = '1'
    excerpt = 'Extracto E2E v2'; content = '<p>Contenido E2E</p>'; status = 'published'
    remove_image = '1'
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'quitar imagen -> exitoso' "fallo: $($r.FinalUri)"
$now = Sql "SELECT featured_image IS NULL FROM portal2026.posts WHERE id=$id;"
Check ($now -eq '1') 'featured_image IS NULL tras quitar' "no se quito: $now"
Check (Test-Path "$pub\$libPath") 'archivo de biblioteca NO se borro al quitar' 'archivo de biblioteca borrado (mal)'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.Content -notmatch [regex]::Escape("storage/$libPath")) 'publico ya no muestra la imagen' 'publico la sigue mostrando'

Write-Output '--- 10. Rechazar archivo no-imagen ---'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/noticias" @{
    _token = $token; title = 'E2E No Debe Crearse'; category_id = '1'
    excerpt = 'x'; content = 'y'; status = 'draft'
} @{ featured_image_file = "$tmp\fake.txt" } $sess
$cnt = Sql "SELECT COUNT(*) FROM portal2026.posts WHERE title='E2E No Debe Crearse';"
Check ($cnt -eq '0') 'archivo no-imagen rechazado (0 filas)' "se creo post: $cnt"

Write-Output '--- 11. Cleanup ---'
Invoke-WebRequest "$base/admin/noticias/$id" -Method Post -Body "_method=DELETE&_token=$([uri]::EscapeDataString($token))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.posts WHERE id=$id AND deleted_at IS NULL;"
Check ($cnt -eq '0') 'post eliminado del listado (soft delete)' "post visible aun: $cnt"
$trashed = Sql "SELECT deleted_at IS NOT NULL FROM portal2026.posts WHERE id=$id;"
Check ($trashed -eq '1') 'deleted_at seteado (soft delete ok)' "deleted_at no seteado: $trashed"
foreach ($p in @($fi, $libPath, $m1, $m2)) {
    if ($p -and (Test-Path "$pub\$p")) { Remove-Item "$pub\$p" -Force; Ok "borrado disco: $p" }
    $wp = if ($p) { [System.IO.Path]::ChangeExtension("$pub\$p", 'webp') } else { $null }
    if ($wp -and (Test-Path $wp)) { Remove-Item $wp -Force; Ok "borrado disco sidecar: $(Split-Path $wp -Leaf)" }
}
Sql "DELETE FROM portal2026.media WHERE path IN ('$fi','$libPath','$m1','$m2');" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.media WHERE path IN ('$fi','$libPath','$m1','$m2');"
Check ($cnt -eq '0') 'media limpia' "media sucia: $cnt"
Sql "DELETE FROM portal2026.posts WHERE title='E2E Imagen Destacada';" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.posts WHERE title='E2E Imagen Destacada';"
Check ($cnt -eq '0') 'fila de post de prueba purgada de la BD' "resto: $cnt"

Write-Output ''
if ($fail -eq 0) { Write-Output '=== RESUMEN: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== RESUMEN: $fail FALLAS ==="; exit 1 }
