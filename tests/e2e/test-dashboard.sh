#!/usr/bin/env bash
# test-dashboard.sh — port de test-dashboard.ps1 (tarjetas, conteos SQL, tracking, grafico 30 dias, roles)
# Timezone: el servidor y la BD usan America/Caracas (-04, sin DST), igual que el .ps1 original.
E2E_SUITE=dashboard
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

stat_num() { # $1 = nombre de tarjeta (data-stat); -z: match multiliena (GNU grep -P es linea a linea)
# Sin lookahead repetido ((?:(?!…)…){0,800}): el PCRE de Git Bash revienta con
# "regular expression is too large". El bloque data-stat → stat-number es
# contiguo en la vista, así que el lazy {0,800} basta (R1).
grep -zoP "data-stat=\"$1\"[\s\S]{0,800}?class=\"fs-2 fw-bold stat-number[^\"]*\">\K\d+" "$HTTP_BODY" | tr -dc '0-9'
}

monthStart=$(date +%Y-%m-01)
window30=$(date -d '29 days ago' +%Y-%m-%d)

echo '--- 1. Sin sesion -> redirect a login ---'
COOKIE_JAR="$E2E_TMP/e2e-${E2E_SUITE}-anon.jar"; rm -f "$COOKIE_JAR"
http "$E2E_BASE/admin"
check '[[ "$HTTP_FINAL" == */admin/login ]]' "sin sesion -> /admin/login (final: $HTTP_FINAL)" "no redirige ($HTTP_FINAL)"

echo '--- 2. Superadmin: tarjetas, conteos y logout ---'
login 'admin@gdc.gob.ve'
http "$E2E_BASE/admin"
check '[ "$HTTP_STATUS" = 200 ]' 'GET /admin 200' "status $HTTP_STATUS"
check 'grep -qE "<h1[^>]*>Dashboard</h1>" "$HTTP_BODY"' 'h1 Dashboard' 'sin h1 Dashboard'
check 'grep -q "Bienvenido, " "$HTTP_BODY"' 'saludo con nombre' 'sin saludo'
cards=$(grep -oP 'data-stat="\K\w+' "$HTTP_BODY")
for c in noticias visitas usuarios mensajes gacetas medios entes videos categorias ultimas; do
  check "printf '%s\n' \"\$cards\" | grep -qx '$c'" "tarjeta data-stat=$c" "falta tarjeta $c"
done
n=$(sql 'SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL;')
card=$(stat_num noticias)
check '[ "$card" = "$n" ]' "noticias total=$n (tarjeta=$card)" 'conteo noticias no coincide'
u=$(sql 'SELECT COUNT(*) FROM users;')
card=$(stat_num usuarios)
check '[ "$card" = "$u" ]' "usuarios total=$u (tarjeta=$card)" 'conteo usuarios no coincide'
m=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE day >= '$monthStart';")
card=$(stat_num visitas)
check '[ -n "$card" ] && [ "$card" = "$m" ]' "visitas mes=$m (tarjeta=$card)" "conteo visitas no coincide: tarjeta=$card SQL=$m"
g=$(sql 'SELECT COUNT(*) FROM gacetas;')
card=$(stat_num gacetas)
check '[ "$card" = "$g" ]' "gacetas=$g" 'conteo gacetas no coincide'
med=$(sql 'SELECT COUNT(*) FROM media;')
card=$(stat_num medios)
check '[ "$card" = "$med" ]' "medios=$med" 'conteo medios no coincide'
e=$(sql 'SELECT COUNT(*) FROM partners;')
card=$(stat_num entes)
check '[ "$card" = "$e" ]' "entes=$e" 'conteo entes no coincide'
v=$(sql 'SELECT COUNT(*) FROM videos;')
card=$(stat_num videos)
check '[ "$card" = "$v" ]' "videos=$v" 'conteo videos no coincide'
cat=$(sql 'SELECT COUNT(*) FROM categories;')
card=$(stat_num categorias)
check '[ "$card" = "$cat" ]' "categorias=$cat" 'conteo categorias no coincide'
logoutForms=$(grep -o 'action="[^"]*admin/logout"' "$HTTP_BODY" | wc -l)
check '[ "$logoutForms" = 1 ]' "exactamente 1 form logout (got $logoutForms)" "forms logout: $logoutForms (stub duplicado?)"

echo '--- 2b. Sidebar sticky (admin-sidebar) ---'
# El aside del layout admin lleva la clase .admin-sidebar (position:sticky en
# app.scss): el menú permanece visible al hacer scroll en páginas largas.
check 'grep -q "admin-sidebar" "$HTTP_BODY"' 'aside con clase admin-sidebar' 'falta la clase en el layout'
cssOk=0
for f in "$E2E_ROOT"/public/build/assets/app-*.css; do
  [ -f "$f" ] || continue
  # grep -z = fichero entero como una sola línea (el CSS viene minificado en
  # una sola línea); la regla debe llevar position:sticky dentro de su bloque.
  if grep -zq 'admin-sidebar{[^}]*position:[[:space:]]*sticky' "$f"; then cssOk=1; fi
done
check '[ "$cssOk" = 1 ]' 'CSS compilado con .admin-sidebar{...position:sticky}' 'regla ausente del build (npm run build)'

echo '--- 3. Ultimas noticias publicadas ---'
row=$(sqlt "SELECT id, title FROM posts WHERE status='published' AND deleted_at IS NULL AND (published_at IS NULL OR published_at <= now()) ORDER BY published_at DESC, id DESC LIMIT 1;")
pidU="${row%%$'\t'*}"; titleU="${row#*$'\t'}"
check '[ -n "$titleU" ]' "ultima publicada: $titleU" 'sin posts publicados'
# Nota: el .ps1 usaba ventana {0,4000}; grep -P falla con ese counted repeat
# ("regular expression is too large"), se usa lazy *? acotado por el lookahead (limitado por el proximo data-stat).
check "grep -zqP \"data-stat=\\\"ultimas\\\"(?:(?!data-stat)[\\s\\S])*?$(re_escape "$titleU")\" \"\$HTTP_BODY\"" 'titulo de la ultima aparece en bloque ultimas' 'titulo no esta en el bloque'
check "grep -qE \"href=\\\"[^\\\"]*admin/noticias/$pidU/editar\\\"\" \"\$HTTP_BODY\"" 'link Editar a la ultima noticia' 'sin link de edicion'

echo '--- 4. Tracking: lectura de articulo registra daily_views ---'
slug=$(sql "SELECT slug FROM posts WHERE status='published' AND deleted_at IS NULL AND (published_at IS NULL OR published_at <= now()) ORDER BY id LIMIT 1;")
pidT=$(sql "SELECT id FROM posts WHERE slug='$slug';")
beforeV=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE post_id=$pidT;")
beforeViews=$(sql "SELECT views FROM posts WHERE id=$pidT;")
beforeMonth=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE day >= '$monthStart';")
http "$E2E_BASE/noticias/$slug"
check '[ "$HTTP_STATUS" = 200 ]' 'GET articulo 200' "status $HTTP_STATUS"
afterV=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE post_id=$pidT;")
afterViews=$(sql "SELECT views FROM posts WHERE id=$pidT;")
afterMonth=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE day >= '$monthStart';")
check '[ "$afterV" = $((beforeV + 1)) ]' "daily_views del post +1 ($beforeV -> $afterV)" "no incremento: $beforeV -> $afterV"
check '[ "$afterViews" = $((beforeViews + 1)) ]' "contador views lifetime +1 ($beforeViews -> $afterViews)" "views no incremento: $beforeViews -> $afterViews"
check '[ "$afterMonth" = $((beforeMonth + 1)) ]' "visitas del mes +1 ($beforeMonth -> $afterMonth)" 'mes no incremento'
http "$E2E_BASE/admin"
card=$(stat_num visitas)
check '[ "$card" = "$afterMonth" ]' "tarjeta visitas refleja el nuevo mes ($afterMonth)" "tarjeta=$card vs SQL=$afterMonth"

echo '--- 4b. Tracking: visitas al portal (home + footer movil, dedup sesion/dia) ---'
sessionJar="$COOKIE_JAR"
http "$E2E_BASE/"
check '[ "$HTTP_STATUS" = 200 ]' 'GET home 200' "home -> $HTTP_STATUS"
check 'grep -q "VISITAS AL PORTAL" "$HTTP_BODY"' 'widget VISITAS AL PORTAL en home' 'sin widget de visitas'
check 'grep -q "visitantes únicos acumulados" "$HTTP_BODY"' 'subtitulo del contador' 'sin subtitulo del contador'
check 'grep -q "d-none d-lg-block" "$HTTP_BODY"' 'contador del sidebar solo en lg+' 'falta breakpoint del sidebar'
check 'grep -q "d-lg-none" "$HTTP_BODY"' 'contador en el footer movil (home)' 'falta bloque del footer movil'
v1=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_visits;")
http "$E2E_BASE/"
v2=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_visits;")
check '[ "$v2" = "$v1" ]' "refresco de home no suma (sesion/dia: $v1)" "F5 inflo: $v1 -> $v2"
todayV=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_visits WHERE day = CURRENT_DATE;")
check '[ "$todayV" -ge 1 ]' "daily_visits de hoy registrada ($todayV)" 'sin fila de hoy'
COOKIE_JAR="$E2E_TMP/e2e-${E2E_SUITE}-bot.jar"; rm -f "$COOKIE_JAR"
http "$E2E_BASE/" -A 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
vBot=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_visits;")
check '[ "$vBot" = "$v2" ]' 'bot no suma visita' "bot inflo: $v2 -> $vBot"
# Restaura la sesion y el cuerpo del dashboard para la seccion 5.
COOKIE_JAR="$sessionJar"
http "$E2E_BASE/admin"

echo '--- 5. Grafico de 30 dias ---'
check 'grep -q "data-chart=\"daily\"" "$HTTP_BODY"' 'contenedor data-chart=daily' 'sin contenedor del grafico'
bars=$(grep -o 'class="dash-bar' "$HTTP_BODY" | wc -l)
check '[ "$bars" = 30 ]' "30 barras (got $bars)" "barras: $bars"
shownRaw=$(grep -zoP 'data-chart="daily"(?:(?!data-stat)[\s\S]){0,600}?\K[\d.,]+(?= en el per)' "$HTTP_BODY" | tr -dc '0-9.,' | head -c 20)
check '[ -n "$shownRaw" ]' 'total del periodo visible' 'sin total del periodo'
periodTotal=$(sql "SELECT COALESCE(SUM(count),0) FROM daily_views WHERE day >= '$window30';")
shownTotal="${shownRaw//[.,]/}"
check '[ "$shownTotal" = "$periodTotal" ]' "total periodo=$periodTotal (visto=$shownTotal)" 'total del periodo no coincide'

echo '--- 6. Editor: solo noticias/visitas/medios ---'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin"
check '[ "$HTTP_STATUS" = 200 ]' 'editor 200' "status $HTTP_STATUS"
cardsE=$(grep -oP 'data-stat="\K\w+' "$HTTP_BODY")
for c in noticias visitas medios; do
  check "printf '%s\n' \"\$cardsE\" | grep -qx '$c'" "editor ve $c" "editor no ve $c"
done
for c in usuarios mensajes gacetas entes videos categorias; do
  check "! printf '%s\n' \"\$cardsE\" | grep -qx '$c'" "editor NO ve $c" "editor ve $c (debio ocultarse)"
done

echo '--- 7. Admin: todo menos usuarios y videos ---'
login 'admin2@gdc.gob.ve'
http "$E2E_BASE/admin"
check '[ "$HTTP_STATUS" = 200 ]' 'admin 200' "status $HTTP_STATUS"
cardsA=$(grep -oP 'data-stat="\K\w+' "$HTTP_BODY")
for c in noticias visitas mensajes gacetas medios entes categorias; do
  check "printf '%s\n' \"\$cardsA\" | grep -qx '$c'" "admin ve $c" "admin no ve $c"
done
for c in usuarios videos; do
  check "! printf '%s\n' \"\$cardsA\" | grep -qx '$c'" "admin NO ve $c" "admin ve $c (debio ocultarse)"
done

echo '--- 8. Smoke 3 roles ---'
login 'admin@gdc.gob.ve'; jarSuper="$COOKIE_JAR"
login 'editor@gdc.gob.ve'; jarEditor="$COOKIE_JAR"
login 'admin2@gdc.gob.ve'; jarAdmin="$COOKIE_JAR"
for pair in "$jarSuper:superadmin" "$jarEditor:editor" "$jarAdmin:admin"; do
  COOKIE_JAR="${pair%%:*}"
  http "$E2E_BASE/admin"
  check '[ "$HTTP_STATUS" = 200 ]' "/admin 200 (${pair#*:})" "${pair#*:} -> $HTTP_STATUS"
done

echo ''
echo 'Nota: las filas daily_views/daily_visits creadas por esta suite se conservan (datos agregados reales, sin borrar).'
summary 'DASHBOARD'
