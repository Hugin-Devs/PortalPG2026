$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$tmp = Join-Path ([System.IO.Path]::GetTempPath()) 'portal2026-e2e'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp | Out-Null }
$storagePartners = Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'storage\app\public'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'

# --- 1. PNGs de prueba (1x1) ---
$png1 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
$png2 = [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAF0lEQVQImWNUNy5jYGBgYGBgYoABBAsAFukA1tgNRvwAAAAASUVORK5CYII=')
[System.IO.File]::WriteAllBytes("$tmp\logo1.png", $png1)
[System.IO.File]::WriteAllBytes("$tmp\logo2.png", $png2)

# --- Limpieza previa: artefactos de corridas anteriores ---
$oldLogos = & $mysql -uroot -N -e "SELECT logo FROM portal2026.partners WHERE name IN ('Ente Prueba Upload','Ente Prueba Upload v2') AND logo IS NOT NULL;" 2>&1
foreach ($lg in ($oldLogos -split "`n")) {
    $lg = "$lg".Trim()
    if ($lg) {
        if (Test-Path "$storagePartners\$lg") { Remove-Item "$storagePartners\$lg" -Force }
        $lgWp = [System.IO.Path]::ChangeExtension("$storagePartners\$lg", 'webp')
        if (Test-Path $lgWp) { Remove-Item $lgWp -Force }
    }
}
& $mysql -uroot -N -e "DELETE FROM portal2026.partners WHERE name IN ('Ente Prueba Upload','Ente Prueba Upload v2');" | Out-Null
Write-Output 'pre-limpieza: artefactos de corridas anteriores removidos'

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
        $head = "--$boundary`r`nContent-Disposition: form-data; name=`"$key`"; filename=`"$(Split-Path $path -Leaf)`"`r`nContent-Type: image/png`r`n`r`n"
        $b = $enc.GetBytes($head); $ms.Write($b, 0, $b.Length)
        $ms.Write($bytes, 0, $bytes.Length)
        $tail = "`r`n"; $b = $enc.GetBytes($tail); $ms.Write($b, 0, $b.Length)
    }
    $end = $enc.GetBytes("--$boundary--`r`n"); $ms.Write($end, 0, $end.Length)
    $body = $ms.ToArray(); $ms.Dispose()
    Invoke-WebRequest -Uri $url -Method Post -Body $body -ContentType "multipart/form-data; boundary=$boundary" -WebSession $sess -UseBasicParsing -MaximumRedirection 5
}

# --- 2. Login ---
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$body = "email=admin%40gdc.gob.ve&password=password&_token=$([uri]::EscapeDataString($token))"
$r = Invoke-WebRequest "$base/admin/login" -Method Post -Body $body -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing
if ($r.Content -notmatch 'Admin GDC') { throw 'Login fallido' }
Write-Output 'LOGIN OK'

# --- 3. Crear ente con logo ---
$r = Invoke-WebRequest "$base/admin/entes/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/entes" `
    @{ _token = $token; name = 'Ente Prueba Upload'; url = 'https://example.gob.ve'; order = '1' } `
    @{ logo = "$tmp\logo1.png" } $sess
Write-Output 'POST create OK (respuesta contiene tabla? ' + ($r.Content -match 'Ente Prueba Upload') + ')'

# --- 4. Verificar BD y archivo ---
$row = & $mysql -uroot -N -e "SELECT id, logo FROM portal2026.partners WHERE name='Ente Prueba Upload';" 2>&1
$id, $logo = $row -split "`t"
Write-Output "BD: id=$id logo=$logo"
if (-not $logo) { throw 'logo no guardado en BD' }
if (-not (Test-Path "$storagePartners\$logo")) { throw "archivo no existe: $storagePartners\$logo" }
Write-Output 'ARCHIVO OK'
$logo1Path = $logo
$logo1Wp = [System.IO.Path]::ChangeExtension("$storagePartners\$logo1Path", 'webp')
if (Test-Path $logo1Wp) { Write-Output 'WEBCONV OK (logo convertido a webp en upload)' } else { throw 'sidecar webp no generado en upload de logo' }

# --- 5. Footer publico muestra el logo ---
$r = Invoke-WebRequest "$base/" -UseBasicParsing
if ($r.Content -match [regex]::Escape("storage/$logo1Path")) { Write-Output 'FOOTER OK (logo visible en home)' } else { Write-Output 'FOOTER: logo NO encontrado en home' }

# --- 6. Reemplazar logo (upload nuevo) ---
$r = Invoke-WebRequest "$base/admin/entes/$id/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/entes/$id" `
    @{ _token = $token; _method = 'PUT'; name = 'Ente Prueba Upload'; url = 'https://example.gob.ve'; order = '1'; is_active = '1' } `
    @{ logo = "$tmp\logo2.png" } $sess
$row = & $mysql -uroot -N -e "SELECT logo FROM portal2026.partners WHERE id=$id;" 2>&1
Write-Output "BD tras replace: logo=$row"
if ($row -eq $logo1Path) { throw 'el logo no cambio' }
if (Test-Path "$storagePartners\$logo1Path") { throw 'el archivo viejo NO se borro' } else { Write-Output 'archivo viejo eliminado OK' }
if (Test-Path $logo1Wp) { throw 'el sidecar webp viejo NO se borro' } else { Write-Output 'sidecar webp viejo eliminado OK' }
if (-not (Test-Path "$storagePartners\$row")) { throw 'archivo nuevo no existe' } else { Write-Output 'archivo nuevo OK' }
$logo2Wp = [System.IO.Path]::ChangeExtension("$storagePartners\$row", 'webp')
if (-not (Test-Path $logo2Wp)) { throw 'sidecar webp del logo nuevo no existe' } else { Write-Output 'sidecar webp nuevo OK' }

# --- 7. Quitar logo con checkbox ---
$curLogo = & $mysql -uroot -N -e "SELECT logo FROM portal2026.partners WHERE id=$id;" 2>&1
$r = Invoke-WebRequest "$base/admin/entes/$id/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/entes/$id" `
    @{ _token = $token; _method = 'PUT'; name = 'Ente Prueba Upload'; url = 'https://example.gob.ve'; order = '1'; is_active = '1'; remove_logo = '1' } `
    @{} $sess
$isNull = & $mysql -uroot -N -e "SELECT logo IS NULL FROM portal2026.partners WHERE id=$id;" 2>&1
Write-Output "BD tras quitar: logo IS NULL=$isNull (esperado 1)"
if ($isNull -ne '1') { throw 'logo no se quito en BD' } else { Write-Output 'logo=null OK' }
if (Test-Path "$storagePartners\$curLogo") { throw 'archivo no se borro' } else { Write-Output 'archivo eliminado OK' }
$curWp = [System.IO.Path]::ChangeExtension("$storagePartners\$curLogo", 'webp')
if (Test-Path $curWp) { throw 'sidecar webp no se borro' } else { Write-Output 'sidecar webp eliminado OK' }

# --- 8. Editar sin tocar logo (debe conservar logo null + sin error) ---
$r = Invoke-WebRequest "$base/admin/entes/$id/editar" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/entes/$id" `
    @{ _token = $token; _method = 'PUT'; name = 'Ente Prueba Upload v2'; url = 'https://example.gob.ve'; order = '2'; is_active = '1' } `
    @{} $sess
$name = & $mysql -uroot -N -e "SELECT name FROM portal2026.partners WHERE id=$id;" 2>&1
Write-Output "BD tras editar nombre: $name"

# --- 9. Validacion: subir archivo que no es imagen ---
[System.IO.File]::WriteAllBytes("$tmp\fake.txt", [System.Text.Encoding]::UTF8.GetBytes('hola'))
$r = Invoke-WebRequest "$base/admin/entes/crear" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$r = Send-Multipart "$base/admin/entes" @{ _token = $token; name = 'No Debe Crearse'; order = '0' } @{ logo = "$tmp\fake.txt" } $sess
$cnt = & $mysql -uroot -N -e "SELECT COUNT(*) FROM portal2026.partners WHERE name='No Debe Crearse';" 2>&1
Write-Output "rechazo archivo no-imagen: filas=$cnt (esperado 0)"

# --- 10. Destruir ente de prueba ---
$r = Invoke-WebRequest "$base/admin/entes" -WebSession $sess -UseBasicParsing
$token = Get-Token $r.Content
$boundary = [Guid]::NewGuid().ToString()
$enc = [System.Text.Encoding]::UTF8
$str = "_token=$token`r`n--$boundary--`r`n"
# form simple con method override
$bodyStr = "_method=DELETE&_token=$([uri]::EscapeDataString($token))"
Invoke-WebRequest "$base/admin/entes/$id" -Method Post -Body $bodyStr -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing | Out-Null
$cnt = & $mysql -uroot -N -e "SELECT COUNT(*) FROM portal2026.partners WHERE id=$id;" 2>&1
Write-Output "destroy: filas=$cnt (esperado 0)"

Write-Output '=== RESUMEN: todas las pruebas completadas ==='
