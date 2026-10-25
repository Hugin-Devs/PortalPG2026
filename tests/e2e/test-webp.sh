#!/usr/bin/env bash
# test-webp.sh — port de test-webp.ps1 (sidecars webp: generate, upload, fallback, borrar)
E2E_SUITE=webp
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-webp"
mkdir -p "$tmp"

echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/webp-img.png"
cp "$tmp/webp-img.png" "$tmp/webp-news.png"
echo 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' | base64 -d > "$tmp/webp-img.gif"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC' | base64 -d > "$tmp/webp-corrupt.png"

# --- Limpieza previa de corridas anteriores ---
sql "DELETE FROM posts WHERE title='E2E WebP Destacada';" >/dev/null
while IFS= read -r p; do
  [ -z "$p" ] && continue
  [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  wp="${p%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done < <(sql "SELECT path FROM media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');")
sql "DELETE FROM media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');" >/dev/null
echo 'pre-limpieza: artefactos de corridas anteriores removidos'

echo '--- 1. Comando webp:generate (backfill) ---'
out1=$(php artisan webp:generate 2>&1); ec1=$?
out2=$(php artisan webp:generate 2>&1); ec2=$?
check '[ "$ec1" = 0 ]' 'webp:generate exit 0' "exit $ec1 (salida: $out1)"
check "grep -qE 'WebP: [0-9]+ convertidos'  <<< \"\$out1\"" "resumen corrida 1" "sin resumen de corrida 1 ($out1)"
check '[ "$ec2" = 0 ]' 'webp:generate 2a corrida exit 0' "exit $ec2"
check "grep -qE 'WebP: 0 convertidos'  <<< \"\$out2\"" "2a corrida 0 convertidos" "2a corrida reconvirtio (no idempotente): $out2"
check "grep -qE '[0-9]+ ya existentes'  <<< \"\$out2\"" '2a corrida reporta ya existentes' "sin contador de existentes: $out2"

echo '--- 2. Login ---'
login 'admin@gdc.gob.ve'
grep -q 'Admin GDC' "$HTTP_BODY" || { echo 'Login fallido' >&2; exit 1; }
ok 'LOGIN superadmin'

echo '--- 3. Upload a biblioteca genera sidecar .webp ---'
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios" --form-string "_token=$token" -F "files[]=@$tmp/webp-img.png;type=image/png"
case "$HTTP_STATUS" in 200|302) up=1 ;; *) up=0 ;; esac
check '[ "$up" = 1 ]' "POST upload -> $HTTP_STATUS" "upload fallo: $HTTP_STATUS"
pathA=$(sql "SELECT path FROM media WHERE original_name='webp-img.png' ORDER BY id DESC LIMIT 1;")
case "$pathA" in uploads/*) pa=1 ;; *) pa=0 ;; esac
check '[ "$pa" = 1 ]' "path media=$pathA" 'upload no guardo path'
check "[ -f \"$E2E_PUB/$pathA\" ]" 'original en disco' 'original no existe'
check "[ -f \"${E2E_PUB}/${pathA%.*}.webp\" ]" 'sidecar .webp creado en upload' 'NO se creo sidecar webp'
webpRel="${pathA%.*}.webp"
http "$E2E_BASE/storage/$webpRel"
check '[ "$HTTP_STATUS" = 200 ]' 'GET /storage/*.webp -> 200' "GET webp $HTTP_STATUS"
# GET + %{content_type} en lugar de HEAD (php -S retiene el handle tras HEAD
# y el delete posterior queda en delete-pending en Windows — ver test-settings).
ct=$(curl -s -o "$E2E_TMP/ct-check.bin" -w '%{content_type}' "$E2E_BASE/storage/$webpRel")
case "$ct" in *image/webp*|*[Ii]mage*[Pp]webp*|*[Ii]mage*webp*) ctw=1 ;; *) ctw=0 ;; esac
check '[ "$ctw" = 1 ]' "content-type image/webp ($ct)" "content-type no webp: $ct"
http "$E2E_BASE/storage/$pathA"
check '[ "$HTTP_STATUS" = 200 ]' 'GET original -> 200 (fallback disponible)' "GET original $HTTP_STATUS"

echo '--- 4. GIF no genera sidecar (solo jpg/png) ---'
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios" --form-string "_token=$token" -F "files[]=@$tmp/webp-img.gif;type=image/gif"
pathC=$(sql "SELECT path FROM media WHERE original_name='webp-img.gif' ORDER BY id DESC LIMIT 1;")
case "$pathC" in uploads/*) pc=1 ;; *) pc=0 ;; esac
check '[ "$pc" = 1 ]' "path gif=$pathC" 'gif no subio'
check "[ -f \"$E2E_PUB/$pathC\" ]" 'gif en disco' 'gif no existe en disco'
check "[ ! -f \"${E2E_PUB}/${pathC%.*}.webp\" ]" 'gif NO genera sidecar webp' 'gif genero sidecar (mal)'

echo '--- 4b. PNG corrupto: sin sidecar y webp:generate no falla ---'
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios" --form-string "_token=$token" -F "files[]=@$tmp/webp-corrupt.png;type=image/png"
pathD=$(sql "SELECT path FROM media WHERE original_name='webp-corrupt.png' ORDER BY id DESC LIMIT 1;")
case "$pathD" in uploads/*) pd=1 ;; *) pd=0 ;; esac
check '[ "$pd" = 1 ]' "path png corrupto=$pathD" 'png corrupto no subio'
check "[ -f \"$E2E_PUB/$pathD\" ]" 'png corrupto en disco' 'png corrupto no existe'
check "[ ! -f \"${E2E_PUB}/${pathD%.*}.webp\" ]" 'png corrupto NO genera sidecar' 'corrupto genero sidecar (mal)'
out3=$(php artisan webp:generate 2>&1); ec3=$?
check '[ "$ec3" = 0 ]' 'webp:generate exit 0 con png corrupto presente' "exit $ec3 (salida: $out3)"
check "grep -qE ', [1-9][0-9]* ilegibles, 0 fallidos'  <<< \"\$out3\"" 'contador ilegibles' "sin ilegibles o con fallidos: $out3"

echo '--- 5. Noticia destacada renderiza <picture> con fallback ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias" \
  --form-string "_token=$token" \
  --form-string 'title=E2E WebP Destacada' \
  --form-string 'category_id=1' \
  --form-string 'excerpt=Extracto WebP E2E' \
  --form-string 'content=<p>Contenido WebP E2E</p>' \
  --form-string 'status=published' \
  -F "featured_image_file=@$tmp/webp-news.png;type=image/png"
case "$HTTP_STATUS" in 200|302) np=1 ;; *) np=0 ;; esac
check '[ "$np" = 1 ]' "POST noticia -> $HTTP_STATUS" "noticia fallo: $HTTP_STATUS"
fi=$(sql "SELECT featured_image FROM posts WHERE title='E2E WebP Destacada';")
case "$fi" in uploads/*) pf=1 ;; *) pf=0 ;; esac
check '[ "$pf" = 1 ]' "featured_image=$fi" 'noticia sin imagen'
check "[ -f \"${E2E_PUB}/${fi%.*}.webp\" ]" 'sidecar de la imagen destacada' 'falta sidecar de destacada'
http "$E2E_BASE/noticias"
check '[ "$HTTP_STATUS" = 200 ]' '/noticias 200' "status $HTTP_STATUS"
check 'grep -q "<picture>" "$HTTP_BODY"' '/noticias usa <picture>' 'sin <picture> en /noticias'
fiWebp="${fi%.*}.webp"
check "grep -zPq \"<source srcset=\\\"[^\\\"]*\$(re_escape \"\$fiWebp\")\\\" type=\\\"image/webp\\\">\" \"\$HTTP_BODY\"" 'source webp apunta al sidecar' 'falta source webp correcto'
check "grep -zPq \"<img src=\\\"[^\\\"]*\$(re_escape \"\$fi\")\\\"\" \"\$HTTP_BODY\"" 'img conserva src original (fallback)' 'img no conserva el original'
check "grep -zPq 'card-img-top[^>]*loading=\"lazy\"' \"\$HTTP_BODY\"" 'card con lazy loading' 'card sin lazy'

echo '--- 6. Destruir media borra original + sidecar ---'
idA=$(sql "SELECT id FROM media WHERE path='$pathA';")
check "[[ \"$idA\" =~ ^[0-9]+$ ]]" "media id=$idA" 'id de media invalido'
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios/$idA" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
check "[ ! -f \"$E2E_PUB/$pathA\" ]" 'original borrado del disco' 'original quedo en disco'
check "[ ! -f \"${E2E_PUB}/${pathA%.*}.webp\" ]" 'sidecar borrado del disco' 'sidecar quedo en disco'
c=$(sql "SELECT COUNT(*) FROM media WHERE path='$pathA';")
check '[ "$c" = 0 ]' 'fila media eliminada' "fila aun existe: $c"

echo '--- 7. CSS construido incluye picture{display:contents} ---'
cssFile=$(php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true); echo $m["resources/css/app.scss"]["file"]??"";')
check '[ -n "$cssFile" ]' 'manifest registra resources/css/app.scss' 'clave ausente en manifest'
if [ -n "$cssFile" ]; then
  check "grep -qF 'picture{display:contents}' \"\$E2E_ROOT/public/build/\$cssFile\"" 'build CSS contiene picture{display:contents}' 'regla ausente (falta npm run build)'
fi

echo '--- 8. Smoke publico con <picture> ---'
for u in / /noticias /entes /gacetas; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u -> $HTTP_STATUS"
done

echo '--- 9. Cleanup ---'
pidW=$(sql "SELECT id FROM posts WHERE title='E2E WebP Destacada';")
if [[ "$pidW" =~ ^[0-9]+$ ]]; then
  http "$E2E_BASE/admin/noticias/$pidW" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
  sql "DELETE FROM posts WHERE title='E2E WebP Destacada';" >/dev/null
  cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title='E2E WebP Destacada';")
  check '[ "$cnt" = 0 ]' 'post de prueba purgado' "restos: $cnt"
fi
while IFS= read -r p; do
  [ -z "$p" ] && continue
  if [ -f "$E2E_PUB/$p" ]; then rm -f "$E2E_PUB/$p"; ok "borrado disco: $p"; fi
  wp="${p%.*}.webp"
  if [ -f "$E2E_PUB/$wp" ]; then rm -f "$E2E_PUB/$wp"; ok "borrado disco sidecar: ${wp##*/}"; fi
done < <(sql "SELECT path FROM media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');")
sql "DELETE FROM media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');" >/dev/null
c=$(sql "SELECT COUNT(*) FROM media WHERE original_name IN ('webp-img.png','webp-news.png','webp-img.gif','webp-corrupt.png');")
check '[ "$c" = 0 ]' 'media de prueba limpia' "restos media: $c"
rm -f "$tmp/webp-img.png" "$tmp/webp-news.png" "$tmp/webp-img.gif" "$tmp/webp-corrupt.png"

summary 'WEBP'
