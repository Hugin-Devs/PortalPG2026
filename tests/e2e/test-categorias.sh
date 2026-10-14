#!/usr/bin/env bash
# test-categorias.sh — port de test-categorias.ps1 (sidebar/pills de categorias, footer, smoke)
E2E_SUITE=categorias
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

echo '--- 1. /noticias: sidebar (desktop) + pills (movil) ---'
http "$E2E_BASE/noticias"
check '[ "$HTTP_STATUS" = 200 ]' '/noticias 200' "status $HTTP_STATUS"
check 'grep -q "col-lg-3 d-none d-lg-block" "$HTTP_BODY"' 'sidebar desktop presente' 'falta sidebar'
check 'grep -q "d-lg-none" "$HTTP_BODY"' 'pills movil presentes' 'falta pills movil'
check 'grep -q "Categor" "$HTTP_BODY"' 'titulo Categorias en sidebar' 'falta titulo Categorias'
check 'grep -qE "action=\"[^\"]*buscar\"" "$HTTP_BODY"' 'buscador en sidebar' 'falta buscador'
check 'grep -q "class=\"badge bg-light text-dark border\"" "$HTTP_BODY"' 'contadores en sidebar' 'falta contadores'
check 'grep -q "rounded-pill" "$HTTP_BODY"' 'pills con rounded-pill' 'pills mal formadas'

echo '--- 2. Categorias activas con publicaciones aparecen ---'
slugs=$(sqlt "SELECT c.slug FROM categories c WHERE c.is_active = true AND EXISTS (SELECT 1 FROM posts p WHERE p.category_id = c.id AND p.status = 'published' AND p.deleted_at IS NULL AND (p.published_at IS NULL OR p.published_at <= now())) ORDER BY c.\"order\", c.name;")
found=0
while IFS= read -r s; do
  [ -z "$s" ] && continue
  n=$(grep -o "categoria/$s" "$HTTP_BODY" | wc -l)
  if [ "$n" -ge 2 ]; then found=$((found+1)); else bad "categoria '$s' con $n enlaces (esperado >=2 pills+sidebar)"; fi
done <<< "$slugs"
check '[ "$found" -gt 0 ]' "$found categorias enlazadas en pills+sidebar" 'ninguna categoria enlazada'
nTodas=$(grep -o '>Todas' "$HTTP_BODY" | wc -l)
check '[ "$nTodas" -ge 2 ]' 'enlace Todas en pills y sidebar' 'falta enlace Todas'

echo '--- 3. /categoria/{slug}: nav coherente + estado activo ---'
first=$(printf '%s\n' "$slugs" | head -1)
if [ -n "$first" ]; then
  http "$E2E_BASE/categoria/$first"
  check '[ "$HTTP_STATUS" = 200 ]' "/categoria/$first 200" "status $HTTP_STATUS"
  check 'grep -q "col-lg-3 d-none d-lg-block" "$HTTP_BODY"' 'sidebar en vista de categoria' 'falta sidebar'
  check 'grep -q "d-lg-none" "$HTTP_BODY"' 'pills en vista de categoria' 'falta pills'
  check 'grep -q "text-primary fw-bold" "$HTTP_BODY"' 'categoria activa resaltada' 'sin estado activo'
  check 'grep -q "breadcrumb" "$HTTP_BODY"' 'breadcrumb presente' 'falta breadcrumb'
else
  ok 'sin categorias con publicaciones: se omite vista de categoria'
fi

echo '--- 4. Footer: Gacetas + orden del navbar ---'
http "$E2E_BASE/noticias"
idx_of() { grep -boF "$1" "$HTTP_BODY" | head -1 | cut -d: -f1; }
iInicio=$(idx_of 'footer-text">Inicio')
iNoticias=$(idx_of 'footer-text">Noticias')
iEntes=$(idx_of 'footer-text">Entes')
iSobre=$(idx_of 'footer-text">Sobre Nosotros')
iGacetas=$(idx_of 'footer-text">Gacetas Oficiales')
iContacto=$(idx_of 'footer-text">Contacto')
[ -z "$iInicio" ] && iInicio=-1; [ -z "$iNoticias" ] && iNoticias=-1
[ -z "$iEntes" ] && iEntes=-1; [ -z "$iSobre" ] && iSobre=-1
[ -z "$iGacetas" ] && iGacetas=-1; [ -z "$iContacto" ] && iContacto=-1
check '[ "$iGacetas" -gt 0 ]' 'footer tiene Gacetas Oficiales' 'falta Gacetas en footer'
order_ok=1
if [ "$iNoticias" -le "$iInicio" ]; then order_ok=0; fi
if [ "$iEntes" -le "$iNoticias" ]; then order_ok=0; fi
if [ "$iSobre" -le "$iEntes" ]; then order_ok=0; fi
if [ "$iGacetas" -le "$iSobre" ]; then order_ok=0; fi
if [ "$iContacto" -le "$iGacetas" ]; then order_ok=0; fi
check '[ "$order_ok" = 1 ]' 'orden footer: Inicio | Noticias | Entes | Sobre Nosotros | Gacetas Oficiales | Contacto' \
  "orden incorrecto: Inicio=$iInicio Noticias=$iNoticias Entes=$iEntes Sobre=$iSobre Gacetas=$iGacetas Contacto=$iContacto"
check 'grep -q "gacetas" "$HTTP_BODY"' 'footer linkea a /gacetas' 'falta link a gacetas'

echo '--- 5. Smoke ---'
for u in / /noticias /gacetas /entes /contacto /sobre-nosotros; do
  http "$E2E_BASE$u"
  check '[ "$HTTP_STATUS" = 200 ]' "$u 200" "$u $HTTP_STATUS"
done

summary 'CATEGORIAS'
