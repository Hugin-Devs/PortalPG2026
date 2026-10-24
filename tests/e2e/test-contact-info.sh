#!/usr/bin/env bash
# test-contact-info.sh — port de test-contact-info.ps1 (config de contacto: form, put, publico, permisos)
# Snapshot de settings contact_* en tabla _e2e_contact_snapshot (multi-linea seguro).
E2E_SUITE=contact-info
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo '--- 0. Estado inicial ---'
contact_snapshot_save
contactSnapshotCount=$(contact_snapshot_count)
echo "settings contact_* previos: $contactSnapshotCount claves preservadas"

echo '--- 0b. Seeder idempotente (2a corrida) ---'
( cd "$E2E_ROOT" && php artisan db:seed --class=PermissionSeeder --no-interaction >/dev/null 2>&1 )
cnt=$(sql 'SELECT COUNT(*) FROM permissions;')
check '[ "$cnt" = 41 ]' "seeder x2: 41 permisos ($cnt)" "permisos != 41: $cnt"

echo '--- 1. Login superadmin + GET /admin/contacto ---'
login 'admin@gdc.gob.ve'
superJar="$COOKIE_JAR"
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'LOGIN superadmin' 'login fallido'
http "$E2E_BASE/admin/contacto"
check '[ "$HTTP_STATUS" = 200 ]' 'GET /admin/contacto -> 200' "status $HTTP_STATUS"
check 'grep -q "name=\"address\"" "$HTTP_BODY"' 'campo address' 'falta address'
check 'grep -q "name=\"phone\"" "$HTTP_BODY"' 'campo phone' 'falta phone'
check 'grep -q "name=\"email\"" "$HTTP_BODY"' 'campo email' 'falta email'
check 'grep -q "name=\"hours\"" "$HTTP_BODY"' 'campo hours' 'falta hours'
check 'grep -q "name=\"map_url\"" "$HTTP_BODY"' 'campo map_url' 'falta map_url'
check 'grep -qE "action=\"[^\"]*admin/contacto\"" "$HTTP_BODY"' 'action /admin/contacto' 'falta action'
check 'grep -q ">Contacto</a>" "$HTTP_BODY"' 'item sidebar Contacto visible' 'sidebar sin Contacto'
token=$(require_token "$HTTP_BODY") || exit 1

echo '--- 2. PUT con valores nuevos ---'
mapTest='https://www.google.com/maps/embed?pb=TTEST123'
http "$E2E_BASE/admin/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'address=Edificio Toromaima, Caracas' \
  --data-urlencode 'phone=+58 212-555-1234' \
  --data-urlencode 'email=info@gdc.gob.ve' \
  --data-urlencode 'hours=Lunes a viernes: 7:00 AM - 5:00 PM' \
  --data-urlencode "map_url=$mapTest"
check '[[ "$HTTP_FINAL" == */admin/contacto ]]' 'redirect al form con exito' "no redirigio: $HTTP_FINAL"
rows=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'contact_%';")
check '[ "$rows" = 5 ]' "5 settings creados ($rows)" "settings != 5: $rows"
v=$(sql "SELECT \"value\" FROM settings WHERE \"key\"='contact_address';")
check '[ "$v" = "Edificio Toromaima, Caracas" ]' 'address guardado' 'address mal'
v=$(sql "SELECT \"value\" FROM settings WHERE \"key\"='contact_map_url';")
check "[ \"\$v\" = \"$mapTest\" ]" 'map_url guardado' 'map_url mal'

echo '--- 3. Las 3 paginas publicas muestran los valores nuevos ---'
for p in / /contacto /sobre-nosotros; do
  http "$E2E_BASE$p"
  okNew=0; okOld=0
  grep -q 'Edificio Toromaima, Caracas' "$HTTP_BODY" && grep -qE 'info@gdc\.gob\.ve' "$HTTP_BODY" && grep -q 'TTEST123' "$HTTP_BODY" && okNew=1
  ! grep -qE 'Av\. Principal, Distrito Capital' "$HTTP_BODY" && ! grep -qE 'contacto@gdc\.gob\.ve' "$HTTP_BODY" && okOld=1
  check '[ "$HTTP_STATUS" = 200 ] && [ "$okNew" = 1 ] && [ "$okOld" = 1 ]' \
    "$p muestra valores nuevos (sin defaults viejos)" \
    "$p no actualizo (200=$HTTP_STATUS, nuevos=$okNew, viejos ausentes=$okOld)"
done

echo '--- 4. Validaciones: email y url invalidos ---'
http "$E2E_BASE/admin/contacto"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'address=Edificio Toromaima, Caracas' \
  --data-urlencode 'phone=x' \
  --data-urlencode 'email=no-es-email' \
  --data-urlencode 'hours=h' \
  --data-urlencode 'map_url=esto-no-es-url'
check '[[ "$HTTP_FINAL" == */admin/contacto ]]' "rechazado (volvio al form: $HTTP_FINAL)" "NO rechazado: $HTTP_FINAL"
v=$(sql "SELECT \"value\" FROM settings WHERE \"key\"='contact_email';")
check '[ "$v" = info@gdc.gob.ve ]' 'email no se piso con invalido' 'email se cambio'

echo '--- 5. Valor vacio -> fallback a default en publico ---'
http "$E2E_BASE/admin/contacto"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/contacto" \
  --data-urlencode "_token=$token" \
  --data-urlencode '_method=PUT' \
  --data-urlencode 'address=' \
  --data-urlencode 'phone=+58 212-555-1234' \
  --data-urlencode 'email=info@gdc.gob.ve' \
  --data-urlencode 'hours=Lunes a viernes: 7:00 AM - 5:00 PM' \
  --data-urlencode "map_url=$mapTest"
http "$E2E_BASE/contacto"
check 'grep -qE "Av\. Principal, Distrito Capital" "$HTTP_BODY"' 'address vacio muestra default' 'no muestra default'

echo '--- 6. Permisos: admin2 ve, editor no ---'
login 'admin2@gdc.gob.ve'
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'LOGIN admin2' 'login admin2 fallido'
http "$E2E_BASE/admin/contacto"
check '[ "$HTTP_STATUS" = 200 ]' 'admin (rol) -> 200 en /admin/contacto' "admin: $HTTP_STATUS"
check 'grep -q ">Contacto</a>" "$HTTP_BODY"' 'admin ve item sidebar' 'admin no ve item'
login 'editor@gdc.gob.ve'
check 'grep -q "Admin GDC" "$HTTP_BODY"' 'LOGIN editor' 'login editor fallido'
http "$E2E_BASE/admin/contacto"
check '[ "$HTTP_STATUS" = 403 ]' 'editor -> 403 en /admin/contacto' "editor obtuvo $HTTP_STATUS (esperado 403)"
http "$E2E_BASE/admin"
check '! grep -q ">Contacto</a>" "$HTTP_BODY"' 'editor NO ve item sidebar' 'editor SI ve item'

echo '--- 7. Smoke publico ---'
for p in / /contacto /sobre-nosotros /noticias; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

echo '--- 8. Cleanup: restaurar settings previos (nunca pisar datos reales) ---'
contact_snapshot_restore
mismatch=$(contact_snapshot_mismatches)
check '[ "$mismatch" = 0 ]' 'settings de contacto previos restaurados' 'NO se restauraron los settings previos'
http "$E2E_BASE/contacto"
if [ "$contactSnapshotCount" = 0 ]; then
  check 'grep -qE "Av\. Principal, Distrito Capital" "$HTTP_BODY" && grep -qE "contacto@gdc\.gob\.ve" "$HTTP_BODY"' \
    'publico vuelve a defaults (no habia datos previos)' 'no volvio a defaults'
else
  addr=$(contact_snapshot_get 'contact_address')
  if [ -n "$addr" ]; then
    check "grep -q \"$(re_escape "$addr")\" \"\$HTTP_BODY\"" 'publico muestra el valor previo restaurado' 'no restauro el valor previo en publico'
  else
    ok 'address previo vacio: publico con default (correcto)'
  fi
fi
contact_snapshot_drop

summary 'CONTACTO INFO'
