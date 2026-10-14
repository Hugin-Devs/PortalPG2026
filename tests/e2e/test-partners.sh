#!/usr/bin/env bash
# test-partners.sh — port de test-partners.ps1 (upload/reemplazo/quitado de logo de entes)
# Estilo original: falla dura (throw -> exit 1); solo progreso por stdout.
E2E_SUITE=partners
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

fail() { echo "ERROR: $1" >&2; exit 1; }
tmp="$E2E_TMP/e2e-partners"
mkdir -p "$tmp"

# --- 1. PNGs de prueba (1x1) ---
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$tmp/logo1.png"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAIAAADZSiLoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAF0lEQVQImWNUNy5jYGBgYGBgYoABBAsAFukA1tgNRvwAAAAASUVORK5CYII=' | base64 -d > "$tmp/logo2.png"

# --- Limpieza previa: artefactos de corridas anteriores ---
while IFS=$'\t' read -r lg _; do
  [ -z "$lg" ] && continue
  [ -f "$E2E_PUB/$lg" ] && rm -f "$E2E_PUB/$lg"
  wp="${lg%.*}.webp"
  [ -f "$E2E_PUB/$wp" ] && rm -f "$E2E_PUB/$wp"
done < <(sqlt "SELECT logo, '' FROM partners WHERE name IN ('Ente Prueba Upload','Ente Prueba Upload v2') AND logo IS NOT NULL;")
sql "DELETE FROM partners WHERE name IN ('Ente Prueba Upload','Ente Prueba Upload v2');" >/dev/null
echo 'pre-limpieza: artefactos de corridas anteriores removidos'

# --- 2. Login ---
login 'admin@gdc.gob.ve'
grep -q 'Admin GDC' "$HTTP_BODY" || fail 'Login fallido'
echo 'LOGIN OK'

# --- 3. Crear ente con logo ---
http "$E2E_BASE/admin/entes/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes" \
  -F "_token=$token" \
  -F 'name=Ente Prueba Upload' \
  -F 'url=https://example.gob.ve' \
  -F 'order=1' \
  -F "logo=@$tmp/logo1.png;type=image/png"
grep -q 'Ente Prueba Upload' "$HTTP_BODY" && rMatch=true || rMatch=false
echo "POST create OK (respuesta contiene tabla? $rMatch)"

# --- 4. Verificar BD y archivo ---
id=$(sqlt "SELECT id, logo FROM partners WHERE name='Ente Prueba Upload';" | cut -f1)
logo=$(sqlt "SELECT id, logo FROM partners WHERE name='Ente Prueba Upload';" | cut -f2)
echo "BD: id=$id logo=$logo"
[ -n "$logo" ] || fail 'logo no guardado en BD'
[ -f "$E2E_PUB/$logo" ] || fail "archivo no existe: $E2E_PUB/$logo"
echo 'ARCHIVO OK'
logo1Path="$logo"
logo1Wp="${logo1Path%.*}.webp"
if [ -f "$E2E_PUB/$logo1Wp" ]; then echo 'WEBCONV OK (logo convertido a webp en upload)'; else fail 'sidecar webp no generado en upload de logo'; fi

# --- 5. Footer publico muestra el logo ---
http "$E2E_BASE/"
if grep -q "storage/$logo1Path" "$HTTP_BODY"; then echo 'FOOTER OK (logo visible en home)'; else echo 'FOOTER: logo NO encontrado en home'; fi

# --- 6. Reemplazar logo (upload nuevo) ---
http "$E2E_BASE/admin/entes/$id/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes/$id" \
  -F "_token=$token" \
  -F '_method=PUT' \
  -F 'name=Ente Prueba Upload' \
  -F 'url=https://example.gob.ve' \
  -F 'order=1' \
  -F 'is_active=1' \
  -F "logo=@$tmp/logo2.png;type=image/png"
row=$(sql "SELECT logo FROM partners WHERE id=$id;")
echo "BD tras replace: logo=$row"
[ "$row" = "$logo1Path" ] && fail 'el logo no cambio'
if [ -f "$E2E_PUB/$logo1Path" ]; then fail 'el archivo viejo NO se borro'; else echo 'archivo viejo eliminado OK'; fi
if [ -f "$E2E_PUB/$logo1Wp" ]; then fail 'el sidecar webp viejo NO se borro'; else echo 'sidecar webp viejo eliminado OK'; fi
[ -f "$E2E_PUB/$row" ] || fail 'archivo nuevo no existe'
echo 'archivo nuevo OK'
logo2Wp="${row%.*}.webp"
[ -f "$E2E_PUB/$logo2Wp" ] || fail 'sidecar webp del logo nuevo no existe'
echo 'sidecar webp nuevo OK'

# --- 7. Quitar logo con checkbox ---
curLogo=$(sql "SELECT logo FROM partners WHERE id=$id;")
http "$E2E_BASE/admin/entes/$id/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes/$id" \
  -F "_token=$token" \
  -F '_method=PUT' \
  -F 'name=Ente Prueba Upload' \
  -F 'url=https://example.gob.ve' \
  -F 'order=1' \
  -F 'is_active=1' \
  -F 'remove_logo=1'
isNull=$(sql "SELECT (logo IS NULL)::int FROM partners WHERE id=$id;")
echo "BD tras quitar: logo IS NULL=$isNull (esperado 1)"
[ "$isNull" = 1 ] || fail 'logo no se quito en BD'
echo 'logo=null OK'
[ -f "$E2E_PUB/$curLogo" ] && fail 'archivo no se borro'
echo 'archivo eliminado OK'
curWp="${curLogo%.*}.webp"
[ -f "$E2E_PUB/$curWp" ] && fail 'sidecar webp no se borro'
echo 'sidecar webp eliminado OK'

# --- 8. Editar sin tocar logo (debe conservar logo null + sin error) ---
http "$E2E_BASE/admin/entes/$id/editar"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes/$id" \
  -F "_token=$token" \
  -F '_method=PUT' \
  -F 'name=Ente Prueba Upload v2' \
  -F 'url=https://example.gob.ve' \
  -F 'order=2' \
  -F 'is_active=1'
name=$(sql "SELECT name FROM partners WHERE id=$id;")
echo "BD tras editar nombre: $name"

# --- 9. Validacion: subir archivo que no es imagen ---
echo 'hola' > "$tmp/fake.txt"
http "$E2E_BASE/admin/entes/crear"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes" \
  -F "_token=$token" \
  -F 'name=No Debe Crearse' \
  -F 'order=0' \
  -F "logo=@$tmp/fake.txt;type=text/plain"
cnt=$(sql "SELECT COUNT(*) FROM partners WHERE name='No Debe Crearse';")
echo "rechazo archivo no-imagen: filas=$cnt (esperado 0)"

# --- 10. Destruir ente de prueba ---
http "$E2E_BASE/admin/entes"
token=$(require_token "$HTTP_BODY") || exit 1
http "$E2E_BASE/admin/entes/$id" --data-urlencode '_method=DELETE' --data-urlencode "_token=$token"
cnt=$(sql "SELECT COUNT(*) FROM partners WHERE id=$id;")
echo "destroy: filas=$cnt (esperado 0)"

echo '=== RESUMEN: todas las pruebas completadas ==='
