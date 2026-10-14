#!/usr/bin/env bash
# test-noticias.sh — port de test-noticias.ps1 (noticias: aprobacion, programacion, multimedia)
E2E_SUITE=noticias
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-noticias"
mkdir -p "$tmp"

# --- Archivos de prueba ---
PNG='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
echo "$PNG" | base64 -d > "$tmp/portada-e2e.png"
echo "$PNG" | base64 -d > "$tmp/gal1-e2e.png"
echo "$PNG" | base64 -d > "$tmp/gal2-e2e.png"
printf '\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41' > "$tmp/galvid-e2e.mp4"

# --- Limpieza previa de corridas anteriores ---
while IFS= read -r p; do
  [ -z "$p" ] && continue
  [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  wp="${p%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done < <(sql "SELECT path FROM media WHERE original_name LIKE '%-e2e.png';")
sql "DELETE FROM posts WHERE title LIKE 'E2E Aprob%';" >/dev/null
sql "DELETE FROM posts WHERE title LIKE 'E2E Multimedia%';" >/dev/null
sql "DELETE FROM videos WHERE title LIKE 'E2E Multimedia%';" >/dev/null
sql "DELETE FROM media WHERE original_name LIKE '%-e2e.png';" >/dev/null
echo 'pre-limpieza: artefactos de corridas anteriores removidos'

echo '--- 1. Login admin ---'
login 'admin@gdc.gob.ve'
superJar="$COOKIE_JAR"
ok 'LOGIN admin'

echo '--- 2. Crear borradores y publicada ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
check 'grep -q "name=\"published_at\"" "$HTTP_BODY"' 'form crear: campo programacion presente' 'falta campo programacion'
check 'grep -q "name=\"status\"" "$HTTP_BODY"' 'form crear: select estado presente' 'falta select status'

for t in 'E2E Aprob Borrador A' 'E2E Aprob Borrador B' 'E2E Aprob Borrador C'; do
  http "$E2E_BASE/admin/noticias" \
    --data-urlencode "_token=$token" \
    --data-urlencode "title=$t" \
    --data-urlencode 'category_id=1' \
    --data-urlencode 'excerpt=Extracto E2E Aprob' \
    --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
    --data-urlencode 'status=draft'
  check '[[ "$HTTP_FINAL" == */admin/noticias ]]' "creado borrador: $t" "fallo crear: $t ($HTTP_FINAL)"
done
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E Aprob Publicada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E Aprob' \
  --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
  --data-urlencode 'status=published'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'creada publicada' "fallo crear publicada ($HTTP_FINAL)"

idA=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Borrador A';")
idB=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Borrador B';")
idC=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Borrador C';")
idP=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Publicada';")
check '[ -n "$idA" ] && [ -n "$idB" ] && [ -n "$idC" ] && [ -n "$idP" ]' "ids A=$idA B=$idB C=$idC P=$idP" 'faltan filas creadas'
pubAt=$(sql "SELECT (published_at IS NOT NULL)::int FROM posts WHERE id=$idP;")
check '[ "$pubAt" = 1 ]' 'publicada con published_at' 'published_at vacio en publicada inmediata'

echo '--- 3. Index admin: badges y UI de aprobacion ---'
http "$E2E_BASE/admin/noticias"
check 'grep -q "Pendiente de aprobaci" "$HTTP_BODY"' 'badge Pendiente visible' 'falta badge Pendiente'
check 'grep -q "Publicado" "$HTTP_BODY"' 'badge Publicado visible' 'falta badge Publicado'
check 'grep -q "Publicar seleccionadas" "$HTTP_BODY"' 'boton masivo presente' 'falta boton masivo'
check 'grep -qF "name=\"posts[]\"" "$HTTP_BODY"' 'checkboxes presentes' 'faltan checkboxes'
check 'grep -q "selectAll" "$HTTP_BODY"' 'seleccionar todo presente' 'falta seleccionar todo'
check '! grep -q "alert alert-warning" "$HTTP_BODY"' 'sin alert warning en index limpio' 'alert warning inesperado'

echo '--- 4. Aprobar individual ---'
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idA/aprobar" --data-urlencode "_token=$token"
check 'grep -qF "Noticia aprobada: E2E Aprob Borrador A" "$HTTP_BODY"' 'flash de aprobacion individual' 'sin flash de aprobacion'
stA=$(sql "SELECT status FROM posts WHERE id=$idA;")
naA=$(sql "SELECT (published_at IS NOT NULL)::int FROM posts WHERE id=$idA;")
check '[ "$stA" = published ] && [ "$naA" = 1 ]' "borrador A publicado ($stA, published_at=$naA)" "estado A: $stA published_at=$naA"
aAudit=$(sql "SELECT COUNT(*) FROM activity_logs WHERE action='news.approved' AND entity_id=$idA;")
check '[ "$aAudit" = 1 ]' "audit news.approved en A ($aAudit)" "audit A: $aAudit"

echo '--- 5. Aprobacion masiva (borrador B + publicada P) ---'
http "$E2E_BASE/admin/noticias"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/publicar" \
  --data-urlencode "_token=$token" \
  --data-urlencode "posts[]=$idB" \
  --data-urlencode "posts[]=$idP"
check 'grep -qF "1 noticia(s) publicada(s)" "$HTTP_BODY"' 'flash conteo publicadas' 'sin flash de conteo'
check 'grep -qF "1 omitida" "$HTTP_BODY"' 'flash conteo omitidas' 'sin flash de omitidas'
row=$(sql "SELECT status FROM posts WHERE id=$idB;")
check '[ "$row" = published ]' "borrador B publicado ($row)" "estado B: $row"
row=$(sql "SELECT status FROM posts WHERE id=$idP;")
check '[ "$row" = published ]' "publicada P intacta ($row)" "estado P: $row"
bAudit=$(sql "SELECT COUNT(*) FROM activity_logs WHERE action='news.approved' AND entity_id IN ($idA,$idB);")
check '[ "$bAudit" = 2 ]' "audit masivo solo A+B ($bAudit)" "audit: $bAudit"

echo '--- 6. Editor: sin UI de aprobacion, 403, formularios disabled ---'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/noticias"
check '! grep -qF "name=\"posts[]\"" "$HTTP_BODY"' 'editor: sin checkboxes' 'editor ve checkboxes'
check '! grep -q "Publicar seleccionadas" "$HTTP_BODY"' 'editor: sin boton masivo' 'editor ve boton masivo'
check '! grep -qF ">Aprobar</button>" "$HTTP_BODY"' 'editor: sin boton Aprobar' 'editor ve boton Aprobar'
check 'grep -q "Pendiente de aprobaci" "$HTTP_BODY"' 'editor: badge Pendiente visible (informativo)' 'editor no ve badge'
tokenE=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idC/aprobar" --data-urlencode "_token=$tokenE"
check '[ "$HTTP_STATUS" = 403 ]' 'editor recibe 403 en aprobar' "aprobar status $HTTP_STATUS"
http "$E2E_BASE/admin/noticias/publicar" --data-urlencode "_token=$tokenE" --data-urlencode "posts[]=$idC"
check '[ "$HTTP_STATUS" = 403 ]' 'editor recibe 403 en masivo' "masivo status $HTTP_STATUS"
http "$E2E_BASE/admin/noticias/$idC/editar"
check 'grep -zPq "name=\"status\"[^>]*disabled" "$HTTP_BODY"' 'editor: select estado disabled' 'select estado NO disabled para editor'
check 'grep -zPq "name=\"published_at\"[^>]*disabled" "$HTTP_BODY"' 'editor: campo programacion disabled' 'datetime NO disabled para editor'
tokenE=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idC" \
  --data-urlencode "_token=$tokenE" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Aprob Borrador C' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E Aprob' \
  --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
  --data-urlencode 'status=published'
check 'grep -q "queda pendiente de aprobaci" "$HTTP_BODY"' 'flash warning al editor por intentar publicar' 'sin flash warning para editor'
cStatus=$(sql "SELECT status FROM posts WHERE id=$idC;")
check '[ "$cStatus" = draft ]' "C sigue en borrador ($cStatus)" "C cambio de estado: $cStatus"

COOKIE_JAR="$superJar"
echo '--- 7. Programacion de publicacion ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
future=$(date -d '+2 days' +%Y-%m-%dT%H:%M)
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E Aprob Programada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E Aprob' \
  --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode "published_at=$future"
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'programada creada' "fallo crear programada ($HTTP_FINAL)"
idS=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Programada';")
check '[ -n "$idS" ]' 'fila programada existe' 'no se creo la programada'
slugS=$(sql "SELECT slug FROM posts WHERE id=$idS;")
st=$(sql "SELECT status FROM posts WHERE id=$idS;")
paFut=$(sql "SELECT (published_at > now())::int FROM posts WHERE id=$idS;")
check '[ "$st" = published ]' "estado published ($st)" "estado: $st"
check '[ "$paFut" = 1 ]' 'published_at futuro' "published_at no futuro: $paFut"

http "$E2E_BASE/noticias/$slugS"
check '[ "$HTTP_STATUS" = 404 ]' 'publico 404 para programada' "show status $HTTP_STATUS"
http "$E2E_BASE/noticias"
check '! grep -q "E2E Aprob Programada" "$HTTP_BODY"' 'listado publico oculta programada' 'listado publico la muestra'
http "$E2E_BASE/admin/noticias"
check 'grep -q "Programada" "$HTTP_BODY"' 'badge Programada en admin' 'falta badge Programada'

http "$E2E_BASE/admin/noticias/$idS/editar"
check 'grep -q "name=\"published_at\"" "$HTTP_BODY"' 'edit admin: campo programacion presente' 'falta campo programacion en edit'
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idS" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Aprob Programada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E Aprob' \
  --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode 'published_at='
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'update limpiar fecha exitoso' "fallo update programada ($HTTP_FINAL)"
paNow=$(sql "SELECT (published_at IS NULL OR published_at <= now())::int FROM posts WHERE id=$idS;")
check '[ "$paNow" = 1 ]' 'fecha vaciada = publicada ya' "sigue futura: $paNow"
http "$E2E_BASE/noticias/$slugS"
check '[ "$HTTP_STATUS" = 200 ]' 'publico 200 tras vaciar fecha' "status $HTTP_STATUS"

echo '--- 8. Editor editando programada no la adelanta ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
future2=$(date -d '+3 days' +%Y-%m-%dT%H:%M)
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E Aprob Futura 2' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E Aprob' \
  --data-urlencode 'content=<p>Contenido E2E Aprob</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode "published_at=$future2"
idF=$(sql "SELECT id FROM posts WHERE title='E2E Aprob Futura 2';")
paBefore=$(sql "SELECT published_at FROM posts WHERE id=$idF;")
COOKIE_JAR="$E2E_TMP/e2e-${E2E_SUITE}-editor_gdc_gob_ve.jar"
http "$E2E_BASE/admin/noticias/$idF/editar"
tokenE=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idF" \
  --data-urlencode "_token=$tokenE" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Aprob Futura 2 Editada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto editado' \
  --data-urlencode 'content=<p>Editado</p>'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'editor pudo editar contenido' "editor no pudo editar ($HTTP_FINAL)"
paAfter=$(sql "SELECT published_at FROM posts WHERE id=$idF;")
titleAfter=$(sql "SELECT title FROM posts WHERE id=$idF;")
check '[ "$paAfter" = "$paBefore" ]' "fecha conservada por editor ($paAfter)" "fecha cambio: $paBefore -> $paAfter"
check '[ "$titleAfter" = "E2E Aprob Futura 2 Editada" ]' 'edicion de contenido aplicada' "edicion no aplico: $titleAfter"

COOKIE_JAR="$superJar"
echo '--- 9. Multimedia: galeria, videos y carrusel (D15) ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias" \
  --form-string "_token=$token" \
  --form-string 'title=E2E Multimedia' \
  --form-string 'category_id=1' \
  --form-string 'excerpt=Extracto multimedia' \
  --form-string 'content=<p>Contenido multimedia</p>' \
  --form-string 'status=published' \
  --form-string 'new_video_urls=https://youtu.be/dQw4w9WgXcQ' \
  -F "featured_image_file=@$tmp/portada-e2e.png;type=image/png" \
  -F "gallery_images[]=@$tmp/gal1-e2e.png;type=image/png" \
  -F "gallery_images[]=@$tmp/gal2-e2e.png;type=image/png" \
  -F "new_video_files[]=@$tmp/galvid-e2e.mp4;type=video/mp4"
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'noticia multimedia creada' "fallo crear noticia multimedia ($HTTP_FINAL)"
idM=$(sql "SELECT id FROM posts WHERE title='E2E Multimedia';")
check '[ -n "$idM" ]' "fila multimedia existe ($idM)" 'no se creo la fila multimedia'

pmCnt=$(sql "SELECT COUNT(*) FROM post_media WHERE post_id=$idM;")
check '[ "$pmCnt" = 4 ]' "post_media: 2 imagenes + 2 videos ($pmCnt)" "post_media: $pmCnt"
dated=$(sql "SELECT COUNT(*) FROM post_media WHERE post_id=$idM AND type='image' AND path LIKE 'uploads/' || to_char(now(),'YYYY') || '/' || to_char(now(),'MM') || '/%';")
check '[ "$dated" = 2 ]' "imagenes en uploads/YYYY/MM ($dated)" "rutas sin ano/mes: $dated"
vidCnt=$(sql "SELECT COUNT(*) FROM videos WHERE title LIKE 'E2E Multimedia%';")
check '[ "$vidCnt" = 2 ]' "videos creados en modulo Videos ($vidCnt)" "videos en modulo: $vidCnt"
featOff=$(sql "SELECT COUNT(*) FROM videos WHERE title LIKE 'E2E Multimedia%' AND is_featured = false;")
check '[ "$featOff" = 2 ]' 'videos sin is_featured (no invaden portada)' 'videos quedaron destacados'

slugM=$(sql "SELECT slug FROM posts WHERE id=$idM;")
fiName=$(sql "SELECT substring(featured_image from '[^/]+$') FROM posts WHERE id=$idM;")
http "$E2E_BASE/noticias/$slugM"
check 'grep -q "id=\"newsGallery\"" "$HTTP_BODY"' 'detalle con carrusel' 'sin carrusel en detalle'
items=$(grep -o 'carousel-item' "$HTTP_BODY" | wc -l | tr -d ' ')
check '[ "$items" = 5 ]' "5 items en carrusel (portada+2img+2vid) ($items)" "items: $items"
check 'grep -qF "youtube.com/embed/dQw4w9WgXcQ" "$HTTP_BODY"' 'video youtube embebido' 'sin embed youtube'
check 'grep -qF "<video" "$HTTP_BODY"' 'video archivo embebido' 'sin video archivo'
check "grep -zPq \"(?s)carousel-item active[^>]*>.*?\$(re_escape \"\$fiName\")\" \"\$HTTP_BODY\"" 'portada primera en el carrusel' "portada no es la primera ($fiName)"

http "$E2E_BASE/admin/noticias/$idM/editar"
check 'grep -q "gallery-items" "$HTTP_BODY"' 'edit muestra items de galeria' 'edit sin galeria'
check 'grep -q "direction=up" "$HTTP_BODY"' 'botones de orden presentes' 'faltan botones de orden'
token=$(require_token "$HTTP_BODY") || exit 1
firstId=$(sql "SELECT id FROM post_media WHERE post_id=$idM ORDER BY position, id LIMIT 1;")
secondId=$(sql "SELECT id FROM post_media WHERE post_id=$idM ORDER BY position, id LIMIT 1 OFFSET 1;")
http "$E2E_BASE/admin/noticias/$idM/media/$firstId/move?direction=down" --data-urlencode "_token=$token"
p1=$(sql "SELECT position FROM post_media WHERE id=$firstId;")
p2=$(sql "SELECT position FROM post_media WHERE id=$secondId;")
check '[ "$p1" = 1 ] && [ "$p2" = 0 ]' "reorden aplicado ($p1/$p2)" "orden no cambio ($p1/$p2)"

imgId=$(sql "SELECT id FROM post_media WHERE post_id=$idM AND type='image' ORDER BY position LIMIT 1;")
http "$E2E_BASE/admin/noticias/$idM" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Multimedia' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto multimedia' \
  --data-urlencode 'content=<p>Contenido multimedia</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode "remove_media[]=$imgId"
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'update con remove_media exitoso' "fallo update remove_media ($HTTP_FINAL)"
afterRm=$(sql "SELECT COUNT(*) FROM post_media WHERE post_id=$idM;")
check '[ "$afterRm" = 3 ]' "imagen eliminada de la galeria ($afterRm)" "post_media tras remove: $afterRm"

http "$E2E_BASE/admin/noticias/$idM/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$idM" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Multimedia' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto multimedia' \
  --data-urlencode 'content=<p>Contenido multimedia</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode $'new_video_urls=https://youtu.be/dQw4w9WgXcQ\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\nhttps://www.youtube.com/embed/dQw4w9WgXcQ'
check '[[ "$HTTP_FINAL" == */editar ]]' 'tope de videos rechaza y vuelve al form' "no rechazo: $HTTP_FINAL"
check 'grep -qF "ximo 3 videos" "$HTTP_BODY"' 'mensaje de tope de videos visible' 'sin mensaje de tope'
vidAfter=$(sql "SELECT COUNT(*) FROM videos WHERE title LIKE 'E2E Multimedia%';")
check '[ "$vidAfter" = 2 ]' "no se crearon videos de mas ($vidAfter)" "videos: $vidAfter"

echo '--- 10. Cleanup ---'
for id in "$idA" "$idB" "$idC" "$idP" "$idS" "$idF"; do
  [ -z "$id" ] && continue
  http "$E2E_BASE/admin/noticias/$id/editar"
  if [ "$HTTP_STATUS" = 200 ]; then
    t=$(require_token "$HTTP_BODY") || continue
    http "$E2E_BASE/admin/noticias/$id" --data-urlencode "_token=$t" --data-urlencode '_method=DELETE'
  fi
done
sql "DELETE FROM posts WHERE title LIKE 'E2E Aprob%';" >/dev/null
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title LIKE 'E2E Aprob%';")
check '[ "$cnt" = 0 ]' 'posts de prueba purgados de la BD' "restos: $cnt"
orphan=$(sql "SELECT COUNT(*) FROM activity_logs WHERE action='news.approved' AND entity_id IN ($idA,$idB);")
check '[ "$orphan" = 2 ]' "auditoria de aprobaciones registrada ($orphan)" "auditoria: $orphan"

imgPaths=$(sql "SELECT pm.path FROM post_media pm JOIN posts p ON p.id=pm.post_id WHERE p.title LIKE 'E2E Multimedia%' AND pm.path IS NOT NULL;")
vidPaths=$(sql "SELECT path FROM videos WHERE title LIKE 'E2E Multimedia%' AND path IS NOT NULL;")
libPaths=$(sql "SELECT path FROM media WHERE original_name LIKE '%-e2e.png';")
featPath=$(sql "SELECT featured_image FROM posts WHERE title LIKE 'E2E Multimedia%';")
sql "DELETE FROM posts WHERE title LIKE 'E2E Multimedia%';" >/dev/null
sql "DELETE FROM videos WHERE title LIKE 'E2E Multimedia%';" >/dev/null
sql "DELETE FROM media WHERE original_name LIKE '%-e2e.png';" >/dev/null
while IFS= read -r p; do
  [ -z "$p" ] && continue
  [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  wp="${p%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done <<< "$imgPaths
$vidPaths
$libPaths
$featPath"
rm -f "$tmp/portada-e2e.png" "$tmp/gal1-e2e.png" "$tmp/gal2-e2e.png" "$tmp/galvid-e2e.mp4"
mmLeft=$(sql "SELECT COUNT(*) FROM post_media pm JOIN posts p ON p.id=pm.post_id WHERE p.title LIKE 'E2E Multimedia%';")
check '[ "$mmLeft" = 0 ]' 'post_media multimedia purgado' "restos: $mmLeft"
vidLeft=$(sql "SELECT COUNT(*) FROM videos WHERE title LIKE 'E2E Multimedia%';")
check '[ "$vidLeft" = 0 ]' 'videos de prueba purgados' "restos videos: $vidLeft"

echo '--- 11. Smoke ---'
http "$E2E_BASE/noticias"
check '[ "$HTTP_STATUS" = 200 ]' '/noticias 200' "/noticias -> $HTTP_STATUS"
http "$E2E_BASE/admin/noticias"
check '[ "$HTTP_STATUS" = 200 ]' '/admin/noticias 200' "admin -> $HTTP_STATUS"

summary 'NOTICIAS/APROBACION'
