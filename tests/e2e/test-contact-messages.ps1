$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Get-Token($html) { if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }; throw 'token no encontrado' }
function Post-Form($url, $fields, $sess) {
    $pairs = foreach ($k in $fields.Keys) { "$k=$([uri]::EscapeDataString($fields[$k]))" }
    $r = Invoke-WebRequest -Uri $url -Method Post -Body ($pairs -join '&') -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing -MaximumRedirection 5
    $r | Add-Member -NotePropertyName FinalUri -NotePropertyValue $r.BaseResponse.ResponseUri.AbsoluteUri -Force
    $r
}
function Login($email, $sess) {
    $r = Invoke-WebRequest "$base/admin/login" -WebSession $sess -UseBasicParsing
    $t = Get-Token $r.Content
    Invoke-WebRequest "$base/admin/login" -Method Post -Body "email=$([uri]::EscapeDataString($email))&password=password&_token=$([uri]::EscapeDataString($t))" -ContentType 'application/x-www-form-urlencoded' -WebSession $sess -UseBasicParsing
}
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

Write-Output '--- 0. Estado inicial ---'
Sql "DELETE FROM portal2026.contacts WHERE subject LIKE 'E2E%';" | Out-Null
$baseCount = ((Sql 'SELECT COUNT(*) FROM portal2026.contacts;') -join '').Trim()
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject LIKE 'E2E%';") -eq '0') 'sin filas E2E al inicio (previas se preservan)' 'habia filas E2E previas'

Write-Output '--- 1. Enviar formulario publico (/contacto) ---'
$sessPub = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/contacto" -WebSession $sessPub -UseBasicParsing
$token = Get-Token $r.Content
$r = Post-Form "$base/contacto" @{
    _token = $token; name = 'Juan E2E'; email = 'juan@ejemplo.com'
    subject = 'E2E Primer Mensaje'; message = 'Hola, este es el mensaje de prueba 1.'
} $sessPub
Check ($r.Content -match 'Mensaje enviado correctamente') 'confirmacion de exito visible' 'sin confirmacion'
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject = 'E2E Primer Mensaje';") -eq '1') 'fila persistida en contacts' 'no se guardo'
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject = 'E2E Primer Mensaje' AND read_at IS NULL;") -eq '1') 'read_at NULL al llegar' 'read_at no NULL'

Write-Output '--- 2. Segundo mensaje (para probar delete) ---'
$r = Post-Form "$base/contacto" @{
    _token = $token; name = 'Maria E2E'; email = 'maria@ejemplo.com'
    subject = 'E2E Segundo Mensaje'; message = 'Mensaje de prueba 2.'
} $sessPub
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject = 'E2E Segundo Mensaje';") -eq '1') 'segunda fila persistida' 'no se guardo'

Write-Output '--- 3. Validacion: email invalido no persiste ---'
$r = Post-Form "$base/contacto" @{
    _token = $token; name = 'Fake'; email = 'no-es-email'
    subject = 'E2E No Debe Guardarse'; message = 'x'
} $sessPub
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject = 'E2E No Debe Guardarse';") -eq '0') 'email invalido rechazado' 'se guardo igual'

Write-Output '--- 4. Bandeja admin: lista ---'
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'admin@gdc.gob.ve' $sess
if ($r.Content -notmatch 'Admin GDC') { throw 'login fallido' }
$r = Invoke-WebRequest "$base/admin/contacto/mensajes" -WebSession $sess -UseBasicParsing
Check ($r.StatusCode -eq 200) 'GET mensajes -> 200' "status $($r.StatusCode)"
Check ($r.Content -match 'E2E Primer Mensaje') 'lista muestra asunto 1' 'falta asunto 1'
Check ($r.Content -match 'E2E Segundo Mensaje') 'lista muestra asunto 2' 'falta asunto 2'
Check ($r.Content -match 'Nuevo') 'badge Nuevo presente' 'falta badge'
Check ($r.Content -match 'juan@ejemplo\.com') 'email visible' 'falta email'
Check ($r.Content -match '>Mensajes</a>') 'item sidebar Mensajes visible' 'sidebar sin Mensajes'

Write-Output '--- 5. Detalle marca leido ---'
$id = Sql "SELECT id FROM portal2026.contacts WHERE subject = 'E2E Primer Mensaje';"
$r = Invoke-WebRequest "$base/admin/contacto/mensajes/$id" -WebSession $sess -UseBasicParsing
Check ($r.StatusCode -eq 200) "GET detalle -> 200 (id=$id)" "status $($r.StatusCode)"
Check ($r.Content -match 'Hola, este es el mensaje de prueba 1\.') 'cuerpo del mensaje visible' 'falta cuerpo'
Check ($r.Content -match 'juan@ejemplo\.com') 'remitente visible' 'falta remitente'
Check ((Sql "SELECT read_at IS NOT NULL FROM portal2026.contacts WHERE id = $id;") -eq '1') 'read_at marcado en BD' 'read_at sin marcar'
$r = Invoke-WebRequest "$base/admin/contacto/mensajes" -WebSession $sess -UseBasicParsing
Check ($r.Content -match 'Le.do') 'lista refleja Leido' 'no refleja Leido'

Write-Output '--- 6. Borrar mensaje ---'
$r = Post-Form "$base/admin/contacto/mensajes/$id" @{ _token = (Get-Token (Invoke-WebRequest "$base/admin/contacto/mensajes" -WebSession $sess -UseBasicParsing).Content); _method = 'DELETE' } $sess
Check ($r.FinalUri -match '/admin/contacto/mensajes$') 'DELETE redirige a lista' "no redirigio: $($r.FinalUri)"
Check ((Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE id = $id;") -eq '0') 'fila eliminada' 'fila no eliminada'
Check ($r.Content -match 'Mensaje eliminado correctamente') 'alerta de exito' 'sin alerta'

Write-Output '--- 7. Permisos: admin2 ve, editor no ---'
$sess2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'admin2@gdc.gob.ve' $sess2
$r = Invoke-WebRequest "$base/admin/contacto/mensajes" -WebSession $sess2 -UseBasicParsing
Check ($r.StatusCode -eq 200) 'admin -> 200 en mensajes' "admin: $($r.StatusCode)"
$sess3 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Login 'editor@gdc.gob.ve' $sess3
try {
    $r = Invoke-WebRequest "$base/admin/contacto/mensajes" -WebSession $sess3 -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    Check ($false) '' "editor obtuvo $($r.StatusCode) (esperado 403)"
} catch {
    $sc = [int]$_.Exception.Response.StatusCode
    Check ($sc -eq 403) 'editor -> 403 en mensajes' "editor: $sc"
}
$r = Invoke-WebRequest "$base/admin" -WebSession $sess3 -UseBasicParsing
Check ($r.Content -notmatch '>Mensajes</a>') 'editor NO ve item Mensajes' 'editor SI ve item'

Write-Output '--- 8. Smoke ---'
foreach ($p in @('/', '/contacto', '/sobre-nosotros')) {
    try { $r = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r.StatusCode -eq 200) "$p 200" "$p $($r.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output '--- 9. Cleanup ---'
Sql "DELETE FROM portal2026.contacts WHERE subject LIKE 'E2E%';" | Out-Null
$cnt = Sql "SELECT COUNT(*) FROM portal2026.contacts WHERE subject LIKE 'E2E%';"
$tot = ((Sql 'SELECT COUNT(*) FROM portal2026.contacts;') -join '').Trim()
Check (($cnt -eq '0') -and ($tot -eq $baseCount)) "filas E2E eliminadas; previas intactas (total $tot)" "restos: E2E=$cnt total=$tot (esperado $baseCount)"

Write-Output ''
if ($fail -eq 0) { Write-Output '=== CONTACTO MENSAJES: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
