#!/usr/bin/env bash
# test-share.sh — port de test-share.ps1 (botones compartir + lazy loading)
E2E_SUITE=share
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

# Fixture: /entes y /sobre-nosotros solo renderizan imgs lazy si hay >=1 partner.
# El entorno E2E (migrate:fresh --seed) no siembra partners; el entorno original
# del .ps1 tenia datos reales. Se crea 1 partner minimo y se elimina al final.
sql "DELETE FROM partners WHERE slug='e2e-share-fixture';" >/dev/null
fixtureCreated=0
if [ "$(sql 'SELECT COUNT(*) FROM partners;')" = 0 ]; then
  sql "INSERT INTO partners (name, slug, logo, \"order\", is_active, created_at, updated_at) VALUES ('E2E Share Fixture', 'e2e-share-fixture', 'uploads/e2e-share-fixture.png', 999, true, now(), now());" >/dev/null
  fixtureCreated=1
  ( cd "$E2E_ROOT" && php artisan cache:clear >/dev/null 2>&1 )
fi

echo '--- 1. Botones de compartir en detalle de noticia ---'
slug=$(sql "SELECT slug FROM posts WHERE status='published' ORDER BY id LIMIT 1;")
check '[ -n "$slug" ]' "slug de prueba: $slug" 'sin posts publicados'
http "$E2E_BASE/noticias/$slug"
check '[ "$HTTP_STATUS" = 200 ]' 'articulo 200' "status $HTTP_STATUS"
check 'grep -q "COMPARTIR:" "$HTTP_BODY"' 'etiqueta COMPARTIR' 'sin etiqueta COMPARTIR'
check 'grep -qE "facebook\.com/sharer/sharer\.php\?u=" "$HTTP_BODY"' 'link Facebook' 'sin link Facebook'
check 'grep -qE "twitter\.com/intent/tweet" "$HTTP_BODY"' 'link X' 'sin link X'
check 'grep -qE "wa\.me/\?text=" "$HTTP_BODY"' 'link WhatsApp' 'sin link WhatsApp'
check 'grep -qE "t\.me/share/url" "$HTTP_BODY"' 'link Telegram' 'sin link Telegram'
check 'grep -q "id=\"copyLinkBtn\"" "$HTTP_BODY"' 'boton Copiar enlace' 'sin boton copiar'
check 'grep -q "copyLinkText" "$HTTP_BODY"' 'span feedback copiar' 'sin span copiar'
n=$(grep -o 'target="_blank" rel="noopener noreferrer"' "$HTTP_BODY" | wc -l)
check '[ "$n" -ge 4 ]' ">=4 enlaces con target blank + noopener (got $n)" "noopener insuficiente (got $n)"
check 'grep -qE "facebook\.com/sharer/sharer\.php\?u=[a-z]+%3A%2F%2F" "$HTTP_BODY"' 'URL codificada (%3A%2F%2F)' 'URL sin codificar'
check 'grep -q "navigator\.clipboard\.writeText" "$HTTP_BODY"' 'JS vanilla copiar' 'sin JS copiar'
check 'grep -q "isSecureContext" "$HTTP_BODY"' 'guarda secure context' 'sin guarda isSecureContext'
check "grep -qE \"execCommand\\('copy'\\)\" \"\$HTTP_BODY\"" 'fallback execCommand copy' 'sin fallback execCommand'
check '! grep -qE "jquery|cdn\.jsdelivr.*share|addthis|sharethis" "$HTTP_BODY"' 'sin librerias de terceros' 'libreria de compartir detectada'

echo '--- 2. Lazy loading en grids (bajo el fold) ---'
http "$E2E_BASE/"
check '[ "$HTTP_STATUS" = 200 ]' 'home 200' "home $HTTP_STATUS"
check '! grep -qE "position-absolute[^>]*loading=\"lazy\"" "$HTTP_BODY"' 'hero SIN lazy (eager, LCP)' 'hero tiene lazy'
grid=$(grep -oE 'card-img-top[^>]*loading="lazy"' "$HTTP_BODY" | wc -l)
check '[ "$grid" -ge 1 ]' "grid home con lazy (got $grid)" 'grid home sin lazy'
http "$E2E_BASE/noticias"
check 'grep -qE "card-img-top[^>]*loading=\"lazy\"" "$HTTP_BODY"' 'grid /noticias con lazy' '/noticias sin lazy'
http "$E2E_BASE/entes"
check 'grep -q "height=\"70\" loading=\"lazy\"" "$HTTP_BODY"' 'grid /entes con lazy' '/entes sin lazy'
http "$E2E_BASE/sobre-nosotros"
# El bloque "Entes Afiliados" del footer está oculto (módulo en preparación,
# equipo externo — PENDIENTES E5): ya no hay logos de partners en el pie.
check '! grep -q "footer-text\">Entes<" "$HTTP_BODY"' 'footer sin Entes (oculto)' 'footer muestra Entes'
check '! grep -q "Entes Afiliados" "$HTTP_BODY"' 'sin bloque Entes Afiliados en footer' 'bloque Entes Afiliados visible'
http "$E2E_BASE/noticias/$slug"
check '! grep -qE "img-fluid[^>]*loading=\"lazy\"" "$HTTP_BODY"' 'imagen destacada del articulo eager' 'articulo con lazy (LCP)'

echo '--- 3. Smoke ---'
for p in / /noticias /contacto /entes; do
  http "$E2E_BASE$p"
  check '[ "$HTTP_STATUS" = 200 ]' "$p 200" "$p $HTTP_STATUS"
done

if [ "$fixtureCreated" = 1 ]; then
  sql "DELETE FROM partners WHERE slug='e2e-share-fixture';" >/dev/null
  ( cd "$E2E_ROOT" && php artisan cache:clear >/dev/null 2>&1 )
fi

summary 'SHARE+LAZY'
