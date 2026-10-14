#!/usr/bin/env bash
# run-all.sh — ejecuta las 17 suites E2E (bash+psql) tras un migrate:fresh --seed.
# Uso:  bash tests/e2e/run-all.sh
#       bash tests/e2e/run-all.sh categorias videos   (solo suites indicadas)
# Exit: 0 si todas las suites pasan; 1 si alguna falla.
set -u
E2E_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$E2E_LIB_DIR/../.." && pwd)"
cd "$ROOT" || exit 1

ALL_SUITES=(
  categorias errors share users contact-messages social contact-info
  dashboard entes partners videos gacetas media noticias settings webp wysiwyg
)

if [ "$#" -gt 0 ]; then
  SUITES=("$@")
else
  SUITES=("${ALL_SUITES[@]}")
fi

echo '=============================================================='
echo ' E2E PortalPG2026 — preparacion (migrate:fresh --seed)'
echo '=============================================================='

if ! curl -s -o /dev/null --max-time 5 http://127.0.0.1:8000/ >/dev/null; then
  echo 'No hay servidor en http://127.0.0.1:8000 — iniciando...'
  mkdir -p "$HOME/.tmp"
  setsid php artisan serve --host=127.0.0.1 --port=8000 \
    > "$HOME/.tmp/portalpg-serve.log" 2>&1 < /dev/null & disown
  for i in $(seq 1 20); do
    curl -s -o /dev/null --max-time 2 http://127.0.0.1:8000/ && break
    sleep 1
  done
fi
if ! curl -s -o /dev/null --max-time 5 http://127.0.0.1:8000/; then
  echo 'ERROR: el servidor no responde en http://127.0.0.1:8000' >&2
  exit 1
fi

php artisan migrate:fresh --seed || { echo 'ERROR: migrate:fresh --seed fallo' >&2; exit 1; }
php artisan storage:link >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null || true
echo

declare -A RESULT
PASS=0; FAIL=0; FAILED=()

for s in "${SUITES[@]}"; do
  script="$E2E_LIB_DIR/test-$s.sh"
  if [ ! -f "$script" ]; then
    RESULT[$s]='AUSENTE'; FAIL=$((FAIL+1)); FAILED+=("$s")
    echo "== test-$s.sh AUSENTE =="
    continue
  fi
  echo '=============================================================='
  echo " == SUITE: test-$s.sh"
  echo '=============================================================='
  out="$HOME/.tmp/e2e-run-$s.log"
  bash "$script" 2>&1 | tee "$out"
  ec=${PIPESTATUS[0]}
  if [ "$ec" = 0 ]; then
    RESULT[$s]='OK'; PASS=$((PASS+1))
  else
    RESULT[$s]="FAIL($ec)"; FAIL=$((FAIL+1)); FAILED+=("$s")
  fi
  echo
done

echo '=============================================================='
echo ' RESUMEN GLOBAL'
echo '=============================================================='
for s in "${SUITES[@]}"; do
  printf '  %-18s %s\n' "$s" "${RESULT[$s]:-?}"
done
echo '--------------------------------------------------------------'
echo "  OK: $PASS   FAIL: $FAIL   TOTAL: $((PASS+FAIL))"
if [ "$FAIL" -gt 0 ]; then
  echo "  Suites fallidas: ${FAILED[*]}"
  echo '=============================================================='
  exit 1
fi
echo '=============================================================='
echo ' E2E COMPLETO: TODAS LAS SUITES EN VERDE'
exit 0
