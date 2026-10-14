#!/usr/bin/env bash
# test-gacetas.sh — port de test-gacetas.ps1 (gacetas: upload PDF/imagen, editar, reemplazar, validaciones)
E2E_SUITE=gacetas
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-gacetas"
mkdir -p "$tmp"

# --- Archivos de prueba ---
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/gac-img1.png"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC' | base64 -d > "$tmp/gac-img2.png"
printf '%s\n' '%PDF-1.4' '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj' '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj' '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj' 'trailer<</Root 1 0 R>>' '%%EOF' > "$tmp/gac-test1.pdf"
printf '%s\n' '%PDF-1.4' '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj' '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj' '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 300]>>endobj' 'trailer<</Root 1 0 R>>' '%%EOF' > "$tmp/gac-test2.pdf"
printf 'hola' > "$tmp/gac-fake.txt"

# --- Limpieza quirurgica: SOLO artefactos de corridas anteriores de esta suite ---
while IFS=$'\t' read -r rid rpath; do
  [ -z "$rid" ] && continue
  if [ -n "$rpath" ]; then
    [ -f "$E2E_PUB/$rpath" ] && rm -f "$E2E_PUB/$rpath"
    wp="${rpath%.*}.webp"
    [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
  fi
  sql "DELETE FROM gacetas WHERE id=$rid;" >/dev/null
done < <(sqlt "SELECT id, path FROM gacetas WHERE original_name IN ('gac-test1.pdf','gac-test2.pdf','gac-img1.png','gac-img2.png');")
echo 'pre-limpieza quirurgica: artefactos de la suite removidos (gacetas reales intactas)'
baseCount=$(sql 'SELECT COUNT(*) FROM gacetas;')
echo "gacetas preexistentes (se preservan): $baseCount"

echo '--- 1. Login superadmin + navbar publico ---'
login 'admin@gdc.gob.ve'
ok 'LOGIN superadmin'
http "$E2E_BASE/"
check 'grep -q "Gacetas Oficiales" "$HTTP_BODY"' 'navbar publico tiene item Gacetas Oficiales' 'falta item en navbar'
idx_of() { grep -boF "$1" "$HTTP_BODY" | head -1 | cut -d: -f1; }
iG=$(idx_of 'Gacetas Oficiales'); iS=$(idx_of 'Sobre Nosotros'); iI=$(idx_of 'Intranet GDC')
[ -z "$iG" ] && iG=-1; [ -z "$iS" ] && iS=-1; [ -z "$iI" ] && iI=-1
check '[ "$iS" -gt 0 ] && [ "$iG" -gt "$iS" ] && [ "$iI" -gt "$iG" ]' 'orden navbar: Sobre Nosotros < Gacetas < Intranet' "orden incorrecto (S=$iS G=$iG I=$iI)"

echo '--- 2. Vista publica vacia ---'
http "$E2E_BASE/gacetas"
check '[ "$HTTP_STATUS" = 200 ]' '/gacetas 200' "status $HTTP_STATUS"
check 'grep -q "Gacetas Oficiales</h1>" "$HTTP_BODY"' 'H1 Gacetas Oficiales' 'falta H1'
if [ "$baseCount" = 0 ]; then
  check 'grep -q "no hay gacetas publicadas" "$HTTP_BODY"' 'estado vacio presente' 'falta estado vacio'
else
  check '! grep -q "no hay gacetas publicadas" "$HTTP_BODY"' 'sin estado vacio (hay gacetas reales)' 'estado vacio incorrecto con gacetas presentes'
fi

echo '--- 3. Admin: formulario Nueva Gaceta ---'
http "$E2E_BASE/admin/gacetas"
check 'grep -q "Nueva Gaceta" "$HTTP_BODY"' 'formulario Nueva Gaceta' 'falta formulario'
check 'grep -q "name=\"file\"" "$HTTP_BODY"' 'input file' 'falta input file'
check 'grep -q "name=\"numero\"" "$HTTP_BODY"' 'input numero' 'falta input numero'
check 'grep -q "name=\"fecha\"" "$HTTP_BODY"' 'input fecha' 'falta input fecha'
token=$(require_token "$HTTP_BODY") || exit 1

echo '--- 4. Cargar gaceta PDF (#045) ---'
http "$E2E_BASE/admin/gacetas" \
  -F "_token=$token" \
  -F 'numero=045' \
  -F 'fecha=2026-02-01' \
  -F "file=@$tmp/gac-test1.pdf;type=application/pdf"
case "$HTTP_FINAL" in */admin/gacetas) redir=1 ;; *) redir=0 ;; esac
check '[ "$redir" = 1 ]' 'redirect a listado' "uri: $HTTP_FINAL"
row=$(sqlt "SELECT id, path, mime_type FROM gacetas WHERE numero='045';")
case "$row" in *gacetas/*) gp=1 ;; *) gp=0 ;; esac
check '[ "$gp" = 1 ]' "BD fila PDF ($row)" 'no se creo la fila PDF'
path1=$(printf '%s' "$row" | cut -f2)
check "[ -f \"$E2E_PUB/$path1\" ]" 'archivo PDF en disco' 'archivo PDF no existe'
check "[ ! -f \"$E2E_PUB/${path1%.*}.webp\" ]" 'PDF sin sidecar webp' 'PDF genero sidecar webp (mal)'

echo '--- 5. Cargar gaceta imagen (#050) ---'
http "$E2E_BASE/admin/gacetas"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas" \
  -F "_token=$token" \
  -F 'numero=050' \
  -F 'fecha=2026-03-01' \
  -F "file=@$tmp/gac-img1.png;type=image/png"
row=$(sqlt "SELECT path, mime_type FROM gacetas WHERE numero='050';")
p2=$(printf '%s' "$row" | cut -f1); m2=$(printf '%s' "$row" | cut -f2)
case "$p2" in gacetas/*) gp=1 ;; *) gp=0 ;; esac
case "$m2" in image/*) gm=1 ;; *) gm=0 ;; esac
check '[ "$gp" = 1 ] && [ "$gm" = 1 ]' "BD fila imagen ($row)" 'no se creo la fila imagen'
path2="$p2"
check "[ -f \"$E2E_PUB/$path2\" ]" 'archivo imagen en disco' 'archivo imagen no existe'
check "[ -f \"$E2E_PUB/${path2%.*}.webp\" ]" 'webp generado para imagen' 'falta sidecar webp de la imagen'

echo '--- 6. Vista publica: cards, orden y enlaces ---'
http "$E2E_BASE/gacetas"
check 'grep -qE "Gaceta Oficial N[^0-9]{1,6}050" "$HTTP_BODY"' 'titulo Gaceta Oficial N 050' 'falta titulo 050'
check 'grep -qE "Gaceta Oficial N[^0-9]{1,6}045" "$HTTP_BODY"' 'titulo Gaceta Oficial N 045' 'falta titulo 045'
i50=$(idx_of '050'); i45=$(idx_of '045')
[ -z "$i50" ] && i50=-1; [ -z "$i45" ] && i45=-1
check '[ "$i50" -gt 0 ] && [ "$i45" -gt "$i50" ]' 'orden: 050 (fecha mas reciente) antes que 045' "orden incorrecto ($i50 vs $i45)"
check 'grep -q "target=\"_blank\"" "$HTTP_BODY"' 'enlaces target _blank' 'falta target _blank'
check "grep -q \"storage/$path1\" \"\$HTTP_BODY\"" 'link al PDF' 'falta link al PDF'
check 'grep -q "fa-file-pdf" "$HTTP_BODY"' 'icono PDF para el PDF' 'falta icono PDF'
check "grep -q \"storage/$path2\" \"\$HTTP_BODY\"" 'miniatura de la imagen' 'falta miniatura imagen'
check 'grep -q "loading=\"lazy\"" "$HTTP_BODY"' 'miniaturas con lazy loading' 'falta lazy'

echo '--- 7. Editar: numero + fecha + reemplazar archivo ---'
id1=$(sql "SELECT id FROM gacetas WHERE numero='045';")
http "$E2E_BASE/admin/gacetas/$id1/editar"
check 'grep -q "value=\"045\"" "$HTTP_BODY"' 'edit precarga numero' 'no precarga numero'
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas/$id1" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'numero=046' \
  --data-urlencode 'fecha=2026-02-15'
check '[[ "$HTTP_FINAL" == */admin/gacetas ]]' 'update sin archivo -> redirect' "uri: $HTTP_FINAL"
numero=$(sql "SELECT numero FROM gacetas WHERE id=$id1;")
fecha=$(sql "SELECT fecha FROM gacetas WHERE id=$id1;")
row="$numero $fecha"
check '[ "$numero" = 046 ] && [ "$fecha" = 2026-02-15 ]' "BD actualizada ($row)" "BD no cambio: $row"
check "[ -f \"$E2E_PUB/$path1\" ]" 'archivo conservado al editar solo campos' 'archivo borrado (mal)'

http "$E2E_BASE/admin/gacetas/$id1/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas/$id1" \
  -F "_token=$token" \
  -F '_method=PUT' \
  -F 'numero=046' \
  -F 'fecha=2026-02-15' \
  -F "file=@$tmp/gac-img2.png;type=image/png"
path3=$(sql "SELECT path FROM gacetas WHERE id=$id1;")
check '[ "$path3" != "$path1" ]' "archivo reemplazado ($path3)" 'path no cambio'
check "[ ! -f \"$E2E_PUB/$path1\" ]" 'archivo anterior borrado del disco' 'archivo anterior quedo en disco'
check "[ -f \"$E2E_PUB/$path3\" ]" 'nuevo archivo en disco' 'nuevo archivo no existe'

echo '--- 8. Validaciones ---'
http "$E2E_BASE/admin/gacetas"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas" \
  -F "_token=$token" -F 'numero=099' -F 'fecha=2026-04-01' \
  -F "file=@$tmp/gac-fake.txt;type=text/plain"
cnt=$(sql "SELECT COUNT(*) FROM gacetas WHERE numero='099';")
check '[ "$cnt" = 0 ]' 'archivo .txt rechazado' "se creo fila: $cnt"
http "$E2E_BASE/admin/gacetas"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas" \
  -F "_token=$token" -F 'numero=abc' -F 'fecha=2026-04-01' \
  -F "file=@$tmp/gac-test2.pdf;type=application/pdf"
cnt=$(sql "SELECT COUNT(*) FROM gacetas WHERE numero='abc';")
check '[ "$cnt" = 0 ]' 'numero no numerico rechazado' "se creo fila: $cnt"
http "$E2E_BASE/admin/gacetas"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/gacetas" \
  -F "_token=$token" -F 'numero=1234567' -F 'fecha=2026-04-01' \
  -F "file=@$tmp/gac-test2.pdf;type=application/pdf"
cnt=$(sql "SELECT COUNT(*) FROM gacetas WHERE numero='1234567';")
check '[ "$cnt" = 0 ]' 'numero de 7 digitos rechazado' "se creo fila: $cnt"

echo '--- 9. Permisos ---'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/gacetas"
check '[ "$HTTP_STATUS" = 403 ]' 'editor recibe 403' "editor obtuvo $HTTP_STATUS (esperado 403)"
login 'admin2@gdc.gob.ve'
http "$E2E_BASE/admin/gacetas"
check 'grep -q "Nueva Gaceta" "$HTTP_BODY"' 'rol admin puede gestionar gacetas' 'rol admin no puede gestionar'
check 'grep -q "Gacetas</a>" "$HTTP_BODY"' 'sidebar admin con Gacetas' 'falta Gacetas en sidebar'
pc=$(sql 'SELECT COUNT(*) FROM permissions;')
check '[ "$pc" = 40 ]' "permisos totales 40 ($pc)" "permisos: $pc"
rc=$(sql 'SELECT role, COUNT(*) FROM role_permission GROUP BY role;')
case "$rc" in *admin*22*|*22*admin*) ra=1 ;; *) ra=0 ;; esac
check '[ "$ra" = 1 ]' "reparto admin 22 ($rc)" "reparto admin inesperado: $rc"

echo '--- 10. Eliminar ---'
login 'admin@gdc.gob.ve'
http "$E2E_BASE/admin/gacetas"
token=$(require_token "$HTTP_BODY") || exit 1
id050=$(sql "SELECT id FROM gacetas WHERE numero='050';")
for id in "$id1" "$id050"; do
  [ -z "$id" ] && continue
  http "$E2E_BASE/admin/gacetas/$id" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
done
cnt=$(sql 'SELECT COUNT(*) FROM gacetas;')
testCnt=$(sql "SELECT COUNT(*) FROM gacetas WHERE original_name IN ('gac-test1.pdf','gac-test2.pdf','gac-img1.png','gac-img2.png');")
check '[ "$testCnt" = 0 ] && [ "$cnt" = "$baseCount" ]' "gacetas de la suite eliminadas; preexistentes intactas (total $cnt)" "restos: suite=$testCnt total=$cnt (esperado $baseCount)"
for p in "$path2" "$path3"; do
  check "[ ! -f \"$E2E_PUB/$p\" ]" "archivo $p borrado del disco" "archivo $p quedo en disco"
  check "[ ! -f \"$E2E_PUB/${p%.*}.webp\" ]" "sidecar webp de $p borrado" "sidecar webp de $p quedo en disco"
done

echo '--- 11. Smoke ---'
for u in / /noticias /gacetas /entes /contacto; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u -> $HTTP_STATUS"
done

summary 'GACETAS'
