#!/usr/bin/env bash
# test-social.sh — port de test-social.ps1 (redes sociales: seeders, form superadmin, validaciones, sidebar)
E2E_SUITE=social
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

# --- Snapshot: preservar valores sociales preexistentes del usuario ---
# Tabla auxiliar _e2e_social_snapshot (multi-linea seguro; reemplaza HEX/UNHEX).
social_snapshot_save
snapCount=$(sql 'SELECT COUNT(*) FROM _e2e_social_snapshot;')
echo "snapshot social: $snapCount claves preservadas (tabla _e2e_social_snapshot)"

put_social() { # $1..$n = clave=valor (las no indicadas viazan vacias)
  http "$E2E_BASE/admin/rrss"
  local t; t=$(require_token "$HTTP_BODY") || return 1
  local args=(--data-urlencode "_token=$t" --data-urlencode '_method=PUT')
  local pair k v
  for pair in "$@"; do
    k="${pair%%=*}"; v="${pair#*=}"
    args+=(--data-urlencode "$k=$v")
  done
  for k in facebook instagram youtube x threads telegram whatsapp; do
    case " $* " in
      *" $k="*) ;;
      *) args+=(--data-urlencode "$k=") ;;
    esac
  done
  http "$E2E_BASE/admin/rrss" "${args[@]}"
}

echo '--- 1. Seeders idempotentes ---'
( cd "$E2E_ROOT" && php artisan db:seed --class=PermissionSeeder --force >/dev/null 2>&1 )
( cd "$E2E_ROOT" && php artisan db:seed --class=SocialSettingSeeder --force >/dev/null 2>&1 )
c=$(sql 'SELECT COUNT(*) FROM permissions;')
check '[ "$c" = 40 ]' "40 permisos (got $c)" 'permisos != 40'
c=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%';")
check '[ "$c" = 7 ]' "7 settings sociales (got $c)" 'settings != 7'
c=$(sql 'SELECT COUNT(*) FROM role_permission;')
check '[ "$c" = 67 ]' "67 asignaciones 40+22+5 (got $c)" 'asignaciones != 67'
c=$(sql "SELECT slug FROM permissions WHERE slug='rrss.manage';")
check '[ "$c" = rrss.manage ]' 'slug rrss.manage existe' "falta rrss.manage (got $c)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug='settings.manage';")
check '[ "$c" = 1 ]' 'slug settings.manage existe (Configuracion)' "settings.manage count != 1 (got $c)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug='rrss.manage' AND \"group\" LIKE 'Gesti%Portal' AND \"module\"='Redes Sociales';")
check '[ "$c" = 1 ]' 'rrss.manage en grupo Gestion Portal, modulo Redes Sociales' 'grupo/modulo incorrecto'

echo '--- 2. Seeder no pisa valores editados ---'
sql "UPDATE settings SET \"value\"='https://www.facebook.com/editado' WHERE \"key\"='social_facebook';" >/dev/null
( cd "$E2E_ROOT" && php artisan db:seed --class=SocialSettingSeeder --force >/dev/null 2>&1 )
v=$(sql "SELECT \"value\" FROM settings WHERE \"key\"='social_facebook';")
check '[ "$v" = https://www.facebook.com/editado ]' 'valor editado conservado' "valor perdido (got $v)"

echo '--- 3. Superadmin: formulario ---'
login 'admin@gdc.gob.ve'
http "$E2E_BASE/admin/rrss"
check '[ "$HTTP_STATUS" = 200 ]' 'GET 200' "GET $HTTP_STATUS"
for f in facebook instagram youtube x threads telegram whatsapp; do
  check "grep -q \"name=\\\"$f\\\"\" \"\$HTTP_BODY\"" "input $f" "falta input $f"
done
check 'grep -q "Redes sociales" "$HTTP_BODY"' 'card Redes sociales' 'sin card'

echo '--- 4. Guardar 7 URLs validas ---'
put_social \
  'facebook=https://www.facebook.com/gdc' \
  'instagram=https://www.instagram.com/gdc' \
  'youtube=https://www.youtube.com/@gdc' \
  'x=https://x.com/gdcoficial' \
  'threads=https://www.threads.net/@gdc' \
  'telegram=https://t.me/gdc' \
  'whatsapp=https://wa.me/580000000000'
check '[ "$HTTP_STATUS" = 200 ]' 'PUT redirige y renderiza (200)' "PUT $HTTP_STATUS"
check 'grep -q "actualizadas correctamente" "$HTTP_BODY"' 'alerta de exito' 'sin alerta'
c=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
check '[ "$c" = 7 ]' "7 settings con valor (got $c)" 'valores no guardados'

echo '--- 5. Publico: iconos SOLO en footer ---'
http "$E2E_BASE/"
check '[ "$HTTP_STATUS" = 200 ]' 'home 200' "home $HTTP_STATUS"
n=$(grep -o 'fa-brands fa-' "$HTTP_BODY" | wc -l)
check '[ "$n" = 7 ]' "7 iconos (7 redes x solo footer) (got $n)" "iconos != 7 (header los incluye?)"
check 'grep -q "fa-brands fa-x-twitter" "$HTTP_BODY"' 'icono X' 'sin icono X'
check 'grep -q "fa-brands fa-threads" "$HTTP_BODY"' 'icono Threads' 'sin icono Threads'
check 'grep -q "rel=\"noopener noreferrer\"" "$HTTP_BODY"' 'rel noopener' 'sin rel noopener'
check 'grep -q "href=\"https://wa.me/580000000000\"" "$HTTP_BODY"' 'href whatsapp' 'sin href whatsapp'

echo '--- 6. Vaciar 2 redes: se ocultan ---'
put_social \
  'facebook=https://www.facebook.com/gdc' \
  'instagram=https://www.instagram.com/gdc' \
  'youtube=https://www.youtube.com/@gdc' \
  'threads=https://www.threads.net/@gdc' \
  'whatsapp=https://wa.me/580000000000'
check '[ "$HTTP_STATUS" = 200 ]' 'PUT parcial 200' "PUT $HTTP_STATUS"
c=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
check '[ "$c" = 5 ]' "5 settings con valor (got $c)" 'conteo != 5'
http "$E2E_BASE/"
n=$(grep -o 'fa-brands fa-' "$HTTP_BODY" | wc -l)
check '[ "$n" = 5 ]' "5 iconos (5 redes, solo footer) (got $n)" "iconos != 5"
check '! grep -q "fa-brands fa-x-twitter" "$HTTP_BODY"' 'X oculto' 'X sigue visible'

echo '--- 7. URL invalida rechazada (BD intacta) ---'
before=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
put_social 'facebook=javascript:alert(1)'
check '[ "$HTTP_STATUS" = 200 ]' 'redirige con errores (200)' "status $HTTP_STATUS"
check 'grep -q "is-invalid" "$HTTP_BODY" || grep -q "invalid-feedback" "$HTTP_BODY"' 'campo en error' 'sin validacion visible'
after=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
check '[ "$before" = "$after" ]' 'BD no cambio' "BD cambio ($before -> $after)"
v=$(sql "SELECT \"value\" FROM settings WHERE \"key\"='social_facebook';")
check '[ "$v" = https://www.facebook.com/gdc ]' 'facebook conservado' "facebook alterado (got $v)"

echo '--- 8. Varias URLs de la misma red (una por linea) ---'
put_social \
  "facebook=https://www.facebook.com/gdc
https://www.facebook.com/distrito" \
  'instagram=https://www.instagram.com/gdc' \
  'youtube=https://www.youtube.com/@gdc' \
  'threads=https://www.threads.net/@gdc' \
  'whatsapp=https://wa.me/580000000000'
check '[ "$HTTP_STATUS" = 200 ]' 'PUT multi-linea 200' "PUT $HTTP_STATUS"
check 'grep -q "actualizadas correctamente" "$HTTP_BODY"' 'alerta de exito multi' 'sin alerta'
newlines=$(sql "SELECT length(\"value\") - length(replace(\"value\", E'\n', '')) FROM settings WHERE \"key\"='social_facebook';")
check '[ "$newlines" = 1 ]' "facebook guardado con 2 lineas / 1 salto (got $newlines)" "saltos != 1 (got $newlines)"
http "$E2E_BASE/"
nFb=$(grep -o 'fa-brands fa-facebook-f' "$HTTP_BODY" | wc -l)
check '[ "$nFb" = 2 ]' "2 iconos de Facebook (got $nFb)" "iconos FB != 2 (got $nFb)"
nTotal=$(grep -o 'fa-brands fa-' "$HTTP_BODY" | wc -l)
check '[ "$nTotal" = 6 ]' "6 iconos totales: FB x2 + 4 redes (got $nTotal)" "total != 6 (got $nTotal)"
check 'grep -qF "title=\"Facebook — https://www.facebook.com/gdc\"" "$HTTP_BODY"' 'tooltip distingue cada cuenta' 'sin tooltip con URL'

echo '--- 9. Linea invalida en medio de varias (BD intacta) ---'
before=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
put_social "facebook=https://www.facebook.com/gdc
no-es-url"
check '[ "$HTTP_STATUS" = 200 ]' 'redirige con errores (200)' "status $HTTP_STATUS"
check 'grep -q "is-invalid" "$HTTP_BODY" || grep -q "invalid-feedback" "$HTTP_BODY"' 'campo en error' 'sin validacion visible'
check 'grep -q "línea 2" "$HTTP_BODY"' 'mensaje indica la linea 2' 'sin numero de linea'
after=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\" LIKE 'social_%' AND \"value\" != '';")
check '[ "$before" = "$after" ]' 'BD no cambio' "BD cambio ($before -> $after)"
newlines=$(sql "SELECT length(\"value\") - length(replace(\"value\", E'\n', '')) FROM settings WHERE \"key\"='social_facebook';")
check '[ "$newlines" = 1 ]' "facebook multi-linea conservado (got $newlines)" "saltos != 1 (got $newlines)"

echo '--- 10. Permisos y sidebar ---'
login 'admin2@gdc.gob.ve'
http "$E2E_BASE/admin/rrss"
check '[ "$HTTP_STATUS" = 403 ]' 'admin2 -> 403' "admin2 obtuvo $HTTP_STATUS (esperado 403)"
http "$E2E_BASE/admin"
check '! grep -q "/admin/rrss" "$HTTP_BODY"' 'admin sin item sidebar' 'admin ve Redes Sociales'
login 'editor@gdc.gob.ve'
http "$E2E_BASE/admin/rrss"
check '[ "$HTTP_STATUS" = 403 ]' 'editor -> 403' "editor obtuvo $HTTP_STATUS (esperado 403)"
login 'admin@gdc.gob.ve'
http "$E2E_BASE/admin"
check 'grep -q "/admin/rrss" "$HTTP_BODY"' 'superadmin ve item sidebar' 'superadmin sin item'

echo '--- 11. Smoke ---'
for p in / /noticias /contacto; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

echo '--- 12. Restaurar valores previos del usuario ---'
social_snapshot_restore
mismatch=$(social_snapshot_mismatches)
check '[ "$mismatch" = 0 ]' 'valores sociales previos restaurados' 'NO se restauraron los valores previos'
social_snapshot_drop

summary 'REDES'
