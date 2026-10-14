#!/usr/bin/env bash
# test-media.sh — port de test-media.ps1 (imagen destacada: upload, biblioteca, quitar, soft delete)
E2E_SUITE=media
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-media"
mkdir -p "$tmp"

# --- PNGs de prueba ---
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/notimg1.png"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR42mM8z8Dwn4GBgYERABIcAgO2mGV+AAAAAElFTkSuQmCC' | base64 -d > "$tmp/notimg2.png"
printf 'hola' > "$tmp/fake.txt"

# --- Login ---
login 'admin@gdc.gob.ve'
grep -q 'Admin GDC' "$HTTP_BODY" || { echo 'Login fallido' >&2; exit 1; }
ok 'LOGIN'

# --- Limpieza previa ---
sql "DELETE FROM posts WHERE title='E2E Imagen Destacada';" >/dev/null
# Artefactos media de corridas anteriores (el .ps1 solo limpiaba al final; aqui
# tambien al inicio para que la corrida sea determinista tras un crash parcial).
while IFS= read -r p; do
  [ -z "$p" ] && continue
  [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  wp="${p%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done < <(sql "SELECT path FROM media WHERE original_name IN ('notimg1.png','notimg2.png','notimg3.png','notimg4.png');")
sql "DELETE FROM media WHERE original_name IN ('notimg1.png','notimg2.png','notimg3.png','notimg4.png');" >/dev/null

echo '--- 1. Formulario de creacion ---'
http "$E2E_BASE/admin/noticias/crear"
check 'grep -q "multipart/form-data" "$HTTP_BODY"' 'enctype multipart presente' 'falta enctype multipart'
check 'grep -q "mediaLibraryModal" "$HTTP_BODY"' 'modal de biblioteca presente' 'falta modal biblioteca'
check 'grep -q "featured_image_file" "$HTTP_BODY"' 'input file presente' 'falta input file'
check 'grep -q "Elegir de la biblioteca" "$HTTP_BODY"' 'boton biblioteca presente' 'falta boton biblioteca'
token=$(require_token "$HTTP_BODY") || exit 1

echo '--- 2. Crear noticia CON archivo nuevo ---'
http "$E2E_BASE/admin/noticias" \
  --form-string "_token=$token" \
  --form-string 'title=E2E Imagen Destacada' \
  --form-string 'category_id=1' \
  --form-string 'excerpt=Extracto E2E' \
  --form-string 'content=<p>Contenido E2E</p>' \
  --form-string 'status=published' \
  -F "featured_image_file=@$tmp/notimg1.png;type=image/png"
case "$HTTP_STATUS" in 200|302) cst=1 ;; *) cst=0 ;; esac
check '[ "$cst" = 1 ]' "POST create -> $HTTP_STATUS" "POST create fallo: $HTTP_STATUS"

row=$(sqlt "SELECT id, featured_image FROM posts WHERE title='E2E Imagen Destacada';")
id=$(printf '%s' "$row" | cut -f1); fiPath=$(printf '%s' "$row" | cut -f2)
check '[ -n "$id" ]' "post id=$id" 'post no creado'
case "$fiPath" in uploads/*) uf=1 ;; *) uf=0 ;; esac
check '[ "$uf" = 1 ]' "featured_image=$fiPath" "featured_image invalido: $fiPath"
mrow=$(sql "SELECT mime_type FROM media WHERE path='$fiPath';")
case "$mrow" in image/*) im=1 ;; *) im=0 ;; esac
check '[ "$im" = 1 ]' "media auto-registrada (mime=$mrow)" 'media NO auto-registrada'
check "[ -f \"$E2E_PUB/$fiPath\" ]" 'archivo en storage/app/public' 'archivo no existe en disco'
img1Path="$E2E_PUB/$fiPath"

echo '--- 3. Visible en publico (/noticias) ---'
http "$E2E_BASE/noticias"
check "grep -q \"storage/$fiPath\" \"\$HTTP_BODY\"" 'home/listado muestra asset(storage/...)' 'imagen NO visible en /noticias'

echo '--- 4. Subir 2a imagen a la biblioteca ---'
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios" --form-string "_token=$token" -F "files[]=@$tmp/notimg2.png;type=image/png"
libPath=$(sql "SELECT path FROM media ORDER BY id DESC LIMIT 1;")
case "$libPath" in uploads/*) lp=1 ;; *) lp=0 ;; esac
check '[ "$lp" = 1 ]' "biblioteca: $libPath" 'subida a biblioteca fallo'

echo '--- 4b. Subida MULTIPLE (2 archivos en un request) ---'
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/notimg3.png"
cp "$tmp/notimg3.png" "$tmp/notimg4.png"
http "$E2E_BASE/admin/medios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/medios" --form-string "_token=$token" \
  -F "files[]=@$tmp/notimg3.png;type=image/png" \
  -F "files[]=@$tmp/notimg4.png;type=image/png"
check 'grep -q "2 archivos subidos correctamente" "$HTTP_BODY"' 'flash plural 2 archivos' 'flash multiple incorrecto'
mrow=$(sql "SELECT COUNT(*) FROM media WHERE original_name IN ('notimg3.png','notimg4.png');")
check '[ "$mrow" = 2 ]' "2 filas en media (got $mrow)" "filas != 2: $mrow"
mpaths=$(sqlt "SELECT path FROM media WHERE original_name IN ('notimg3.png','notimg4.png') ORDER BY id;")
m1=$(printf '%s' "$mpaths" | sed -n 1p); m2=$(printf '%s' "$mpaths" | sed -n 2p)
check "[ -f \"$E2E_PUB/$m1\" ] && [ -f \"$E2E_PUB/$m2\" ]" 'ambos archivos en disco' 'faltan archivos en disco'
http "$E2E_BASE/admin/medios"
check "grep -q \"$m1\" \"\$HTTP_BODY\" && grep -q \"$m2\" \"\$HTTP_BODY\"" 'grid muestra ambos archivos' 'grid incompleto'

echo '--- 5. Edit page: modal con imagen de biblioteca + checkbox quitar ---'
http "$E2E_BASE/admin/noticias/$id/editar"
check "grep -q \"data-path=\\\"$libPath\\\"\" \"\$HTTP_BODY\"" 'modal lista imagenes de biblioteca' 'modal no contiene la imagen'
check 'grep -q "Quitar imagen actual" "$HTTP_BODY"' 'checkbox quitar presente' 'falta checkbox quitar'
token=$(require_token "$HTTP_BODY") || exit 1

echo '--- 6. Editar eligiendo de la BIBLIOTECA (sin archivo) ---'
http "$E2E_BASE/admin/noticias/$id" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Imagen Destacada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E' \
  --data-urlencode 'content=<p>Contenido E2E</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode "featured_image=$libPath"
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' "redirect exitoso a listado ($HTTP_FINAL)" "no redirigio al listado: $HTTP_FINAL"
now=$(sql "SELECT featured_image FROM posts WHERE id=$id;")
check '[ "$now" = "$libPath" ]' "BD apunta a biblioteca ($now)" "BD no cambio: $now"
check "[ -f \"$img1Path\" ]" 'archivo del upload original NO se borro' 'archivo original erroneamente borrado'

echo '--- 7. Path inexistente en media -> rechazado, conserva actual ---'
http "$E2E_BASE/admin/noticias/$id/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias/$id" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Imagen Destacada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E' \
  --data-urlencode 'content=<p>Contenido E2E</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode 'featured_image=uploads/hack-no-existe.jpg'
check '[[ "$HTTP_FINAL" == */editar ]]' "validacion rechazo (volvio al form: $HTTP_FINAL)" "NO rechazo: redirigio a $HTTP_FINAL"
now=$(sql "SELECT featured_image FROM posts WHERE id=$id;")
check '[ "$now" = "$libPath" ]' "path falso rechazado, conserva $libPath" "path falso ACEPTADO: $now"

echo '--- 8. Editar SIN tocar imagen -> la conserva ---'
http "$E2E_BASE/admin/noticias/$id" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Imagen Destacada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E v2' \
  --data-urlencode 'content=<p>Contenido E2E</p>' \
  --data-urlencode 'status=published'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'update sin tocar imagen -> exitoso' "fallo: $HTTP_FINAL"
now=$(sql "SELECT featured_image FROM posts WHERE id=$id;")
check '[ "$now" = "$libPath" ]' "sin tocar conserva ($now)" "perdio la imagen: $now"
exc=$(sql "SELECT excerpt FROM posts WHERE id=$id;")
check '[ "$exc" = "Extracto E2E v2" ]' 'el resto del update si aplico' "el update no aplico: $exc"

echo '--- 9. Quitar imagen (checkbox) ---'
http "$E2E_BASE/admin/noticias/$id" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'title=E2E Imagen Destacada' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto E2E v2' \
  --data-urlencode 'content=<p>Contenido E2E</p>' \
  --data-urlencode 'status=published' \
  --data-urlencode 'remove_image=1'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'quitar imagen -> exitoso' "fallo: $HTTP_FINAL"
now=$(sql "SELECT (featured_image IS NULL)::int FROM posts WHERE id=$id;")
check '[ "$now" = 1 ]' 'featured_image IS NULL tras quitar' "no se quito: $now"
check "[ -f \"$E2E_PUB/$libPath\" ]" 'archivo de biblioteca NO se borro al quitar' 'archivo de biblioteca borrado (mal)'
http "$E2E_BASE/noticias"
check "! grep -q \"storage/$libPath\" \"\$HTTP_BODY\"" 'publico ya no muestra la imagen' 'publico la sigue mostrando'

echo '--- 10. Rechazar archivo no-imagen ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias" \
  --form-string "_token=$token" \
  --form-string 'title=E2E No Debe Crearse' \
  --form-string 'category_id=1' \
  --form-string 'excerpt=x' \
  --form-string 'content=y' \
  --form-string 'status=draft' \
  -F "featured_image_file=@$tmp/fake.txt;type=text/plain"
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title='E2E No Debe Crearse';")
check '[ "$cnt" = 0 ]' 'archivo no-imagen rechazado (0 filas)' "se creo post: $cnt"

echo '--- 11. Cleanup ---'
http "$E2E_BASE/admin/noticias/$id" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE id=$id AND deleted_at IS NULL;")
check '[ "$cnt" = 0 ]' 'post eliminado del listado (soft delete)' "post visible aun: $cnt"
trashed=$(sql "SELECT (deleted_at IS NOT NULL)::int FROM posts WHERE id=$id;")
check '[ "$trashed" = 1 ]' 'deleted_at seteado (soft delete ok)' "deleted_at no seteado: $trashed"
for p in "$fiPath" "$libPath" "$m1" "$m2"; do
  if [ -n "$p" ] && [ -f "$E2E_PUB/$p" ]; then rm -f "$E2E_PUB/$p"; ok "borrado disco: $p"; fi
  wp="${p%.*}.webp"
  if [ -n "$p" ] && [ -f "$E2E_PUB/$wp" ]; then rm -f "$E2E_PUB/$wp"; ok "borrado disco sidecar: ${wp##*/}"; fi
done
sql "DELETE FROM media WHERE path IN ('$fiPath','$libPath','$m1','$m2');" >/dev/null
cnt=$(sql "SELECT COUNT(*) FROM media WHERE path IN ('$fiPath','$libPath','$m1','$m2');")
check '[ "$cnt" = 0 ]' 'media limpia' "media sucia: $cnt"
sql "DELETE FROM posts WHERE title='E2E Imagen Destacada';" >/dev/null
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title='E2E Imagen Destacada';")
check '[ "$cnt" = 0 ]' 'fila de post de prueba purgada de la BD' "resto: $cnt"

summary 'RESUMEN'
