$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$pub = 'C:\Users\aegir\Documents\GitHub\Portal2026\storage\app\public'
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'token no encontrado'
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
function Post-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Login($email) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $s -UseBasicParsing
    $t = Get-Token $r.Content
    Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=$([uri]::EscapeDataString('password'))&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $s -UseBasicParsing | Out-Null
    return $s
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

# --- Limpieza quirurgica de corridas anteriores ---
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E WYSIWYG%';" | Out-Null
Sql "DELETE FROM portal2026.media WHERE original_name LIKE 'wysiwyg-%';" | Out-Null

Write-Output '--- 1. TinyMCE presente en crear noticia ---'
$sess = Login 'admin@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/noticias/crear" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'tinymce\.min\.js') 'script tinymce cargado' 'falta script tinymce'
Check ($r.Content -match 'id="content"') 'textarea content presente' 'falta textarea content'
Check ($r.Content -match "license_key") 'license_key gpl declarado' 'falta license_key'
Check ($r.Content -match 'images_upload_handler') 'handler de subida configurado' 'falta handler subida'
Check ($r.Content -match 'vendor/tinymce') 'base_url vendor/tinymce' 'falta base_url'
Check ($r.Content -match "language_url") 'idioma configurado' 'falta idioma'
Check ($r.Content -match 'name="_token"') 'csrf en form' 'falta csrf'
$token = Get-Token $r.Content

Write-Output '--- 2. Assets TinyMCE servidos (offline) ---'
foreach ($u in @('/vendor/tinymce/tinymce.min.js', '/vendor/tinymce/themes/silver/theme.min.js', '/vendor/tinymce/langs/es.js')) {
    try {
        $r2 = Invoke-WebRequest "$base$u" -UseBasicParsing -TimeoutSec 10
        Check ($r2.StatusCode -eq 200) "$u 200" "$u $($r2.StatusCode)"
    } catch { Bad "$u ERROR" }
}

Write-Output '--- 3. Crear noticia con HTML del editor ---'
$html = '<p>Parrafo con <strong>negrita</strong> y <em>cursiva</em>.</p><ul><li>Item 1</li></ul>'
$r = Post-Form "$base/admin/noticias" @{
    _token = $token; title = 'E2E WYSIWYG HTML'; category_id = '1'
    excerpt = 'Extracto WYSIWYG'; content = $html; status = 'published'
} $sess
Check ($r.FinalUri -match '/admin/noticias$') 'redirect al listado' "uri: $($r.FinalUri)"
$row = Sql "SELECT content FROM portal2026.posts WHERE title='E2E WYSIWYG HTML';"
Check ($row -match '<strong>negrita</strong>') 'HTML guardado crudo en BD' "contenido alterado: $row"

Write-Output '--- 4. Detalle publico renderiza HTML sin escapar ---'
$slug = (Sql "SELECT slug FROM portal2026.posts WHERE title='E2E WYSIWYG HTML';") -join ''
$r = Invoke-WebRequest "$base/noticias/$slug" -UseBasicParsing
Check ($r.Content -match '<strong>negrita</strong>') 'detalle muestra <strong> crudo' 'HTML escapado en detalle'
Check ($r.Content -notmatch '&lt;strong&gt;') 'sin entidades escapadas' 'hay entidades &lt; en detalle'
Check ($r.Content -match '<li>Item 1</li>') 'lista renderizada' 'lista no renderiza'

Write-Output '--- 5. Contenido plano legado conserva saltos (nl2br) ---'
Sql "INSERT INTO portal2026.posts (title, slug, excerpt, content, category_id, user_id, status, published_at, created_at, updated_at) VALUES ('E2E WYSIWYG Plano', 'e2e-wysiwyg-plano', 'x', 'Linea uno`nLinea dos', 1, 1, 'published', NOW(), NOW(), NOW());" | Out-Null
$r = Invoke-WebRequest "$base/noticias/e2e-wysiwyg-plano" -UseBasicParsing
Check ($r.Content -match '<br />|<br>') 'texto plano usa nl2br' 'sin <br> en texto plano'
Check ($r.Content -notmatch 'Linea uno&lt;') 'sin escape residual' 'escape residual'

Write-Output '--- 6. Upload handler JSON (subida desde el editor) ---'
$png = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
[System.IO.File]::WriteAllBytes("$tmp\wysiwyg-up.png", $png)
$boundary = [Guid]::NewGuid().ToString()
$enc = [System.Text.Encoding]::UTF8
$ms = New-Object System.IO.MemoryStream
$p1 = "--$boundary`r`nContent-Disposition: form-data; name=`"_token`"`r`n`r`n$token`r`n"
$b = $enc.GetBytes($p1); $ms.Write($b, 0, $b.Length)
$bytes = [System.IO.File]::ReadAllBytes("$tmp\wysiwyg-up.png")
$head = "--$boundary`r`nContent-Disposition: form-data; name=`"files[]`"; filename=`"wysiwyg-up.png`"`r`nContent-Type: image/png`r`n`r`n"
$b = $enc.GetBytes($head); $ms.Write($b, 0, $b.Length)
$ms.Write($bytes, 0, $bytes.Length)
$tail = "`r`n--$boundary--`r`n"; $b = $enc.GetBytes($tail); $ms.Write($b, 0, $b.Length)
$body = $ms.ToArray(); $ms.Dispose()
$r = Invoke-WebRequest -Uri "$base/admin/medios" -Method Post -Body $body -ContentType "multipart/form-data; boundary=$boundary" -Headers @{ Accept = 'application/json' } -WebSession $sess -UseBasicParsing
Check ($r.StatusCode -eq 200) "JSON 200 (got $($r.StatusCode))" "JSON status $($r.StatusCode)"
Check ($r.Content -match '"location"') 'JSON con location' 'sin location en JSON'
if ($r.Content -match '"location"\s*:\s*"([^"]+)"') { $jsonPath = $Matches[1] -replace '\\/', '/' } else { $jsonPath = '' }
Check ($jsonPath -match 'storage/uploads/') "location a uploads ($jsonPath)" "location invalida: $jsonPath"

Write-Output '--- 7. Medios: Copiar URL + fallback ---'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'copy-url-btn') 'boton Copiar URL presente' 'falta boton Copiar URL'
Check ($r.Content -match 'mediaCopyFallback') 'fallback copiar presente' 'falta fallback'
Check ($r.Content -match 'isSecureContext') 'guarda secure context' 'sin guarda'
Check ($r.Content -match 'data-url=') 'data-url en botones' 'sin data-url'

Write-Output '--- 8. Permisos: editor con media.upload ---'
$ed = Login 'editor@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin/medios" -WebSession $ed -UseBasicParsing
Check ($r.StatusCode -eq 200) 'editor accede a /admin/medios' "status $($r.StatusCode)"
Check ($r.Content -match 'name="files\[\]"') 'editor ve el form de subida' 'editor sin form de subida'

Write-Output '--- 9. Cleanup ---'
Sql "DELETE FROM portal2026.posts WHERE title LIKE 'E2E WYSIWYG%';" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.posts WHERE title LIKE 'E2E WYSIWYG%';"
Check ($cnt -eq '0') 'posts de prueba eliminados' "restan: $cnt"
$paths = (Sql "SELECT path FROM portal2026.media WHERE original_name LIKE 'wysiwyg-%';") -split "`n"
foreach ($p in $paths) {
    $p = $p.Trim()
    if ($p -and (Test-Path "$pub\$p")) { Remove-Item "$pub\$p" -Force; Ok "borrado disco: $p" }
}
Sql "DELETE FROM portal2026.media WHERE original_name LIKE 'wysiwyg-%';" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.media WHERE original_name LIKE 'wysiwyg-%';"
Check ($cnt -eq '0') 'media de prueba eliminada' "restan: $cnt"
Remove-Item "$tmp\wysiwyg-up.png" -Force -ErrorAction SilentlyContinue

Write-Output '--- 10. Smoke ---'
foreach ($u in @('/', '/noticias', '/gacetas')) {
    try { $r2 = Invoke-WebRequest "$base$u" -UseBasicParsing -TimeoutSec 10; Check ($r2.StatusCode -eq 200) "$u 200" "$u $($r2.StatusCode)" }
    catch { Bad "$u ERROR" }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== WYSIWYG: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== WYSIWYG: $fail FALLAS ==="; exit 1 }
