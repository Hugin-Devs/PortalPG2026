#!/usr/bin/env bash
# lib.sh — helpers compartidos para las suites E2E (bash + psql, port desde PowerShell + MySQL)
# NO usar shebangs: Termux no tiene /usr/bin/env. Ejecutar siempre: bash tests/e2e/test-<nombre>.sh

E2E_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
E2E_ROOT="$(cd "$E2E_LIB_DIR/../.." && pwd)"
E2E_BASE="${E2E_BASE:-http://127.0.0.1:8000}"
E2E_TMP="${E2E_TMP:-$HOME/.tmp}"
E2E_SUITE="${E2E_SUITE:-e2e}"
E2E_PUB="$E2E_ROOT/storage/app/public"
PGPASSWORD_FILE="${PGPASSWORD_FILE:-$HOME/.portalpg2026_password}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-portalpg}"
DB_NAME="${DB_NAME:-portalpg2026}"

mkdir -p "$E2E_TMP"
BODY_FILE="$E2E_TMP/e2e-body-${E2E_SUITE}.html"
COOKIE_JAR=""

sql() {
  PGPASSWORD="$(cat "$PGPASSWORD_FILE")" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -At -c "$1" 2>&1
}
sqlt() {
  PGPASSWORD="$(cat "$PGPASSWORD_FILE")" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -At -F $'\t' -c "$1" 2>&1
}

OK=0
FAILN=0
ok()  { echo "  [OK] $1"; OK=$((OK+1)); }
bad() { echo "  [FAIL] $1"; FAILN=$((FAILN+1)); }
check() { # $1 = condicion (eval), $2 = mensaje OK, $3 = mensaje FAIL
  if eval "$1" >/dev/null 2>&1; then ok "$2"; else bad "$3"; fi
}
summary() { # $1 = etiqueta final
  echo ''
  if [ "$FAILN" -eq 0 ]; then
    echo "=== $1: TODAS LAS PRUEBAS EN VERDE ==="
    return 0
  else
    echo "=== $1: $FAILN FALLAS ==="
    return 1
  fi
}
re_escape() { printf '%s' "$1" | sed -e 's/[][\.^$*+?(){}|\\]/\\&/g'; }

get_token() { # $1 = archivo html
  grep -oP 'name="_token"\s+value="\K[^"]+' "$1" | head -1
}
require_token() {
  local t; t=$(get_token "$1")
  if [ -z "$t" ]; then echo 'CSRF token no encontrado' >&2; exit 1; fi
  printf '%s' "$t"
}

# http URL [args-curl...]
# - Sigue redirects (-L), usa COOKIE_JAR si esta definida.
# - Sin -f: captura cuerpo de 4xx/5xx.
# - Setea HTTP_STATUS, HTTP_FINAL (url final) y HTTP_BODY (archivo del cuerpo).
# - Para POST usar --data/--data-urlencode (implicitamente POST); para DELETE -X DELETE.
http() {
  local url="$1"; shift
  local args=(-s -o "$BODY_FILE" -w $'%{http_code}\t%{url_effective}' -L --max-time 60)
  if [ -n "$COOKIE_JAR" ]; then args+=(-b "$COOKIE_JAR" -c "$COOKIE_JAR"); fi
  local out
  out=$(curl "${args[@]}" "$@" "$url") || out=$'000\t'"$url"
  HTTP_STATUS="${out%%$'\t'*}"
  HTTP_FINAL="${out#*$'\t'}"
  HTTP_BODY="$BODY_FILE"
}

# login EMAIL [sufijo-jar] — GET login, extrae token, POST credenciales; setea COOKIE_JAR
login() {
  local email="$1" suffix="${2:-}"
  local jar="$E2E_TMP/e2e-${E2E_SUITE}-$(printf '%s' "$email" | tr -c 'A-Za-z0-9' '_')${suffix}.jar"
  rm -f "$jar"
  COOKIE_JAR="$jar"
  http "$E2E_BASE/admin/login"
  local t; t=$(require_token "$HTTP_BODY")
  http "$E2E_BASE/admin/login" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=password" \
    --data-urlencode "_token=$t"
}

# --- Snapshot de settings (multi-linea seguro) via tabla auxiliar ---
social_snapshot_save() {
  sql "DROP TABLE IF EXISTS _e2e_social_snapshot;
CREATE TABLE _e2e_social_snapshot AS SELECT \"key\", \"value\" FROM settings WHERE \"key\" LIKE 'social_%';" >/dev/null
}
social_snapshot_restore() {
  sql "DELETE FROM settings s WHERE s.\"key\" LIKE 'social_%' AND NOT EXISTS (SELECT 1 FROM _e2e_social_snapshot t WHERE t.\"key\" = s.\"key\");
UPDATE settings s SET \"value\" = t.\"value\" FROM _e2e_social_snapshot t WHERE s.\"key\" = t.\"key\";" >/dev/null
}
social_snapshot_mismatches() { # filas que NO coinciden con el snapshot
  sql "SELECT (SELECT COUNT(*) FROM _e2e_social_snapshot) - (SELECT COUNT(*) FROM settings s JOIN _e2e_social_snapshot t ON s.\"key\" = t.\"key\" WHERE s.\"value\" IS NOT DISTINCT FROM t.\"value\");"
}
social_snapshot_drop() { sql "DROP TABLE IF EXISTS _e2e_social_snapshot;" >/dev/null; }

contact_snapshot_save() {
  sql "DROP TABLE IF EXISTS _e2e_contact_snapshot;
CREATE TABLE _e2e_contact_snapshot AS SELECT \"key\", \"value\" FROM settings WHERE \"key\" LIKE 'contact_%';" >/dev/null
}
contact_snapshot_restore() {
  sql "DELETE FROM settings s WHERE s.\"key\" LIKE 'contact_%' AND NOT EXISTS (SELECT 1 FROM _e2e_contact_snapshot t WHERE t.\"key\" = s.\"key\");
UPDATE settings s SET \"value\" = t.\"value\" FROM _e2e_contact_snapshot t WHERE s.\"key\" = t.\"key\";" >/dev/null
}
contact_snapshot_mismatches() {
  sql "SELECT (SELECT COUNT(*) FROM _e2e_contact_snapshot) - (SELECT COUNT(*) FROM settings s JOIN _e2e_contact_snapshot t ON s.\"key\" = t.\"key\" WHERE s.\"value\" IS NOT DISTINCT FROM t.\"value\");"
}
contact_snapshot_count() { sql "SELECT COUNT(*) FROM _e2e_contact_snapshot;"; }
contact_snapshot_get() { # $1 = key
  sql "SELECT \"value\" FROM _e2e_contact_snapshot WHERE \"key\" = '$1';"
}
contact_snapshot_drop() { sql "DROP TABLE IF EXISTS _e2e_contact_snapshot;" >/dev/null; }
