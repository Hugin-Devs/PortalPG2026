#!/usr/bin/env bash
# test-contact-messages.sh — port de test-contact-messages.ps1 (formulario publico + bandeja admin)
# PII cifrado (PRIV-01): name/email/subject/message cifrados en BD; las aserciones
# SQL se apoyan en id; el texto plano se valida por la interfaz del panel.
E2E_SUITE=contact-messages
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo '--- 0. Estado inicial ---'
baseMaxId=$(sql 'SELECT COALESCE(MAX(id),0) FROM contacts;')
baseCount=$(sql 'SELECT COUNT(*) FROM contacts;')
check '[ -n "$baseCount" ]' "punto de partida registrado (max id=$baseMaxId, total=$baseCount)" 'sin estado inicial'

echo '--- 1. Enviar formulario publico (/contacto) ---'
COOKIE_JAR="$E2E_TMP/e2e-${E2E_SUITE}-pub.jar"; rm -f "$COOKIE_JAR"
http "$E2E_BASE/contacto"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'name=Juan E2E' \
  --data-urlencode 'email=juan@ejemplo.com' \
  --data-urlencode 'subject=E2E Primer Mensaje' \
  --data-urlencode 'message=Hola, este es el mensaje de prueba 1.'
check 'grep -q "Mensaje enviado correctamente" "$HTTP_BODY"' 'confirmacion de exito visible' 'sin confirmacion'
id1=$(sql 'SELECT MAX(id) FROM contacts;')
check '[ "${id1:-0}" -gt "${baseMaxId:-0}" ]' 'fila persistida en contacts' 'no se guardo'
r=$(sql "SELECT (read_at IS NULL)::int FROM contacts WHERE id = $id1;")
check '[ "$r" = 1 ]' 'read_at NULL al llegar' 'read_at no NULL'

echo '--- 2. Segundo mensaje (para probar delete) ---'
http "$E2E_BASE/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'name=Maria E2E' \
  --data-urlencode 'email=maria@ejemplo.com' \
  --data-urlencode 'subject=E2E Segundo Mensaje' \
  --data-urlencode 'message=Mensaje de prueba 2.'
id2=$(sql 'SELECT MAX(id) FROM contacts;')
check '[ "${id2:-0}" -gt "${id1:-0}" ]' 'segunda fila persistida' 'no se guardo'

echo '--- 3. Validacion: email invalido no persiste ---'
http "$E2E_BASE/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'name=Fake' \
  --data-urlencode 'email=no-es-email' \
  --data-urlencode 'subject=E2E No Debe Guardarse' \
  --data-urlencode 'message=x'
tot=$(sql 'SELECT COUNT(*) FROM contacts;')
check '[ "$tot" = $((baseCount + 2)) ]' 'email invalido rechazado' 'se guardo igual'

echo '--- 4. Bandeja admin: lista ---'
login 'admin@gdc.gob.ve'
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'LOGIN admin' 'login fallido'
http "$E2E_BASE/admin/contacto/mensajes"
check '[ "$HTTP_STATUS" = 200 ]' 'GET mensajes -> 200' "status $HTTP_STATUS"
check 'grep -q "E2E Primer Mensaje" "$HTTP_BODY"' 'lista muestra asunto 1' 'falta asunto 1'
check 'grep -q "E2E Segundo Mensaje" "$HTTP_BODY"' 'lista muestra asunto 2' 'falta asunto 2'
check 'grep -q "Nuevo" "$HTTP_BODY"' 'badge Nuevo presente' 'falta badge'
check 'grep -qE "juan@ejemplo\.com" "$HTTP_BODY"' 'email visible' 'falta email'
check 'grep -q ">Mensajes</a>" "$HTTP_BODY"' 'item sidebar Mensajes visible' 'sidebar sin Mensajes'

echo '--- 5. Detalle marca leido ---'
id=$id1
http "$E2E_BASE/admin/contacto/mensajes/$id"
check '[ "$HTTP_STATUS" = 200 ]' "GET detalle -> 200 (id=$id)" "status $HTTP_STATUS"
check 'grep -qE "Hola, este es el mensaje de prueba 1\." "$HTTP_BODY"' 'cuerpo del mensaje visible' 'falta cuerpo'
check 'grep -qE "juan@ejemplo\.com" "$HTTP_BODY"' 'remitente visible' 'falta remitente'
r=$(sql "SELECT (read_at IS NOT NULL)::int FROM contacts WHERE id = $id;")
check '[ "$r" = 1 ]' 'read_at marcado en BD' 'read_at sin marcar'
http "$E2E_BASE/admin/contacto/mensajes"
check 'grep -qE "Le.do" "$HTTP_BODY"' 'lista refleja Leido' 'no refleja Leido'

echo '--- 6. Borrar mensaje ---'
http "$E2E_BASE/admin/contacto/mensajes"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/contacto/mensajes/$id" --data-urlencode "_token=$token" --data-urlencode '_method=DELETE'
check '[[ "$HTTP_FINAL" == */admin/contacto/mensajes ]]' 'DELETE redirige a lista' "no redirigio: $HTTP_FINAL"
r=$(sql "SELECT COUNT(*) FROM contacts WHERE id = $id;")
check '[ "$r" = 0 ]' 'fila eliminada' 'fila no eliminada'
check 'grep -q "Mensaje eliminado correctamente" "$HTTP_BODY"' 'alerta de exito' 'sin alerta'

echo '--- 7. Permisos: admin2 ve, editor no ---'
login 'admin2@gdc.gob.ve'
http "$E2E_BASE/admin/contacto/mensajes"
check '[ "$HTTP_STATUS" = 200 ]' 'admin -> 200 en mensajes' "admin: $HTTP_STATUS"
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/contacto/mensajes"
check '[ "$HTTP_STATUS" = 403 ]' 'editor -> 403 en mensajes' "editor obtuvo $HTTP_STATUS (esperado 403)"
http "$E2E_BASE/admin"
check '! grep -q ">Mensajes</a>" "$HTTP_BODY"' 'editor NO ve item Mensajes' 'editor SI ve item'

echo '--- 8. Smoke ---'
for p in / /contacto /sobre-nosotros; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

echo '--- 9. Cleanup ---'
sql "DELETE FROM contacts WHERE id > $baseMaxId;" >/dev/null
tot=$(sql 'SELECT COUNT(*) FROM contacts;')
check '[ "$tot" = "$baseCount" ]' "filas E2E eliminadas; previas intactas (total $tot)" "total=$tot (esperado $baseCount)"

summary 'CONTACTO MENSAJES'
