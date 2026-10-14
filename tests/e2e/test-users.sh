#!/usr/bin/env bash
# test-users.sh — port de test-users.ps1 (CRUD usuarios, is_active, last_login_at, guard self-deactivate)
# Nota: usuarios creados por el panel quedan con password_changed_at = NULL y el
# middleware los redirige al cambio forzado; do_login() los marca como cambiados.
E2E_SUITE=users
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

do_login() { # $1 = email (como Login del .ps1: arregla password_changed_at antes)
  sql "UPDATE users SET password_changed_at = now() WHERE email='$1' AND password_changed_at IS NULL;" >/dev/null
  login "$1"
}

# --- Limpieza quirurgica: solo usuarios E2E de corridas anteriores ---
sql "DELETE FROM users WHERE email IN ('e2e-activo@prueba.local','e2e-inactivo@prueba.local');" >/dev/null

echo '--- 1. Login superadmin + columnas del listado ---'
do_login 'admin@gdc.gob.ve'
superJar="$COOKIE_JAR"
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'LOGIN superadmin' 'login superadmin fallido'
http "$E2E_BASE/admin/usuarios"
check 'grep -q ">Estado<" "$HTTP_BODY"' 'columna Estado' 'falta columna Estado'
check 'grep -q "ltima conexi" "$HTTP_BODY"' 'columna Ultima conexion' 'falta columna Ultima conexion'
check 'grep -q "Activo" "$HTTP_BODY"' 'badge Activo presente' 'sin badges de estado'

echo '--- 2. Crear usuario ACTIVO (checkbox marcado) ---'
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/usuarios" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'name=E2E Activo' \
  --data-urlencode 'email=e2e-activo@prueba.local' \
  --data-urlencode 'password=password' \
  --data-urlencode 'password_confirmation=password' \
  --data-urlencode 'role=editor' \
  --data-urlencode 'is_active=on'
check '[[ "$HTTP_FINAL" == */admin/usuarios ]]' 'redirect al listado' "uri: $HTTP_FINAL"
row=$(sql "SELECT is_active::int FROM users WHERE email='e2e-activo@prueba.local';")
check '[ "$row" = 1 ]' "usuario activo (is_active=$row)" "is_active incorrecto: $row"

echo '--- 3. Login usuario activo -> entra y registra last_login_at ---'
do_login 'e2e-activo@prueba.local'
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'usuario activo entra al admin' 'no pudo entrar'
l=$(sql "SELECT (last_login_at IS NOT NULL)::int FROM users WHERE email='e2e-activo@prueba.local';")
check '[ "$l" = 1 ]' "last_login_at registrado ($l)" "last_login_at no se registro: $l"

echo '--- 4. Crear usuario INACTIVO (checkbox ausente) ---'
COOKIE_JAR="$superJar"
http "$E2E_BASE/admin/usuarios"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/usuarios" \
  --data-urlencode "_token=$token" \
  --data-urlencode 'name=E2E Inactivo' \
  --data-urlencode 'email=e2e-inactivo@prueba.local' \
  --data-urlencode 'password=password' \
  --data-urlencode 'password_confirmation=password' \
  --data-urlencode 'role=editor'
check '[[ "$HTTP_FINAL" == */admin/usuarios ]]' 'redirect al listado' "uri: $HTTP_FINAL"
row=$(sql "SELECT is_active::int FROM users WHERE email='e2e-inactivo@prueba.local';")
check '[ "$row" = 0 ]' "usuario inactivo (is_active=$row)" "is_active incorrecto: $row"

echo '--- 5. Login usuario inactivo -> rechazado (mensaje generico) ---'
login 'e2e-inactivo@prueba.local' '-inactivo'
check 'grep -q "Credenciales" "$HTTP_BODY"' 'mensaje generico Credenciales' 'sin mensaje de error'
check '! grep -q "inactiva" "$HTTP_BODY"' 'sin revelar estado de la cuenta' 'revela que la cuenta esta inactiva'
check '! grep -q "Admin GDC" "$HTTP_BODY"' 'no entra al admin' 'entro estando inactivo'
l=$(sql "SELECT (last_login_at IS NULL)::int FROM users WHERE email='e2e-inactivo@prueba.local';")
check '[ "$l" = 1 ]' 'last_login_at sigue NULL' 'last_login_at fue seteado'

echo '--- 6. Activarlo -> ahora si entra ---'
COOKIE_JAR="$superJar"
id=$(sql "SELECT id FROM users WHERE email='e2e-inactivo@prueba.local';")
http "$E2E_BASE/admin/usuarios/$id/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/usuarios/$id" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'name=E2E Inactivo' \
  --data-urlencode 'email=e2e-inactivo@prueba.local' \
  --data-urlencode 'role=editor' \
  --data-urlencode 'is_active=on'
check '[[ "$HTTP_FINAL" == */admin/usuarios ]]' 'update -> redirect' "uri: $HTTP_FINAL"
do_login 'e2e-inactivo@prueba.local'
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'usuario ya activo entra' 'no entra tras activar'

echo '--- 7. Guard: no puedes desactivarte a ti mismo ---'
COOKIE_JAR="$superJar"
selfId=$(sql "SELECT id FROM users WHERE email='admin@gdc.gob.ve';")
http "$E2E_BASE/admin/usuarios/$selfId/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/usuarios/$selfId" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'name=Super Admin' \
  --data-urlencode 'email=admin@gdc.gob.ve' \
  --data-urlencode 'role=superadmin'
check 'grep -q "No puedes desactivarte a ti mismo" "$HTTP_BODY"' 'mensaje de guard visible' 'sin mensaje de guard'
a=$(sql "SELECT is_active::int FROM users WHERE id=$selfId;")
check '[ "$a" = 1 ]' "superadmin sigue activo (is_active=$a)" "se auto-desactivo: $a"

echo '--- 8. Listado muestra estado + ultima conexion ---'
http "$E2E_BASE/admin/usuarios"
check 'grep -q "e2e-activo@prueba.local" "$HTTP_BODY"' 'usuario E2E en listado' 'falta usuario en listado'
check 'grep -q "Activo" "$HTTP_BODY" && grep -q "Inactivo" "$HTTP_BODY"' 'badges Activo e Inactivo' 'badges incompletos'
check 'grep -qE "[0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}" "$HTTP_BODY"' 'ultima conexion con formato fecha' 'sin formato de fecha'

echo '--- 9. Cleanup: eliminar usuarios de prueba ---'
for u in 'e2e-activo@prueba.local' 'e2e-inactivo@prueba.local'; do
  uid=$(sql "SELECT id FROM users WHERE email='$u';")
  if [ -n "$uid" ]; then
    http "$E2E_BASE/admin/usuarios"
    token=$(require_token "$HTTP_BODY") || exit 1
    http "$E2E_BASE/admin/usuarios/$uid" --data-urlencode "_method=DELETE" --data-urlencode "_token=$token"
  fi
done
cnt=$(sql "SELECT COUNT(*) FROM users WHERE email IN ('e2e-activo@prueba.local','e2e-inactivo@prueba.local');")
check '[ "$cnt" = 0 ]' 'usuarios de prueba eliminados' "restan: $cnt"
real=$(sql 'SELECT COUNT(*) FROM users;')
check '[ "$real" -ge 3 ]' "usuarios reales intactos ($real)" "usuarios reales alterados: $real"

echo '--- 10. Smoke ---'
for p in / /noticias /gacetas; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

summary 'USUARIOS'
