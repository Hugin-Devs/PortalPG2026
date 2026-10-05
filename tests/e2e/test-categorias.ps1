$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }
function Sql($q) { & $mysql -uroot -N -e $q 2>&1 }

Write-Output '--- 1. /noticias: sidebar (desktop) + pills (movil) ---'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.StatusCode -eq 200) '/noticias 200' "status $($r.StatusCode)"
Check ($r.Content -match 'col-lg-3 d-none d-lg-block') 'sidebar desktop presente' 'falta sidebar'
Check ($r.Content -match 'd-lg-none') 'pills movil presentes' 'falta pills movil'
Check ($r.Content -match 'Categor') 'titulo Categorias en sidebar' 'falta titulo Categorias'
Check ($r.Content -match 'action="[^"]*buscar"') 'buscador en sidebar' 'falta buscador'
Check ($r.Content -match 'class="badge bg-light text-dark border"') 'contadores en sidebar' 'falta contadores'
Check ($r.Content -match 'rounded-pill') 'pills con rounded-pill' 'pills mal formadas'

Write-Output '--- 2. Categorias activas con publicaciones aparecen ---'
$slugs = (Sql "SELECT c.slug FROM portal2026.categories c WHERE c.is_active = 1 AND EXISTS (SELECT 1 FROM portal2026.posts p WHERE p.category_id = c.id AND p.status = 'published') ORDER BY c.`order`, c.name;") -split "`n"
$found = 0
foreach ($s in $slugs) {
    if (-not $s) { continue }
    $n = ([regex]::Matches($r.Content, [regex]::Escape("categoria/$s"))).Count
    if ($n -ge 2) { $found++ } else { Bad "categoria '$s' con $n enlaces (esperado >=2 pills+sidebar)" }
}
Check ($found -gt 0) "$found categorias enlazadas en pills+sidebar" 'ninguna categoria enlazada'
Check (([regex]::Matches($r.Content, '>Todas')).Count -ge 2) 'enlace Todas en pills y sidebar' 'falta enlace Todas'

Write-Output '--- 3. /categoria/{slug}: nav coherente + estado activo ---'
$first = ($slugs | Where-Object { $_ } | Select-Object -First 1)
if ($first) {
    $r2 = Invoke-WebRequest "$base/categoria/$first" -UseBasicParsing
    Check ($r2.StatusCode -eq 200) "/categoria/$first 200" "status $($r2.StatusCode)"
    Check ($r2.Content -match 'col-lg-3 d-none d-lg-block') 'sidebar en vista de categoria' 'falta sidebar'
    Check ($r2.Content -match 'd-lg-none') 'pills en vista de categoria' 'falta pills'
    Check ($r2.Content -match 'text-primary fw-bold') 'categoria activa resaltada' 'sin estado activo'
    Check ($r2.Content -match 'breadcrumb') 'breadcrumb presente' 'falta breadcrumb'
} else {
    Ok 'sin categorias con publicaciones: se omite vista de categoria'
}

Write-Output '--- 4. Footer: Gacetas + orden del navbar ---'
$order = @('Inicio', 'Noticias', 'Entes', 'Sobre Nosotros', 'Gacetas Oficiales', 'Contacto')
$idx = @{}
foreach ($n in $order) { $idx[$n] = $r.Content.IndexOf('footer-text">' + $n) }
Check ($idx['Gacetas Oficiales'] -gt 0) 'footer tiene Gacetas Oficiales' 'falta Gacetas en footer'
$ok = $true
for ($i = 1; $i -lt $order.Count; $i++) {
    if ($idx[$order[$i]] -le $idx[$order[$i - 1]]) { $ok = $false }
}
Check $ok "orden footer: $($order -join ' | ')" "orden incorrecto: $(($order | ForEach-Object { "$_=$($idx[$_])" }) -join ' ')"
Check ($r.Content -match 'gacetas') 'footer linkea a /gacetas' 'falta link a gacetas'

Write-Output '--- 5. Smoke ---'
foreach ($u in @('/', '/noticias', '/gacetas', '/entes', '/contacto', '/sobre-nosotros')) {
    try { $r3 = Invoke-WebRequest "$base$u" -UseBasicParsing -TimeoutSec 10; Check ($r3.StatusCode -eq 200) "$u 200" "$u $($r3.StatusCode)" }
    catch { Bad "$u ERROR" }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== CATEGORIAS: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== CATEGORIAS: $fail FALLAS ==="; exit 1 }
