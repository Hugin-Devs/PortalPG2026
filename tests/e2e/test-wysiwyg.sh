#!/usr/bin/env bash
# test-wysiwyg.sh — port de test-wysiwyg.ps1 (TinyMCE: assets, HTML crudo, inline img, JSON biblioteca)
E2E_SUITE=wysiwyg
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-wysiwyg"
mkdir -p "$tmp"

# --- Limpieza quirurgica de corridas anteriores ---
while IFS= read -r p; do
  [ -z "$p" ] && continue
  [ -f "$E2E_PUB/$p" ] && rm -f "$E2E_PUB/$p"
  wp="${p%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done < <(sql "SELECT path FROM media WHERE original_name LIKE 'wysiwyg-%';")
sql "DELETE FROM posts WHERE title LIKE 'E2E WYSIWYG%';" >/dev/null
sql "DELETE FROM media WHERE original_name LIKE 'wysiwyg-%';" >/dev/null
echo 'pre-limpieza: artefactos de corridas anteriores removidos'

echo '--- 1. TinyMCE presente en crear noticia ---'
login 'admin@gdc.gob.ve'
http "$E2E_BASE/admin/noticias/crear"
check 'grep -q "tinymce\.min\.js" "$HTTP_BODY"' 'script tinymce cargado' 'falta script tinymce'
check 'grep -q "id=\"content\"" "$HTTP_BODY"' 'textarea content presente' 'falta textarea content'
check 'grep -q "license_key" "$HTTP_BODY"' 'license_key gpl declarado' 'falta license_key'
check "! grep -qF \"setRequestHeader('Accept', 'application/json')\" \"\$HTTP_BODY\"" 'sin handler de subida Accept json (D15)' 'queda handler Accept json'
check '! grep -zPq "id=\"content\"[^>]*\brequired\b" "$HTTP_BODY"' 'textarea content sin required nativo' 'textarea tiene required nativo'
check 'grep -q "triggerSave" "$HTTP_BODY"' 'guard de submit con triggerSave' 'falta guard de submit'
check '! grep -q "images_upload_handler" "$HTTP_BODY"' 'sin images_upload_handler (D15: imagenes via galeria)' 'queda images_upload_handler'
check "grep -qF \"plugins: 'lists link code help wordcount'\" \"\$HTTP_BODY\"" 'plugin image removido de plugins' 'sigue el plugin image'
check '! grep -qF "bullist numlist | link image" "$HTTP_BODY"' 'boton image removido del toolbar' 'sigue el boton image'
check 'grep -q "no se admiten dentro del texto" "$HTTP_BODY"' 'aviso visible bajo el editor' 'falta aviso de imagenes'
check 'grep -q "vendor/tinymce" "$HTTP_BODY"' 'base_url vendor/tinymce' 'falta base_url'
check 'grep -q "language_url" "$HTTP_BODY"' 'idioma configurado' 'falta idioma'
check 'grep -q "name=\"_token\"" "$HTTP_BODY"' 'csrf en form' 'falta csrf'
token=$(require_token "$HTTP_BODY") || exit 1

echo '--- 2. Assets TinyMCE servidos (offline) ---'
for u in /vendor/tinymce/tinymce.min.js /vendor/tinymce/themes/silver/theme.min.js /vendor/tinymce/langs/es.js; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u -> $HTTP_STATUS"
done

echo '--- 3. Crear noticia con HTML del editor ---'
html='<p>Parrafo con <strong>negrita</strong> y <em>cursiva</em>.</p><ul><li>Item 1</li></ul>'
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E WYSIWYG HTML' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto WYSIWYG' \
  --data-urlencode "content=$html" \
  --data-urlencode 'status=published'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'redirect al listado' "uri: $HTTP_FINAL"
row=$(sql "SELECT content FROM posts WHERE title='E2E WYSIWYG HTML';")
case "$row" in *'<strong>negrita</strong>'*) hr=1 ;; *) hr=0 ;; esac
check '[ "$hr" = 1 ]' 'HTML guardado crudo en BD' "contenido alterado: $row"

echo '--- 4. Detalle publico renderiza HTML sin escapar ---'
slug=$(sql "SELECT slug FROM posts WHERE title='E2E WYSIWYG HTML';")
http "$E2E_BASE/noticias/$slug"
check 'grep -qF "<strong>negrita</strong>" "$HTTP_BODY"' 'detalle muestra <strong> crudo' 'HTML escapado en detalle'
check '! grep -qF "&lt;strong&gt;" "$HTTP_BODY"' 'sin entidades escapadas' 'hay entidades &lt; en detalle'
check 'grep -qF "<li>Item 1</li>" "$HTTP_BODY"' 'lista renderizada' 'lista no renderiza'

echo '--- 5. Contenido plano legado conserva saltos (nl2br) ---'
insOut=$(sql "INSERT INTO posts (title, slug, excerpt, content, category_id, user_id, status, published_at, created_at, updated_at) VALUES ('E2E WYSIWYG Plano', 'e2e-wysiwyg-plano', 'x', 'Linea uno
Linea dos', 1, 1, 'published', date_trunc('second', now()), date_trunc('second', now()), date_trunc('second', now()));")
planoCnt=$(sql "SELECT COUNT(*) FROM posts WHERE slug='e2e-wysiwyg-plano';")
check '[ "$planoCnt" = 1 ]' 'INSERT de contenido plano OK' "INSERT fallo: $insOut"
http "$E2E_BASE/noticias/e2e-wysiwyg-plano"
check 'grep -qE "<br />|<br>" "$HTTP_BODY"' 'texto plano usa nl2br' 'sin <br> en texto plano'
check '! grep -qF "Linea uno&lt;" "$HTTP_BODY"' 'sin escape residual' 'escape residual'

echo '--- 5b. Imagen inline en el texto: eliminada + aviso visible (D15) ---'
b64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
imgHtml="<p>Texto con imagen:</p><img src=\"data:image/png;base64,$b64\" alt=\"x\">"
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E WYSIWYG Imagen' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=Extracto img' \
  --data-urlencode "content=$imgHtml" \
  --data-urlencode 'status=published'
check '[[ "$HTTP_FINAL" == */admin/noticias ]]' 'acepta y redirige al listado' "uri: $HTTP_FINAL"
row=$(sql "SELECT content FROM posts WHERE title='E2E WYSIWYG Imagen';")
case "$row" in *'data:image/png;base64'*) inline=1 ;; *) inline=0 ;; esac
check '[ "$inline" = 0 ]' 'imagen inline eliminada del texto' "imagen inline quedo persistida: $row"
case "$row" in *'Texto con imagen'*) tp=1 ;; *) tp=0 ;; esac
check '[ "$tp" = 1 ]' 'parrafo del texto conservado' 'se perdio el texto'
check 'grep -q "Se eliminaron" "$HTTP_BODY"' 'aviso visible de imagenes eliminadas' 'falta el aviso en el listado'

echo '--- 5c. Contenido vacio -> validacion del servidor ---'
http "$E2E_BASE/admin/noticias/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/noticias" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'title=E2E WYSIWYG Vacio' \
  --data-urlencode 'category_id=1' \
  --data-urlencode 'excerpt=x' \
  --data-urlencode 'content=' \
  --data-urlencode 'status=draft'
check '[[ "$HTTP_FINAL" == */crear ]]' "vuelve al form con error (uri: $HTTP_FINAL)" "no valido: $HTTP_FINAL"
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title='E2E WYSIWYG Vacio';")
check '[ "$cnt" = 0 ]' 'no creo el post vacio' "creo post: $cnt"

echo '--- 6. Endpoint JSON de la biblioteca Medios sigue activo ---'
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/wysiwyg-up.png"
http "$E2E_BASE/admin/medios" \
  -H 'Accept: application/json' \
  --form-string "_token=$token" \
  -F "files[]=@$tmp/wysiwyg-up.png;type=image/png"
check '[ "$HTTP_STATUS" = 200 ]' "JSON 200 (got $HTTP_STATUS)" "JSON status $HTTP_STATUS"
check 'grep -qF "\"location\"" "$HTTP_BODY"' 'JSON con location' 'sin location en JSON'
jsonPath=$(grep -oP '"location"\s*:\s*"\K[^"]+' "$HTTP_BODY" | head -1 | sed 's|\\/|/|g')
case "$jsonPath" in *storage/uploads/*) jl=1 ;; *) jl=0 ;; esac
check '[ "$jl" = 1 ]' "location a uploads ($jsonPath)" "location invalida: $jsonPath"

echo '--- 7. Medios: Copiar URL + fallback ---'
http "$E2E_BASE/admin/medios"
check 'grep -q "copy-url-btn" "$HTTP_BODY"' 'boton Copiar URL presente' 'falta boton Copiar URL'
check 'grep -q "mediaCopyFallback" "$HTTP_BODY"' 'fallback copiar presente' 'falta fallback'
check 'grep -q "isSecureContext" "$HTTP_BODY"' 'guarda secure context' 'sin guarda'
check 'grep -q "data-url=" "$HTTP_BODY"' 'data-url en botones' 'sin data-url'

echo '--- 8. Permisos: editor con media.upload ---'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/medios"
check '[ "$HTTP_STATUS" = 200 ]' 'editor accede a /admin/medios' "status $HTTP_STATUS"
check 'grep -qF "name=\"files[]\"" "$HTTP_BODY"' 'editor ve el form de subida' 'editor sin form de subida'

echo '--- 9. Cleanup ---'
sql "DELETE FROM posts WHERE title LIKE 'E2E WYSIWYG%';" >/dev/null
cnt=$(sql "SELECT COUNT(*) FROM posts WHERE title LIKE 'E2E WYSIWYG%';")
check '[ "$cnt" = 0 ]' 'posts de prueba eliminados' "restan: $cnt"
while IFS= read -r p; do
  [ -z "$p" ] && continue
  if [ -f "$E2E_PUB/$p" ]; then rm -f "$E2E_PUB/$p"; ok "borrado disco: $p"; fi
  wp="${p%.*}.webp"
  if [ -f "$E2E_PUB/$wp" ]; then rm -f "$E2E_PUB/$wp"; ok "borrado disco sidecar: ${wp##*/}"; fi
done < <(sql "SELECT path FROM media WHERE original_name LIKE 'wysiwyg-%';")
sql "DELETE FROM media WHERE original_name LIKE 'wysiwyg-%';" >/dev/null
cnt=$(sql "SELECT COUNT(*) FROM media WHERE original_name LIKE 'wysiwyg-%';")
check '[ "$cnt" = 0 ]' 'media de prueba eliminada' "restan: $cnt"
rm -f "$tmp/wysiwyg-up.png"

echo '--- 10. Smoke ---'
for u in / /noticias /gacetas; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u -> $HTTP_STATUS"
done

summary 'WYSIWYG'
