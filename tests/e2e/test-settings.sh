#!/usr/bin/env bash
# test-settings.sh — port de test-settings.ps1 (configuracion general: identidad, enlaces, logo, permisos)
E2E_SUITE=settings
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

tmp="$E2E_TMP/e2e-settings"
mkdir -p "$tmp"

declare -A SNAP=()
declare -A SNAP_EXIST=()
SNAP_KEYS=(site_name site_logo nav_funcionarios_url nav_jubilados_url)
q=$(printf "'%s'," "${SNAP_KEYS[@]}"); q="${q%,}"
while IFS=$'\t' read -r k v; do
  [ -z "$k" ] && continue
  SNAP["$k"]="$v"; SNAP_EXIST["$k"]=1
done < <(sqlt "SELECT \"key\", \"value\" FROM settings WHERE \"key\" IN ($q);")
echo "snapshot settings: ${#SNAP_EXIST[@]}/4 claves preservadas"

snapLogoPath=''; hadLogoFile=0; hadLogoWebp=0
if [ "${SNAP_EXIST[site_logo]:-}" = 1 ] && [ -n "${SNAP[site_logo]:-}" ]; then
  src="$E2E_PUB/${SNAP[site_logo]}"
  if [ -f "$src" ]; then cp -f "$src" "$tmp/e2e-logo-orig"; snapLogoPath="$src"; hadLogoFile=1; fi
  if [ -f "${src%.*}.webp" ]; then cp -f "${src%.*}.webp" "$tmp/e2e-logo-orig.webp"; hadLogoWebp=1; fi
fi

put_settings() {
  http "$E2E_BASE/admin/configuracion"
  local t; t=$(require_token "$HTTP_BODY") || return 1
  http "$E2E_BASE/admin/configuracion" --data-urlencode "_token=$t" --data-urlencode '_method=PUT' "$@"
}

echo '--- 1. Rutas y permisos ---'
COOKIE_JAR=''
http "$E2E_BASE/admin/configuracion"
case "$HTTP_FINAL" in */admin/login) toLogin=1 ;; *) toLogin=0 ;; esac
check '[ "$toLogin" = 1 ]' 'sin sesion -> login' "no redirige a login ($HTTP_FINAL)"
check 'grep -q "Acceso administrativo" "$HTTP_BODY"' 'renderiza formulario de login' 'sin formulario de login'
login 'admin@gdc.gob.ve'
superJar="$COOKIE_JAR"
http "$E2E_BASE/admin/configuracion"
check '[ "$HTTP_STATUS" = 200 ]' 'superadmin GET 200' "GET $HTTP_STATUS"
for f in site_name logo nav_funcionarios_url nav_jubilados_url; do
  check "grep -q \"name=\\\"$f\\\"\" \"\$HTTP_BODY\"" "input $f" "falta input $f"
done
check 'grep -q "<h1>Identidad / Enlaces" "$HTTP_BODY"' 'titulo Identidad / Enlaces' 'sin titulo'
check 'grep -q "Identidad del sitio" "$HTTP_BODY"' 'card Identidad' 'sin card Identidad'
check 'grep -q "Enlaces de" "$HTTP_BODY"' 'card Enlaces' 'sin card Enlaces'
for pair in 'admin2@gdc.gob.ve admin' 'editor@gdc.gob.ve editor'; do
  set -- $pair
  login "$1"
  http "$E2E_BASE/admin/configuracion"
  check '[ "$HTTP_STATUS" = 403 ]' "$2 -> 403" "$2 -> $HTTP_STATUS"
done
COOKIE_JAR="$superJar"
http "$E2E_BASE/admin"
check 'grep -q "/admin/configuracion" "$HTTP_BODY"' 'superadmin ve item sidebar' 'superadmin sin item sidebar'
login 'admin2@gdc.gob.ve'
http "$E2E_BASE/admin"
check '! grep -q "/admin/configuracion" "$HTTP_BODY"' 'admin NO ve item sidebar' 'admin ve item Configuracion'
COOKIE_JAR="$superJar"

echo '--- 2. Guardar nombre y enlaces (URLs absolutas) ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=https://intranet.example.gob.ve/' \
  --data-urlencode 'nav_jubilados_url=https://jubilados.example.gob.ve/'
check '[ "$HTTP_STATUS" = 200 ]' 'PUT 200' "PUT $HTTP_STATUS"
check 'grep -q "actualizada correctamente" "$HTTP_BODY"' 'alerta de exito' 'sin alerta de exito'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'site_name'"'"';')
check '[ "$v" = "Portal GDC E2E" ]' "site_name guardado ($v)" 'site_name no guardado'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "https://intranet.example.gob.ve/" ]' "nav_funcionarios guardado ($v)" 'nav_funcionarios no guardado'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_jubilados_url'"'"';')
check '[ "$v" = "https://jubilados.example.gob.ve/" ]' 'nav_jubilados guardado' "nav_jubilados no guardado ($v)"
http "$E2E_BASE/"
check 'grep -qF "<title>Inicio - Portal GDC E2E</title>" "$HTTP_BODY"' 'titulo home usa site_name' 'titulo home no usa site_name'
check 'grep -qF "href=\"https://intranet.example.gob.ve/\"" "$HTTP_BODY"' 'navbar muestra Funcionarios' 'navbar sin Funcionarios'
check 'grep -qF "href=\"https://jubilados.example.gob.ve/\">Jubilados</a>" "$HTTP_BODY"' 'navbar muestra Jubilados' 'navbar sin Jubilados'
check 'grep -qF "target=\"_blank\" rel=\"noopener\"" "$HTTP_BODY"' 'enlaces target blank' 'sin target blank'
http "$E2E_BASE/admin/login"
check 'grep -qF "<title>Admin - Portal GDC E2E</title>" "$HTTP_BODY"' 'titulo login usa site_name' 'titulo login no usa site_name'
http "$E2E_BASE/admin/configuracion"
check 'grep -qF "<title>Admin - Portal GDC E2E</title>" "$HTTP_BODY"' 'titulo panel usa site_name' 'titulo panel no usa site_name'

echo '--- 3. Campo vacio oculta el item del navbar ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=https://intranet.example.gob.ve/' \
  --data-urlencode 'nav_jubilados_url='
check '[ "$HTTP_STATUS" = 200 ]' 'PUT 200' "PUT $HTTP_STATUS"
v=$(sql 'SELECT COALESCE("value",'"''"') FROM settings WHERE "key"='"'"'nav_jubilados_url'"'"';')
check '[ -z "$v" ]' 'nav_jubilados vacio guardado' "nav_jubilados no vacio ($v)"
http "$E2E_BASE/"
check 'grep -qF "href=\"https://intranet.example.gob.ve/\">Funcionarios Activos</a>" "$HTTP_BODY"' 'Funcionarios visible' 'Funcionarios oculto'
check '! grep -qF ">Jubilados</a>" "$HTTP_BODY"' 'Jubilados oculto' 'Jubilados aun visible'
check 'grep -q "Intranet GDC" "$HTTP_BODY"' 'dropdown Intranet GDC presente' 'sin dropdown Intranet GDC'

echo '--- 4. Validacion: URL invalida rechazada ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=javascript:alert(1)' \
  --data-urlencode 'nav_jubilados_url='
check '[ "$HTTP_STATUS" = 200 ]' 'redirige con errores (200)' "status $HTTP_STATUS"
check 'grep -q "is-invalid" "$HTTP_BODY" || grep -q "invalid-feedback" "$HTTP_BODY"' 'campo en error' 'sin validacion visible'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "https://intranet.example.gob.ve/" ]' "BD intacta ($v)" 'BD alterada'

echo '--- 5. Validacion: ruta sin "/" rechazada ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=documentos/algo.pdf' \
  --data-urlencode 'nav_jubilados_url='
check 'grep -q "debe comenzar con" "$HTTP_BODY"' 'mensaje de error' 'sin mensaje de error'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "https://intranet.example.gob.ve/" ]' "BD intacta ($v)" 'BD alterada'

echo '--- 6. Validacion: ruta inexistente rechazada ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=/documentos/NOEXISTE-E2E.pdf' \
  --data-urlencode 'nav_jubilados_url='
check 'grep -q "El archivo o directorio no existe" "$HTTP_BODY"' 'mensaje archivo inexistente' 'sin mensaje de archivo inexistente'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "https://intranet.example.gob.ve/" ]' "BD intacta ($v)" 'BD alterada'

echo '--- 7. Validacion: ruta con ".." rechazada ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=/images/../.env' \
  --data-urlencode 'nav_jubilados_url='
check 'grep -q "no puede contener" "$HTTP_BODY"' 'mensaje de traversal' 'sin mensaje de traversal'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "https://intranet.example.gob.ve/" ]' "BD intacta ($v)" 'BD alterada'

echo '--- 8. Ruta valida del servidor aceptada ---'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=/images/logo.png' \
  --data-urlencode 'nav_jubilados_url='
check 'grep -q "actualizada correctamente" "$HTTP_BODY"' 'guardado OK' 'no guardo'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'nav_funcionarios_url'"'"';')
check '[ "$v" = "/images/logo.png" ]' "nav_funcionarios=/images/logo.png ($v)" 'valor no guardado'
http "$E2E_BASE/"
check 'grep -qF "href=\"/images/logo.png\">Funcionarios Activos</a>" "$HTTP_BODY"' 'navbar con ruta relativa' 'navbar sin ruta relativa'

echo '--- 9. site_name obligatorio ---'
put_settings \
  --data-urlencode 'site_name=' \
  --data-urlencode 'nav_funcionarios_url=/images/logo.png' \
  --data-urlencode 'nav_jubilados_url='
check 'grep -q "is-invalid" "$HTTP_BODY" || grep -q "invalid-feedback" "$HTTP_BODY"' 'campo site_name en error' 'sin validacion en site_name'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'site_name'"'"';')
check '[ "$v" = "Portal GDC E2E" ]' "site_name conservado ($v)" 'site_name alterado'

echo '--- 10. Logo: subir, previsualizar, quitar ---'
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/e2e-logo.png"
http "$E2E_BASE/admin/configuracion"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/configuracion" \
  --form-string "_token=$token" \
  --form-string '_method=PUT' \
  --form-string 'site_name=Portal GDC E2E' \
  --form-string 'nav_funcionarios_url=/images/logo.png' \
  --form-string 'nav_jubilados_url=' \
  -F "logo=@$tmp/e2e-logo.png;type=image/png"
check '[ "$HTTP_STATUS" = 200 ]' 'upload 200' "upload $HTTP_STATUS"
check 'grep -q "actualizada correctamente" "$HTTP_BODY"' 'upload guardado' 'sin alerta de exito'
v=$(sql 'SELECT "value" FROM settings WHERE "key"='"'"'site_logo'"'"';')
check '[ "$v" = "settings/logo.png" ]' "site_logo=settings/logo.png ($v)" 'site_logo mal guardado'
check '[ -f "$E2E_PUB/settings/logo.png" ]' 'archivo logo.png en disco' 'archivo no existe en disco'
check '[ -f "$E2E_PUB/settings/logo.webp" ]' 'sidecar logo.webp generado en upload' 'falta sidecar logo.webp'
http "$E2E_BASE/storage/settings/logo.png"
check '[ "$HTTP_STATUS" = 200 ]' 'GET /storage/settings/logo.png -> 200' "GET $HTTP_STATUS"
ct=$(curl -sI "$E2E_BASE/storage/settings/logo.png" | grep -i '^content-type:')
case "$ct" in *[Ii]mage*) ctOk=1 ;; *) ctOk=0 ;; esac
check '[ "$ctOk" = 1 ]' "content-type image ($ct)" "content-type no image: $ct"
http "$E2E_BASE/storage/settings/logo.webp"
check '[ "$HTTP_STATUS" = 200 ]' 'GET /storage/settings/logo.webp -> 200' "GET webp $HTTP_STATUS"
http "$E2E_BASE/admin/configuracion"
check 'grep -q "storage/settings/logo.png" "$HTTP_BODY"' 'panel previsualiza logo nuevo' 'panel no previsualiza logo'
check 'grep -q "Quitar logo actual" "$HTTP_BODY"' 'checkbox quitar logo' 'sin checkbox quitar logo'
put_settings \
  --data-urlencode 'site_name=Portal GDC E2E' \
  --data-urlencode 'nav_funcionarios_url=/images/logo.png' \
  --data-urlencode 'nav_jubilados_url=' \
  --data-urlencode 'remove_logo=1'
check '[ "$HTTP_STATUS" = 200 ]' 'PUT remove 200' "PUT $HTTP_STATUS"
v=$(sql 'SELECT COALESCE("value",'"''"') FROM settings WHERE "key"='"'"'site_logo'"'"';')
check '[ -z "$v" ]' 'site_logo vaciado' "site_logo no vaciado ($v)"
check '[ ! -f "$E2E_PUB/settings/logo.png" ]' 'archivo logo.png borrado del disco' 'archivo logo.png sigue en disco'
check '[ ! -f "$E2E_PUB/settings/logo.webp" ]' 'sidecar logo.webp borrado del disco' 'sidecar logo.webp sigue en disco'
http "$E2E_BASE/admin/configuracion"
check 'grep -q "Logo por defecto" "$HTTP_BODY"' 'panel vuelve a logo por defecto' 'sin indicador de logo por defecto'
check '! grep -q "Quitar logo actual" "$HTTP_BODY"' 'checkbox quitar logo oculto' 'checkbox quitar logo aun visible'

echo '--- 11. Titulos y smoke ---'
http "$E2E_BASE/"
check 'grep -qF "<title>Inicio - Portal GDC E2E</title>" "$HTTP_BODY"' 'titulo home usa site_name' 'titulo home malo'
http "$E2E_BASE/NOEXISTE-E2E-XYZ"
check '[ "$HTTP_STATUS" = 404 ]' '404 devuelto' "status $HTTP_STATUS"
check 'grep -qF "<title>Error 404 - Portal GDC E2E</title>" "$HTTP_BODY"' 'titulo 404 usa site_name' "titulo 404 no usa site_name"
for p in / /noticias /contacto /gacetas /entes; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p -> $HTTP_STATUS"
done

echo '--- 12. Permisos en BD ---'
c=$(sql 'SELECT COUNT(*) FROM permissions;')
check '[ "$c" = 41 ]' "41 permisos ($c)" "permisos != 41 ($c)"
c=$(sql 'SELECT COUNT(*) FROM role_permission;')
check '[ "$c" = 68 ]' "68 asignaciones 41+22+5 ($c)" "asignaciones != 68 ($c)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug='settings.manage' AND \"group\" LIKE 'Configuraci%Portal';")
check '[ "$c" = 1 ]' "settings.manage en grupo Configuracion Portal ($c)" "settings.manage mal ubicado ($c)"
c=$(sql 'SELECT COUNT(DISTINCT "group") FROM permissions;')
check '[ "$c" = 3 ]' "3 grupos de permisos = secciones sidebar ($c)" "grupos != 3 ($c)"
c=$(sql 'SELECT COUNT(DISTINCT module) FROM permissions;')
check '[ "$c" = 15 ]' "15 modulos = items sidebar ($c)" "modulos != 15 ($c)"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE module IS NULL OR module='';")
check '[ "$c" = 0 ]' "0 permisos sin modulo ($c)" "permisos sin modulo: $c"
c=$(sql "SELECT COUNT(*) FROM permissions WHERE slug='settings.manage' AND name='Editar identidad y enlaces';")
check '[ "$c" = 1 ]' "permiso settings.manage renombrado ($c)" 'settings.manage nombre viejo'
http "$E2E_BASE/admin/roles"
check '[ "$HTTP_STATUS" = 200 ]' '/admin/roles 200' "roles $HTTP_STATUS"
n=$(grep -o 'perm-module' "$HTTP_BODY" | wc -l | tr -d ' ')
check '[ "$n" = 15 ]' "vista: 15 cabeceras de modulo ($n)" "vista: cabeceras modulo = $n"
check 'grep -q "Editar identidad y enlaces" "$HTTP_BODY"' 'vista: permiso renombrado visible' 'vista sin permiso renombrado'
roles=$(sql "SELECT rp.role FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug='settings.manage';" | paste -sd, -)
check '[ "$roles" = superadmin ]' "solo superadmin ($roles)" "roles inesperados: $roles"

echo '--- 13. Restaurar snapshot ---'
for k in "${SNAP_KEYS[@]}"; do
  if [ "${SNAP_EXIST[$k]:-}" = 1 ]; then
    val="${SNAP[$k]//\'/\'\'}"
    sql "UPDATE settings SET \"value\"='$val' WHERE \"key\"='$k';" >/dev/null
  else
    sql "DELETE FROM settings WHERE \"key\"='$k';" >/dev/null
  fi
done
if [ "$hadLogoFile" = 1 ]; then
  dst="$E2E_PUB/${SNAP[site_logo]}"
  mkdir -p "$(dirname "$dst")"
  cp -f "$tmp/e2e-logo-orig" "$dst"
  [ "$hadLogoWebp" = 1 ] && cp -f "$tmp/e2e-logo-orig.webp" "${dst%.*}.webp"
else
  rm -f "$E2E_PUB"/settings/logo.* 2>/dev/null
fi
restored=1
for k in "${SNAP_KEYS[@]}"; do
  if [ "${SNAP_EXIST[$k]:-}" = 1 ]; then
    actual=$(sql "SELECT COALESCE(\"value\",'') FROM settings WHERE \"key\"='$k';")
    [ "$actual" != "${SNAP[$k]}" ] && restored=0
  else
    rows=$(sql "SELECT COUNT(*) FROM settings WHERE \"key\"='$k';")
    [ "$rows" != 0 ] && restored=0
  fi
done
check '[ "$restored" = 1 ]' 'configuracion preexistente restaurada' 'NO se restauro la configuracion previa'
http "$E2E_BASE/"
check '[ "$HTTP_STATUS" = 200 ]' 'home 200 tras restaurar' "home $HTTP_STATUS"

summary 'CONFIGURACION GENERAL'
