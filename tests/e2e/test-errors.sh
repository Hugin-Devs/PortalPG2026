#!/usr/bin/env bash
# test-errors.sh — port de test-errors.ps1 (paginas de error 404/403/405/419/503 + render_errors.php)
E2E_SUITE=errors
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo '--- 1. 404 (pagina inexistente) ---'
http "$E2E_BASE/pagina-que-no-existe-xyz"
check '[ "$HTTP_STATUS" = 404 ]' "status 404 (got $HTTP_STATUS)" 'status != 404'
check 'grep -qE "P.gina no encontrada" "$HTTP_BODY"' 'titulo en espanol' 'titulo no encontrado'
check 'grep -q ">Volver<" "$HTTP_BODY"' 'boton Volver' 'sin boton Volver'
check 'grep -q "Ir al inicio" "$HTTP_BODY"' 'boton Ir al inicio' 'sin boton inicio'

echo '--- 2. 403 (editor en /admin/contacto) ---'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/contacto"
check '[ "$HTTP_STATUS" = 403 ]' "status 403 (got $HTTP_STATUS)" 'status != 403'
check 'grep -q "Acceso denegado" "$HTTP_BODY"' 'titulo en espanol' 'titulo no encontrado'
check 'grep -q ">Volver<" "$HTTP_BODY"' 'boton Volver' 'sin boton Volver'
check 'grep -q "Ir al panel" "$HTTP_BODY"' 'boton Ir al panel (auth)' 'sin boton panel'

echo '--- 3. 405 (DELETE a GET/POST route) ---'
COOKIE_JAR="$E2E_TMP/e2e-${E2E_SUITE}-anon.jar"; rm -f "$COOKIE_JAR"
http "$E2E_BASE/contacto" -X DELETE
check '[ "$HTTP_STATUS" = 405 ]' "status 405 (got $HTTP_STATUS)" 'status != 405'
check 'grep -qE "M&eacute;todo no permitido|M.todo no permitido" "$HTTP_BODY"' 'titulo en espanol' 'titulo no encontrado'
check 'grep -q ">Volver<" "$HTTP_BODY"' 'boton Volver' 'sin boton Volver'

echo '--- 4. 419 (POST con token invalido) ---'
cntBefore=$(sql 'SELECT COUNT(*) FROM contacts;')
http "$E2E_BASE/contacto" \
  --data-urlencode '_token=token-invalido-prueba' \
  --data-urlencode 'name=X' --data-urlencode 'email=x@x.com' \
  --data-urlencode 'subject=X' --data-urlencode 'message=X'
check '[ "$HTTP_STATUS" = 419 ]' "status 419 (got $HTTP_STATUS)" 'status != 419'
check 'grep -qE "Sesi.n expirada" "$HTTP_BODY"' 'titulo en espanol' 'titulo no encontrado'
check 'grep -q "Recargar e intentar" "$HTTP_BODY"' 'boton Recargar' 'sin boton recargar'
check 'grep -q ">Volver<" "$HTTP_BODY"' 'boton Volver' 'sin boton Volver'
cntAfter=$(sql 'SELECT COUNT(*) FROM contacts;')
check '[ "$cntBefore" = "$cntAfter" ]' 'contacts intacto (no escribio)' 'contacts cambio'

echo '--- 5. Render PHP de las 12 vistas ---'
renderOut=$(php "$E2E_LIB_DIR/render_errors.php"); renderEc=$?
printf '%s\n' "$renderOut" | sed 's/^/  /'
if [ "$renderEc" -ne 0 ]; then FAILN=$((FAILN+1)); fi

echo '--- 6. 503 real via maintenance mode ---'
( cd "$E2E_ROOT" && php artisan down --no-interaction >/dev/null 2>&1 )
http "$E2E_BASE/"
check '[ "$HTTP_STATUS" = 503 ]' "status 503 (got $HTTP_STATUS)" 'status != 503'
check 'grep -q "Servicio no disponible" "$HTTP_BODY"' 'titulo en espanol' 'titulo no encontrado'
check 'grep -q "Reintentar" "$HTTP_BODY"' 'boton Reintentar' 'sin boton reintentar'
check 'grep -q "Ir al inicio" "$HTTP_BODY"' 'boton Ir al inicio' 'sin boton inicio'
( cd "$E2E_ROOT" && php artisan up --no-interaction >/dev/null 2>&1 )

echo '--- 7. Smoke (site de vuelta) ---'
for p in / /contacto /noticias; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

summary 'ERRORES'
