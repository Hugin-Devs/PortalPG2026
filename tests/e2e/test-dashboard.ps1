$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($cond, $ok, $bad) { if ($cond) { Ok $ok } else { Bad $bad } }
function Get-Token($html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    throw 'CSRF token no encontrado'
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
function StatNum($html, $name) {
    $m = [regex]::Match($html, 'data-stat="' + $name + '"(?:(?!data-stat|data-chart)[\s\S]){0,800}?class="fs-2 fw-bold stat-number[^"]*">(\d+)<')
    if ($m.Success) { return [int]$m.Groups[1].Value } else { return $null }
}
$tzCaracas = [System.TimeZoneInfo]::FindSystemTimeZoneById('Venezuela Standard Time')
$nowCaracas = [System.TimeZoneInfo]::ConvertTimeFromUtc([DateTime]::UtcNow, $tzCaracas)
$monthStart = $nowCaracas.ToString('yyyy-MM-01')
$window30 = $nowCaracas.AddDays(-29).ToString('yyyy-MM-dd')

Write-Output '--- 1. Sin sesion -> redirect a login ---'
try {
    $r = Invoke-WebRequest "$base/admin" -UseBasicParsing -MaximumRedirection 5
    Check ($r.BaseResponse.ResponseUri.AbsoluteUri -match '/admin/login') 'sin sesion -> /admin/login' "no redirige ($($r.BaseResponse.ResponseUri.AbsoluteUri))"
} catch {
    Check ($_.Exception.Response.StatusCode.value__ -in 302, 401, 419) 'sin sesion -> 302/401/419' "status $($_.Exception.Response.StatusCode.value__)"
}

Write-Output '--- 2. Superadmin: tarjetas, conteos y logout ---'
$super = Login 'admin@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin" -WebSession $super -UseBasicParsing
Check ($r.StatusCode -eq 200) 'GET /admin 200' "status $($r.StatusCode)"
$html = $r.Content
Check ($html -match '<h1[^>]*>Dashboard</h1>') 'h1 Dashboard' 'sin h1 Dashboard'
Check ($html -match 'Bienvenido, ') 'saludo con nombre' 'sin saludo'
$cards = ([regex]::Matches($html, 'data-stat="(\w+)"') | ForEach-Object { $_.Groups[1].Value })
foreach ($c in @('noticias', 'visitas', 'usuarios', 'mensajes', 'gacetas', 'medios', 'entes', 'videos', 'categorias', 'ultimas')) {
    Check ($cards -contains $c) "tarjeta data-stat=$c" "falta tarjeta $c"
}
$n = [int](Sql "SELECT COUNT(*) FROM portal2026.posts WHERE deleted_at IS NULL;")
Check ((StatNum $html 'noticias') -eq $n) "noticias total=$n (tarjeta=$((StatNum $html 'noticias')))" 'conteo noticias no coincide'
$u = [int](Sql "SELECT COUNT(*) FROM portal2026.users;")
Check ((StatNum $html 'usuarios') -eq $u) "usuarios total=$u (tarjeta=$((StatNum $html 'usuarios')))" 'conteo usuarios no coincide'
$m = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE day >= '$monthStart';")
$cardVisitas = StatNum $html 'visitas'
Check (($null -ne $cardVisitas) -and ($cardVisitas -eq $m)) "visitas mes=$m (tarjeta=$cardVisitas)" "conteo visitas no coincide: tarjeta=$cardVisitas SQL=$m"
$g = [int](Sql "SELECT COUNT(*) FROM portal2026.gacetas;")
Check ((StatNum $html 'gacetas') -eq $g) "gacetas=$g" 'conteo gacetas no coincide'
$med = [int](Sql "SELECT COUNT(*) FROM portal2026.media;")
Check ((StatNum $html 'medios') -eq $med) "medios=$med" 'conteo medios no coincide'
$e = [int](Sql "SELECT COUNT(*) FROM portal2026.partners;")
Check ((StatNum $html 'entes') -eq $e) "entes=$e" 'conteo entes no coincide'
$v = [int](Sql "SELECT COUNT(*) FROM portal2026.videos;")
Check ((StatNum $html 'videos') -eq $v) "videos=$v" 'conteo videos no coincide'
$cat = [int](Sql "SELECT COUNT(*) FROM portal2026.categories;")
Check ((StatNum $html 'categorias') -eq $cat) "categorias=$cat" 'conteo categorias no coincide'
$logoutForms = ([regex]::Matches($html, 'action="[^"]*admin/logout"')).Count
Check ($logoutForms -eq 1) "exactamente 1 form logout (got $logoutForms)" "forms logout: $logoutForms (stub duplicado?)"

Write-Output '--- 3. Ultimas noticias publicadas ---'
$row = (Sql "SELECT id, title FROM portal2026.posts WHERE status='published' AND deleted_at IS NULL ORDER BY published_at DESC, id DESC LIMIT 1;")
$pidU, $titleU = $row -split "`t", 2
Check ($titleU) "ultima publicada: $titleU" 'sin posts publicados'
$m = [regex]::Match($html, 'data-stat="ultimas"(?:(?!data-stat)[\s\S]){0,4000}?' + [regex]::Escape($titleU))
Check ($m.Success) 'titulo de la ultima aparece en bloque ultimas' 'titulo no esta en el bloque'
Check ($html -match ('href="[^"]*admin/noticias/' + $pidU + '/editar"')) 'link Editar a la ultima noticia' 'sin link de edicion'

Write-Output '--- 4. Tracking: lectura de articulo registra daily_views ---'
$slug = (Sql "SELECT slug FROM portal2026.posts WHERE status='published' AND deleted_at IS NULL ORDER BY id LIMIT 1;")
$slug = "$slug".Trim()
$pidT = [int](Sql "SELECT id FROM portal2026.posts WHERE slug='$slug';")
$beforeV = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE post_id=$pidT;")
$beforeViews = [int](Sql "SELECT views FROM portal2026.posts WHERE id=$pidT;")
$beforeMonth = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE day >= '$monthStart';")
$r = Invoke-WebRequest "$base/noticias/$slug" -UseBasicParsing
Check ($r.StatusCode -eq 200) 'GET articulo 200' "status $($r.StatusCode)"
$afterV = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE post_id=$pidT;")
$afterViews = [int](Sql "SELECT views FROM portal2026.posts WHERE id=$pidT;")
$afterMonth = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE day >= '$monthStart';")
Check ($afterV -eq ($beforeV + 1)) "daily_views del post +1 ($beforeV -> $afterV)" "no incremento: $beforeV -> $afterV"
Check ($afterViews -eq ($beforeViews + 1)) "contador views lifetime +1 ($beforeViews -> $afterViews)" "views no incremento: $beforeViews -> $afterViews"
Check ($afterMonth -eq ($beforeMonth + 1)) "visitas del mes +1 ($beforeMonth -> $afterMonth)" "mes no incremento"
$r = Invoke-WebRequest "$base/admin" -WebSession $super -UseBasicParsing
Check ((StatNum $r.Content 'visitas') -eq $afterMonth) "tarjeta visitas refleja el nuevo mes ($afterMonth)" "tarjeta=$((StatNum $r.Content 'visitas')) vs SQL=$afterMonth"

Write-Output '--- 5. Grafico de 30 dias ---'
$html = $r.Content
Check ($html -match 'data-chart="daily"') 'contenedor data-chart=daily' 'sin contenedor del grafico'
$bars = ([regex]::Matches($html, 'class="dash-bar')).Count
Check ($bars -eq 30) "30 barras (got $bars)" "barras: $bars"
$m = [regex]::Match($html, 'data-chart="daily"(?:(?!data-stat)[\s\S]){0,600}?([\d.,]+) en el per')
Check ($m.Success) 'total del periodo visible' 'sin total del periodo'
$periodTotal = [int](Sql "SELECT COALESCE(SUM(count),0) FROM portal2026.daily_views WHERE day >= '$window30';")
$shownTotal = 0
if ($m.Success) { $shownTotal = [int]($m.Groups[1].Value -replace '[.,]', '') }
Check ($shownTotal -eq $periodTotal) "total periodo=$periodTotal (visto=$shownTotal)" 'total del periodo no coincide'

Write-Output '--- 6. Editor: solo noticias/visitas/medios ---'
$editor = Login 'editor@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin" -WebSession $editor -UseBasicParsing
Check ($r.StatusCode -eq 200) 'editor 200' "status $($r.StatusCode)"
$cardsE = ([regex]::Matches($r.Content, 'data-stat="(\w+)"') | ForEach-Object { $_.Groups[1].Value })
foreach ($c in @('noticias', 'visitas', 'medios')) { Check ($cardsE -contains $c) "editor ve $c" "editor no ve $c" }
foreach ($c in @('usuarios', 'mensajes', 'gacetas', 'entes', 'videos', 'categorias')) { Check ($cardsE -notcontains $c) "editor NO ve $c" "editor ve $c (debio ocultarse)" }

Write-Output '--- 7. Admin: todo menos usuarios y videos ---'
$admin = Login 'admin2@gdc.gob.ve'
$r = Invoke-WebRequest "$base/admin" -WebSession $admin -UseBasicParsing
Check ($r.StatusCode -eq 200) 'admin 200' "status $($r.StatusCode)"
$cardsA = ([regex]::Matches($r.Content, 'data-stat="(\w+)"') | ForEach-Object { $_.Groups[1].Value })
foreach ($c in @('noticias', 'visitas', 'mensajes', 'gacetas', 'medios', 'entes', 'categorias')) { Check ($cardsA -contains $c) "admin ve $c" "admin no ve $c" }
foreach ($c in @('usuarios', 'videos')) { Check ($cardsA -notcontains $c) "admin NO ve $c" "admin ve $c (debio ocultarse)" }

Write-Output '--- 8. Smoke 3 roles ---'
foreach ($pair in @(@($super, 'superadmin'), @($editor, 'editor'), @($admin, 'admin'))) {
    try { $r2 = Invoke-WebRequest "$base/admin" -WebSession $pair[0] -UseBasicParsing -TimeoutSec 15; Check ($r2.StatusCode -eq 200) "/admin 200 ($($pair[1]))" "$($pair[1]) -> $($r2.StatusCode)" }
    catch { Bad "/admin ERROR ($($pair[1]))" }
}

Write-Output ''
Write-Output 'Nota: las filas daily_views creadas por esta suite se conservan (datos agregados reales, sin borrar).'
if ($fail -eq 0) { Write-Output '=== DASHBOARD: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== DASHBOARD: $fail FALLAS ==="; exit 1 }
