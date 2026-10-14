#!/usr/bin/env bash
# test-entes.sh — port de test-entes.ps1 (entes: estado vacio, CRUD, slug, nav, buscador)
# Estilo original: falla dura (throw -> exit 1). Nota: el .ps1 original asumia
# partners preexistentes (>=3 tarjetas en el buscador); el entorno E2E
# (migrate:fresh --seed) arranca vacio, se crea 1 fixture y se elimina al final.
E2E_SUITE=entes
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

fail() { echo "ERROR: $1" >&2; exit 1; }

# --- Login ---
login 'admin@gdc.gob.ve'
grep -q 'Admin GDC' "$HTTP_BODY" || fail 'login fallido'
echo 'LOGIN OK'

# --- 1. /entes vacio: estado vacio + nav activo ---
http "$E2E_BASE/entes"
grep -q 'Pr' "$HTTP_BODY" || fail 'falta estado vacio'
grep -qE '<a class="nav-link active" href="[^"]*">Entes</a>' "$HTTP_BODY" || fail 'nav Entes NO activo en /entes'
echo 'VACIO + NAV ACTIVO OK'

# Fixture: el .ps1 esperaba partners preexistentes (data-name >= 3 tras crear A+B).
sql "DELETE FROM partners WHERE slug='e2e-ente-fixture';" >/dev/null
sql "INSERT INTO partners (name, slug, \"order\", is_active, created_at, updated_at) VALUES ('E2E Ente Fixture', 'e2e-ente-fixture', 998, true, now(), now());" >/dev/null
( cd "$E2E_ROOT" && php artisan cache:clear >/dev/null 2>&1 )

# --- 2. Crear ente A (con url) y B (sin url) desde el panel ---
for p in "Ente Demo A|Descripcion de prueba A.|https://example.gob.ve" \
         "Ente Demo B|Descripcion de prueba B.|"; do
  name="${p%%|*}"; rest="${p#*|}"; desc="${rest%%|*}"; url="${rest#*|}"
  http "$E2E_BASE/admin/entes/crear"
  token=$(require_token "$HTTP_BODY") || exit 1
  http "$E2E_BASE/admin/entes" \
    --data-urlencode "_token=$token" \
    --data-urlencode "name=$name" \
    --data-urlencode "description=$desc" \
    --data-urlencode "url=$url" \
    --data-urlencode 'order=1'
done
rowA=$(sqlt "SELECT slug, description FROM partners WHERE name='Ente Demo A';")
rowB=$(sql "SELECT slug FROM partners WHERE name='Ente Demo B';")
echo "A: $rowA"
echo "B: $rowB"
case "$rowA" in *ente-demo-a*) ;; *) fail 'slug A mal' ;; esac
[ "$rowB" = 'ente-demo-b' ] || fail 'slug B mal'
echo 'CREAR + SLUG OK'

# --- 3. Listado publico: cards con descripcion ---
http "$E2E_BASE/entes"
grep -q 'Ente Demo A' "$HTTP_BODY" || fail 'card A no muestra nombre'
grep -q 'Descripcion de prueba A' "$HTTP_BODY" || fail 'card A no muestra descripcion'
grep -q 'Ir al sitio' "$HTTP_BODY" || fail 'falta boton Ir al sitio (ente con url)'
grep -qE 'href="[^"]*entes/ente-demo-b"' "$HTTP_BODY" || fail 'falta link Ver mas al detalle (ente sin url)'
echo 'LISTADO OK'

# --- 4. Detalle ---
http "$E2E_BASE/entes/ente-demo-a"
grep -q 'Descripcion de prueba A' "$HTTP_BODY" || fail 'detalle A sin descripcion'
http "$E2E_BASE/entes/ente-demo-b"
grep -q 'Ente Demo B' "$HTTP_BODY" || fail 'detalle B fallo'
http "$E2E_BASE/entes/no-existe"
[ "$HTTP_STATUS" = 404 ] || fail "debio dar 404 (got $HTTP_STATUS)"
echo 'DETALLE + 404 OK'

# --- 5. /servicios redirige a /entes ---
http "$E2E_BASE/servicios"
case "$HTTP_FINAL" in */entes) ;; *) fail "redirect fallo: $HTTP_FINAL" ;; esac
echo 'REDIRECT /servicios -> /entes OK'

# --- 6. Renombrar ente B: cambia slug ---
http "$E2E_BASE/admin/entes"
idB=$(sql "SELECT id FROM partners WHERE slug='ente-demo-b';")
http "$E2E_BASE/admin/entes/$idB/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes/$idB" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'name=Ente Demo B Renombrado' \
  --data-urlencode 'description=Descripcion de prueba B.' \
  --data-urlencode 'order=1' \
  --data-urlencode 'is_active=1'
slugB=$(sql "SELECT slug FROM partners WHERE id=$idB;")
echo "slug B tras renombrar: $slugB"
[ "$slugB" = 'ente-demo-b-renombrado' ] || fail 'slug no se actualizo'
http "$E2E_BASE/entes/ente-demo-b-renombrado"
echo 'RENOMBRAR -> SLUG OK'

# --- 7. Nav activo en /noticias y en home ---
http "$E2E_BASE/noticias"
grep -qE '<a class="nav-link active" href="[^"]*">Noticias</a>' "$HTTP_BODY" || fail 'nav Noticias NO activo'
if grep -qE '<a class="nav-link active" href="[^"]*">Entes</a>' "$HTTP_BODY"; then fail 'nav Entes activo indebidamente en /noticias'; fi
echo 'NAV ACTIVO OK'
http "$E2E_BASE/"
if grep -qE '<a class="nav-link active" href="[^"]*"[^>]*>Inicio</a>' "$HTTP_BODY"; then
  echo 'HOME OK'
elif ! grep -q 'nav-link active' "$HTTP_BODY"; then
  fail 'ningun nav activo en home'
else
  echo 'AVISO: revisar cual link esta activo en home'
fi

# --- 7b. Buscador en /entes (filtro client-side) ---
http "$E2E_BASE/entes"
[ "$HTTP_STATUS" = 200 ] || fail "/entes no 200 ($HTTP_STATUS)"
grep -q 'id="enteSearch"' "$HTTP_BODY" || fail 'falta input enteSearch'
grep -q 'type="search"' "$HTTP_BODY" || fail 'el input no es type=search'
grep -q 'aria-label="Buscar entre los entes afiliados"' "$HTTP_BODY" || fail 'falta aria-label del buscador'
grep -q 'id="entesGrid"' "$HTTP_BODY" || fail 'falta entesGrid'
n=$(grep -o 'data-name="' "$HTTP_BODY" | wc -l)
[ "$n" -ge 3 ] || fail "data-name insuficientes ($n, esperado >= 3)"
grep -q 'data-desc="' "$HTTP_BODY" || fail 'falta data-desc en las tarjetas'
grep -q 'id="entesNoResults"' "$HTTP_BODY" || fail 'falta mensaje sin resultados'
grep -q "getElementById('enteSearch')" "$HTTP_BODY" || fail 'falta script del buscador'
grep -q "getElementById('entesGrid')" "$HTTP_BODY" || fail 'script no referencia entesGrid'
grep -q 'normalize' "$HTTP_BODY" || fail 'falta normalizacion de acentos'
grep -q 'classList' "$HTTP_BODY" || fail 'falta alternancia de clase d-none'
if grep -qi 'jquery' "$HTTP_BODY"; then fail 'jquery detectado en la pagina'; fi
echo "BUSCADOR OK ($n tarjetas indexadas)"

# --- 8. Limpieza ---
http "$E2E_BASE/admin/entes"
token=$(require_token "$HTTP_BODY") || exit 1
ids=$(sql "SELECT id FROM partners WHERE name LIKE 'Ente Demo%';")
while IFS= read -r id; do
  [ -z "$id" ] && continue
  http "$E2E_BASE/admin/entes/$id" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
done <<< "$ids"
restan=$(sql "SELECT COUNT(*) FROM partners WHERE name LIKE 'Ente Demo%';")
echo "limpieza: quedan $restan (esperado 0)"
sql "DELETE FROM partners WHERE slug='e2e-ente-fixture';" >/dev/null
( cd "$E2E_ROOT" && php artisan cache:clear >/dev/null 2>&1 )
echo '=== TODAS LAS PRUEBAS OK ==='
