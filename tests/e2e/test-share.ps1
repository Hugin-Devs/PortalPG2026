$ErrorActionPreference = 'Stop'
$base = 'http://portal2026.test'
$mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$fail = 0
function Ok($m) { Write-Output "  [OK] $m" }
function Bad($m) { Write-Output "  [FAIL] $m"; $script:fail++ }
function Check($c, $ok, $bad) { if ($c) { Ok $ok } else { Bad $bad } }

Write-Output '--- 1. Botones de compartir en detalle de noticia ---'
$slug = (& $mysql -uroot -N -e 'SELECT slug FROM portal2026.posts WHERE status=''published'' ORDER BY id LIMIT 1;') -join ''
Check ($slug -ne '') "slug de prueba: $slug" 'sin posts publicados'
$r = Invoke-WebRequest "$base/noticias/$slug" -UseBasicParsing -TimeoutSec 15
Check ($r.StatusCode -eq 200) 'articulo 200' "status $($r.StatusCode)"
Check ($r.Content -match 'COMPARTIR:') 'etiqueta COMPARTIR' 'sin etiqueta COMPARTIR'
Check ($r.Content -match 'facebook\.com/sharer/sharer\.php\?u=') 'link Facebook' 'sin link Facebook'
Check ($r.Content -match 'twitter\.com/intent/tweet') 'link X' 'sin link X'
Check ($r.Content -match 'wa\.me/\?text=') 'link WhatsApp' 'sin link WhatsApp'
Check ($r.Content -match 't\.me/share/url') 'link Telegram' 'sin link Telegram'
Check ($r.Content -match 'id="copyLinkBtn"') 'boton Copiar enlace' 'sin boton copiar'
Check ($r.Content -match 'copyLinkText') 'span feedback copiar' 'sin span copiar'
$n = ([regex]::Matches($r.Content, 'target="_blank" rel="noopener noreferrer"')).Count
Check ($n -ge 4) ">=4 enlaces con target blank + noopener (got $n)" "noopener insuficiente (got $n)"
Check ($r.Content -match 'facebook\.com/sharer/sharer\.php\?u=[a-z]+%3A%2F%2F') 'URL codificada (%3A%2F%2F)' 'URL sin codificar'
Check ($r.Content -match 'navigator\.clipboard\.writeText') 'JS vanilla copiar' 'sin JS copiar'
Check (-not ($r.Content -match 'jquery|cdn\.jsdelivr.*share|addthis|sharethis')) 'sin librerias de terceros' 'libreria de compartir detectada'

Write-Output '--- 2. Lazy loading en grids (bajo el fold) ---'
$r = Invoke-WebRequest "$base/" -UseBasicParsing
Check ($r.StatusCode -eq 200) 'home 200' "home $($r.StatusCode)"
$hero = [regex]::Match($r.Content, '<img src="[^"]*" alt="[^"]*" class="position-absolute')
Check (-not ($r.Content -match 'position-absolute[^>]*loading="lazy"')) 'hero SIN lazy (eager, LCP)' 'hero tiene lazy'
$grid = [regex]::Matches($r.Content, 'card-img-top[^>]*loading="lazy"').Count
Check ($grid -ge 1) "grid home con lazy (got $grid)" 'grid home sin lazy'
$r = Invoke-WebRequest "$base/noticias" -UseBasicParsing
Check ($r.Content -match 'card-img-top[^>]*loading="lazy"') 'grid /noticias con lazy' '/noticias sin lazy'
$r = Invoke-WebRequest "$base/entes" -UseBasicParsing
Check ($r.Content -match 'height="70" loading="lazy"') 'grid /entes con lazy' '/entes sin lazy'
$r = Invoke-WebRequest "$base/sobre-nosotros" -UseBasicParsing
Check ($r.Content -match 'height="30" loading="lazy"') 'logos footer con lazy' 'logos footer sin lazy'
$r = Invoke-WebRequest "$base/noticias/$slug" -UseBasicParsing
Check (-not ($r.Content -match 'img-fluid[^>]*loading="lazy"')) 'imagen destacada del articulo eager' 'articulo con lazy (LCP)'

Write-Output '--- 3. Smoke ---'
foreach ($p in @('/', '/noticias', '/contacto', '/entes')) {
    try { $r2 = Invoke-WebRequest "$base$p" -UseBasicParsing -TimeoutSec 10; Check ($r2.StatusCode -eq 200) "$p 200" "$p $($r2.StatusCode)" }
    catch { Bad "$p ERROR" }
}

Write-Output ''
if ($fail -eq 0) { Write-Output '=== SHARE+LAZY: TODAS LAS PRUEBAS EN VERDE ===' }
else { Write-Output "=== $fail FALLAS ==="; exit 1 }
