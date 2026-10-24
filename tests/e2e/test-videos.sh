#!/usr/bin/env bash
# test-videos.sh — port de test-videos.ps1 (modulo videos: permisos, sidebar, alta YT/archivo, portada)
E2E_SUITE=videos
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-videos"
mkdir -p "$tmp"
count_videos() { sql 'SELECT COUNT(*) FROM videos;'; }

# --- Fixture: MP4 minimo valido (ftyp isom) ---
mp4="$tmp/vidtest.mp4"
{ printf '\x00\x00\x00\x18'; printf 'ftypisom'; printf '\x00\x00\x02\x00'; printf 'isomiso2mp41'; } > "$mp4"

# --- Pre-limpieza quirurgica: solo artefactos de corridas anteriores de esta suite ---
while IFS=$'\t' read -r vid p; do
  [ -z "$vid" ] && continue
  [ -n "$p" ] && [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  sql "DELETE FROM videos WHERE id=$vid;" >/dev/null
done < <(sqlt "SELECT id, path FROM videos WHERE title LIKE 'VidTest%';")
echo 'pre-limpieza: artefactos VidTest* removidos'

echo '--- 1. Permisos videos.* ---'
( cd "$E2E_ROOT" && php artisan db:seed --class=PermissionSeeder --force >/dev/null 2>&1 )
pc=$(sql 'SELECT COUNT(*) FROM permissions;')
check '[ "$pc" = 41 ]' "permisos totales 41 tras videos (got $pc)" "permisos != 41 (got $pc)"
rc=$(sql 'SELECT COUNT(*) FROM role_permission;')
check '[ "$rc" = 68 ]' "68 asignaciones 41+22+5 (got $rc)" "asignaciones != 68 (got $rc)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug='videos.view' AND \"group\" LIKE 'Gesti%Noticias' AND \"module\"='Videos';")
check '[ "$c" = 1 ]' 'videos.view en grupo Gestion Noticias, modulo Videos' "videos.view mal ubicado (got $c)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug LIKE 'videos.%';")
check '[ "$c" = 4 ]' "4 permisos videos.* (got $c)" "permisos videos != 4 (got $c)"
c=$(sql "SELECT COUNT(*) FROM role_permission rp JOIN permissions p ON p.id=rp.permission_id WHERE p.slug LIKE 'videos.%' AND rp.role IN ('admin','editor');")
check '[ "$c" = 0 ]' 'admin/editor SIN permisos videos' "admin/editor tienen videos.* (got $c)"

echo '--- 2. Sidebar: seccion + dropdown por rol ---'
login 'admin@gdc.gob.ve'
ss="$COOKIE_JAR"
http "$E2E_BASE/admin/videos"
check '[ "$HTTP_STATUS" = 200 ]' 'superadmin ve /admin/videos' "status $HTTP_STATUS"
check 'grep -qE "Gesti.n Noticias</span>" "$HTTP_BODY"' 'sidebar: titulo de seccion' 'falta titulo de seccion'
check 'grep -q "id=\"mmMenu\"" "$HTTP_BODY"' 'sidebar: dropdown Multimedia' 'falta dropdown mmMenu'
check 'grep -qE "nav flex-column flex-nowrap[^\"]*\" id=\"mmMenu\"" "$HTTP_BODY"' 'sidebar: mmMenu con flex-nowrap (sin wrap lateral en la animacion)' 'mmMenu sin flex-nowrap'
check 'grep -qE "href=\"[^\"]*admin/videos\"" "$HTTP_BODY"' 'superadmin ve item Videos' 'falta item Videos'
check 'grep -q "tab-cargar" "$HTTP_BODY" && grep -q "tab-portada" "$HTTP_BODY"' 'pestanas Cargar + Portada' 'faltan pestanas'
check 'grep -q "name=\"type\"" "$HTTP_BODY" && grep -q "id=\"typeFile\"" "$HTTP_BODY"' 'form con toggle YouTube/Archivo' 'falta toggle origen'

login 'admin2@gdc.gob.ve'
admin="$COOKIE_JAR"
COOKIE_JAR="$admin"; http "$E2E_BASE/admin/videos"
check '[ "$HTTP_STATUS" = 403 ]' 'admin NO accede /admin/videos (403)' "admin status $HTTP_STATUS"
http "$E2E_BASE/admin/noticias"
check 'grep -qE "Gesti.n Noticias</span>" "$HTTP_BODY"' 'admin SI ve la seccion (tiene news/media)' 'admin sin seccion'
check 'grep -q "id=\"mmMenu\"" "$HTTP_BODY"' 'admin ve dropdown Multimedia' 'admin sin dropdown'
check 'grep -q ">Medios</a>" "$HTTP_BODY"' 'admin ve Medios en dropdown' 'admin sin Medios'
check '! grep -qE "href=\"[^\"]*admin/videos\"" "$HTTP_BODY"' 'admin NO ve item Videos' 'admin ve item Videos indebidamente'

login 'editor@gdc.gob.ve'
ed="$COOKIE_JAR"
COOKIE_JAR="$ed"; http "$E2E_BASE/admin/videos"
check '[ "$HTTP_STATUS" = 403 ]' 'editor NO accede /admin/videos (403)' "editor status $HTTP_STATUS"
http "$E2E_BASE/admin/noticias"
check 'grep -qE "Gesti.n Noticias</span>" "$HTTP_BODY"' 'editor SI ve la seccion' 'editor sin seccion'
check '! grep -qE "href=\"[^\"]*admin/videos\"" "$HTTP_BODY"' 'editor NO ve item Videos' 'editor ve item Videos indebidamente'

echo '--- 3. Cargar video YouTube ---'
COOKIE_JAR="$ss"
before=$(count_videos)
http "$E2E_BASE/admin/videos"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/videos" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=VidTest YT' \
  --data-urlencode 'type=youtube' \
  --data-urlencode 'url=https://www.youtube.com/watch?v=dQw4w9WgXcQ'
after=$(count_videos)
check '[ "$after" = $((before + 1)) ]' "alta youtube creada ($before -> $after)" "alta youtube fallo ($before -> $after)"
row=$(sqlt "SELECT type, url FROM videos WHERE title='VidTest YT';")
case "$row" in *youtube*dQw4w9WgXcQ*|*dQw4w9WgXcQ*youtube*) ytm=1 ;; *) ytm=0 ;; esac
check '[ "$ytm" = 1 ]' "datos youtube correctos ($row)" "datos youtube mal ($row)"

echo '--- 4. URL invalida rechazada ---'
before=$(count_videos)
http "$E2E_BASE/admin/videos" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=VidTest Bad' \
  --data-urlencode 'type=youtube' \
  --data-urlencode 'url=https://example.com/no-youtube'
after=$(count_videos)
check '[ "$after" = "$before" ]' "URL invalida rechazada ($after)" "URL invalida fue aceptada ($before -> $after)"

echo '--- 5. Subir archivo mp4 ---'
http "$E2E_BASE/admin/videos" \
  -F "_token=$token" \
  -F 'title=VidTest File' \
  -F 'type=file' \
  -F "file=@$mp4;type=video/mp4"
vidRow=$(sqlt "SELECT id, path FROM videos WHERE title='VidTest File';")
case "$vidRow" in *$'\t'*) rec=1 ;; *) rec=0 ;; esac
check '[ "$rec" = 1 ]' 'registro archivo creado' "registro archivo fallo ($vidRow)"
vidPath=$(printf '%s' "$vidRow" | cut -f2)
case "$vidPath" in videos/*) vp=1 ;; *) vp=0 ;; esac
check '[ "$vp" = 1 ]' "path en carpeta videos/ (got $vidPath)" "path fuera de videos/ (got $vidPath)"
check "[ -f \"$E2E_PUB/$vidPath\" ]" 'archivo mp4 en disco' 'archivo mp4 no existe en disco'
c=$(sql "SELECT type FROM videos WHERE title='VidTest File';")
check '[ "$c" = file ]' "type=file (got $c)" "type incorrecto (got $c)"

echo '--- 6. Portada: destacar / cambiar / ninguno ---'
idYT=$(sql "SELECT id FROM videos WHERE title='VidTest YT';")
idFile=$(sql "SELECT id FROM videos WHERE title='VidTest File';")
http "$E2E_BASE/admin/videos"
token=$(require_token "$HTTP_BODY") || exit 1
check 'grep -q "name=\"video_id\"" "$HTTP_BODY"' 'radios en pestana portada' 'faltan radios'
check 'grep -q "featNone" "$HTTP_BODY"' 'opcion Ninguno presente' 'falta opcion Ninguno'

http "$E2E_BASE/admin/videos/destacar" --data-urlencode "_token=$token" --data-urlencode "video_id=$idYT"
c=$(sql 'SELECT COUNT(*) FROM videos WHERE is_featured=true;')
check '[ "$c" = 1 ]' "destacar: 1 featured (got $c)" "destacar: featured=$c"
c=$(sql 'SELECT id FROM videos WHERE is_featured=true;')
check '[ "$c" = "$idYT" ]' "destacar apunta al video elegido ($c)" "destacar apunta a $c (esperado $idYT)"

http "$E2E_BASE/admin/videos/destacar" --data-urlencode "_token=$token" --data-urlencode "video_id=$idFile"
c=$(sql 'SELECT COUNT(*) FROM videos WHERE is_featured=true;')
check '[ "$c" = 1 ]' "cambio de destacado: sigue en 1 (got $c)" "cambio: featured=$c"
c=$(sql 'SELECT id FROM videos WHERE is_featured=true;')
check '[ "$c" = "$idFile" ]' 'destacado ahora es el archivo' "destacado es $c (esperado $idFile)"

http "$E2E_BASE/admin/videos/destacar" --data-urlencode "_token=$token" --data-urlencode 'video_id='
c=$(sql 'SELECT COUNT(*) FROM videos WHERE is_featured=true;')
check '[ "$c" = 0 ]' "Ninguno limpia destacados (got $c)" "Ninguno: featured=$c"

echo '--- 7. Home refleja la portada ---'
http "$E2E_BASE/"
check '! grep -q "VIDEOS INSTITUCIONALES" "$HTTP_BODY"' 'home SIN seccion sin destacado' 'home muestra seccion sin destacado'
http "$E2E_BASE/admin/videos"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/videos/destacar" --data-urlencode "_token=$token" --data-urlencode "video_id=$idYT"
http "$E2E_BASE/"
check 'grep -q "VIDEOS INSTITUCIONALES" "$HTTP_BODY"' 'home muestra seccion con destacado' 'home sin seccion con destacado'
check 'grep -q "youtube.com/embed/dQw4w9WgXcQ" "$HTTP_BODY"' 'home embebe YouTube destacado' 'home sin embed youtube'
http "$E2E_BASE/admin/videos"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/videos/destacar" --data-urlencode "_token=$token" --data-urlencode "video_id=$idFile"
http "$E2E_BASE/"
check 'grep -q "VIDEOS INSTITUCIONALES" "$HTTP_BODY"' 'home muestra seccion con archivo' 'home sin seccion con archivo'
check "grep -q '<video' \"\$HTTP_BODY\" && grep -q \"storage/$vidPath\" \"\$HTTP_BODY\"" 'home embebe video subido' 'home sin video subido'

echo '--- 8. Eliminar + limpieza ---'
http "$E2E_BASE/admin/videos"
token=$(require_token "$HTTP_BODY") || exit 1
for id in "$idYT" "$idFile"; do
  [ -z "$id" ] && continue
  http "$E2E_BASE/admin/videos/$id" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
done
c=$(sql "SELECT COUNT(*) FROM videos WHERE title LIKE 'VidTest%';")
check '[ "$c" = 0 ]' "videos de la suite eliminados (got $c)" "restos: $c"
check "[ ! -f \"$E2E_PUB/$vidPath\" ]" 'archivo mp4 borrado del disco' 'archivo mp4 quedo en disco'
[ -f "$mp4" ] && rm -f "$mp4"
check "[ ! -f \"$mp4\" ]" 'fixture temporal limpio' 'fixture temporal quedo'

echo '--- 9. Smoke ---'
for u in / /noticias /entes; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u -> $HTTP_STATUS"
done
COOKIE_JAR="$ss"
http "$E2E_BASE/admin/medios"
check '[ "$HTTP_STATUS" = 200 ]' '/admin/medios 200 (superadmin)' "status $HTTP_STATUS"
http "$E2E_BASE/admin/noticias"
check '[ "$HTTP_STATUS" = 200 ]' '/admin/noticias 200' "status $HTTP_STATUS"

summary 'VIDEOS'
