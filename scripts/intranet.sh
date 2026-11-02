#!/usr/bin/env bash
#
# Desactiva/activa la publicación de la intranet (Funcionarios Activos y
# Jubilados) bajo el dominio del portal mediante reverse proxy de Nginx hacia
# el servidor interno 172.16.0.13 (que no tiene salida a internet).
#
#   sudo bash scripts/intranet.sh off      # deja de publicar /extranet y /intranet_jubilados
#   sudo bash scripts/intranet.sh on       # los vuelve a publicar
#   sudo bash scripts/intranet.sh status   # solo informa (no modifica nada)
#
# La primera ejecución de on/off migra los bloques del vhost a
# /etc/nginx/snippets/intranet.conf y deja una línea `include` que se
# comenta/descomenta para alternar. Es idempotente y tiene puerta `nginx -set`:
# si la configuración no valida, restaura el backup y NO recarga (el sitio no
# se queda caído). No toca la aplicación, la base de datos ni los settings del
# navbar (vaciar nav_funcionarios_url / nav_jubilados_url se hace en
# /admin/configuracion; ver docs/DOCUMENTACION.md §14.O y PENDIENTES E6).
#
set -euo pipefail

VF=/etc/nginx/sites-available/web
SNIP=/etc/nginx/snippets/intranet.conf
MODE="${1:-}"

log() { printf '\n== %s\n' "$1"; }

if [ "$MODE" != "off" ] && [ "$MODE" != "on" ] && [ "$MODE" != "status" ]; then
    echo "Uso: $0 off|on|status" >&2
    exit 2
fi

[ -f "$VF" ] || { echo "ERROR: no existe $VF" >&2; exit 1; }

http_code() { curl -sk -o /dev/null -w '%{http_code}' --resolve www.gdc.gob.ve:443:127.0.0.1 "https://www.gdc.gob.ve$1" || echo ERR; }

report() {
    echo "URL                                     codigo"
    printf '%-38s %s\n' "/extranet/login.php" "$(http_code /extranet/login.php)"
    printf '%-38s %s\n' "/intranet_jubilados/login.php" "$(http_code /intranet_jubilados/login.php)"
    printf '%-38s %s\n' "/ (portal)" "$(http_code /)"
    printf '%-38s %s\n' "/sistema/frontend (fallback)" "$(http_code /sistema/frontend)"
}

# ---------------------------------------------------------------- status ----
if [ "$MODE" = "status" ]; then
    echo "Vhost: $VF"
    grep -n "include snippets/intranet.conf" "$VF" || echo "  (sin línea include)"
    echo "Snippet: $SNIP -> $([ -f "$SNIP" ] && echo "existe" || echo "NO existe")"
    report
    exit 0
fi

[ "$(id -u)" -eq 0 ] || { echo "ERROR: '$MODE' requiere root (consola del servidor)." >&2; exit 1; }

# ------------------------------------------------ migración (1ª vez) --------
if grep -q "location ^~ /extranet/" "$VF"; then
    log "Migrando los bloques de intranet a $SNIP"
    mkdir -p "$(dirname "$SNIP")"
    TMP="$(mktemp)"
    rm -f "$SNIP"

    if ! awk -v snip="$SNIP" '
        BEGIN { started = 0; pending = 0; inrange = 0; blocks = 0; done = 0 }
        {
            line = $0
            if (!started && line ~ /^    # --- Intranet \(2026-10-28\)/) { started = 1; pending = 1 }
            if (pending) {
                if (!inrange) {
                    print "    # INTRANET-SCRIPT: bloques movidos a " snip
                    print "    # Alterna con: bash scripts/intranet.sh on|off"
                    print "    # include snippets/intranet.conf;"
                    inrange = 1
                }
                print line >> snip
                if (line ~ /^    \}$/) {
                    blocks++
                    if (blocks >= 2) { pending = 0; inrange = 0; done = 1 }
                }
                next
            }
            print line
        }
        END { if (!done) exit 3 }
    ' "$VF" > "$TMP"; then
        rm -f "$TMP"
        echo "ERROR: no encontré los bloques esperados en $VF (¿ya migrado o vhost modificado?)." >&2
        echo "       Revisa a mano: grep -n 'intranet' $VF" >&2
        exit 1
    fi

    grep -q "location ^~ /extranet/" "$SNIP" || { rm -f "$TMP" "$SNIP"; echo "ERROR: snippet incompleto (sin /extranet/)." >&2; exit 1; }
    grep -q "location ^~ /intranet_jubilados/" "$SNIP" || { rm -f "$TMP" "$SNIP"; echo "ERROR: snippet incompleto (sin /intranet_jubilados/)." >&2; exit 1; }
    grep -q "include snippets/intranet.conf;" "$TMP" || { rm -f "$TMP" "$SNIP"; echo "ERROR: no quedó la línea include en el vhost." >&2; exit 1; }
    if grep -q "location ^~ /extranet/" "$TMP"; then
        rm -f "$TMP" "$SNIP"
        echo "ERROR: quedaron bloques inline en el vhost." >&2
        exit 1
    fi

    BK="$VF.bak-intranet-$(date +%Y%m%d-%H%M%S)"
    cp -a "$VF" "$BK"
    install -o root -g root -m 644 "$TMP" "$VF"
    rm -f "$TMP"
    chown root:root "$SNIP"
    chmod 644 "$SNIP"
    echo "Snippet creado: $SNIP"
    echo "Backup del vhost: $BK"
else
    log "Bloques ya migrados; solo se alterna el include"
    BK="$VF.bak-intranet-$(date +%Y%m%d-%H%M%S)"
    cp -a "$VF" "$BK"
    echo "Backup del vhost: $BK"
fi

# ---------------------------------------------------------------- toggle ----
if [ "$MODE" = "off" ]; then
    sed -i 's|^\([[:space:]]*\)include snippets/intranet.conf;|\1# include snippets/intranet.conf;|' "$VF"
    if ! grep -q "^[[:space:]]*# include snippets/intranet.conf;" "$VF"; then
        cp -a "$BK" "$VF"
        echo "ERROR: no pude desactivar el include; vhost restaurado." >&2
        exit 1
    fi
else
    sed -i 's|^\([[:space:]]*\)#\+ *include snippets/intranet.conf;|\1include snippets/intranet.conf;|' "$VF"
    if ! grep -q "^[[:space:]]*include snippets/intranet.conf;" "$VF"; then
        cp -a "$BK" "$VF"
        echo "ERROR: no pude activar el include; vhost restaurado." >&2
        exit 1
    fi
fi

# ---------------------------------------------------------- validar/recargar
log "Validando configuración (nginx -t)"
if ! /usr/sbin/nginx -t; then
    cp -a "$BK" "$VF"
    echo "ERROR: nginx -t falló; vhost restaurado desde $BK y NO se recargó." >&2
    exit 1
fi

systemctl reload nginx
echo "Nginx recargado."

# ------------------------------------------------------------ verificación ---
log "Verificación (esperado: con 'off' los dos 404; con 'on' los dos 200)"
report
E=$(http_code /extranet/login.php)
J=$(http_code /intranet_jubilados/login.php)
H=$(http_code /)
if [ "$MODE" = "off" ]; then
    if [ "$E" = "404" ] && [ "$J" = "404" ] && [ "$H" = "200" ]; then
        echo "OK: la intranet ya NO está publicada por la ruta del portal."
        echo "    Recuerda vaciar nav_funcionarios_url y nav_jubilados_url en"
        echo "    /admin/configuracion para que los enlaces desaparezcan del navbar."
    else
        echo "AVISO: los códigos no son los esperados; revisa $VF y el estado de la app."
        exit 1
    fi
else
    if [ "$E" = "200" ] && [ "$J" = "200" ] && [ "$H" = "200" ]; then
        echo "OK: intranet publicada. Recuerda rellenar los enlaces en /admin/configuracion."
    else
        echo "AVISO: los códigos no son los esperados; revisa $VF."
        exit 1
    fi
fi
