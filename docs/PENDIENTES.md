# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar un ítem.
> Última actualización: 2026-11-06 (vista de mantenimiento `/extranet/*`, DOCUMENTACION §14.O).

**Abiertos: 5** — 2 de contenido del cliente + 1 de bloqueo de salida (backup de BD) + 1 de reactivación (intranet) + 1 de mantenimiento (guarda del seeder).
*Producción en **Laravel 13.38.0** desde el 2026-10-28 (U1 cerrado; registro en DOCUMENTACION §14.M), **publicada en internet** con **TLS de Let's Encrypt** (§14.P, R5 cerrado) e **intranet temporalmente despublicada** (§14.O, E6) — mientras tanto `/extranet/*` responde **503 con la vista de mantenimiento**. Gates: Pint ✅ · **372 tests · 1734 aserciones** · cobertura 97.58% · E2E 17/17 (re-validado 2026-11-06) · **ZAP final 0/0/1/4**, waiver `style-src` cerrado · audits 0/0.*

---

## A. Contenido / decisiones del cliente

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)

- [ ] **E5 — Reactivar los enlaces Entes**  
  Ocultos con comentario Blade el 2026-10-27 (`layouts/public.blade.php`: navbar, enlace del footer y bloque "Entes Afiliados") mientras un equipo externo prepara el módulo de entes. Las rutas `/entes` siguen vivas. Reactivar descomentando los 3 bloques y restaurando las aserciones de `test-entes.sh`/`test-categorias.sh`.

- [ ] **E6 — Reactivar la intranet (extranet y jubilados)**  
  Desvinculada el 2026-10-28: el proxy de Nginx quedó desactivado con **`scripts/intranet.sh off`** (bloques migrados a `/etc/nginx/snippets/intranet.conf`, línea `include` comentada) y los enlaces del navbar quedaron **vacíos** (ítems ocultos). Reactivar desde la consola del servidor: `bash /var/www/html/PortalPG2026/scripts/intranet.sh on` (o `status` para comprobar) y rellenar en `/admin/configuracion` `nav_funcionarios_url = https://www.gdc.gob.ve/extranet/login.php` y `nav_jubilados_url = https://www.gdc.gob.ve/intranet_jubilados/login.php`. Detalle en DOCUMENTACION.md §14.O.
  *Nota (2026-11-06): mientras siga desactivada, `/extranet/*` muestra la **vista de mantenimiento (503)** del portal en vez de 404 (route `maintenance.extranet`); al hacer `on`, el proxy intercepta antes que Laravel y la vista deja de verse **sin desplegar nada**. `intranet.sh off` ahora verifica `extranet=503` (antes 404).*

## B. Pre-release (bloquean la publicación / el release)

- [x] **R5 — Certificado TLS del dominio** → **cerrado el 2026-10-28 con Let's Encrypt**: instalado `certbot` (apt), reto ACME por webroot (el `return 301` del bloque :80 se bajó a `location /` para no interceptar `/.well-known/acme-challenge/`) y emitido el certificado de `www.gdc.gob.ve` (ECDSA, cadena completa) → `ssl_certificate` del vhost apunta a `/etc/letsencrypt/live/www.gdc.gob.ve/{fullchain,privkey}.pem`, **válido hasta 2027-01-26**, con `certbot.timer` activo, `renew --dry-run` OK y hook de despliegue que recarga Nginx. Verificado `curl -I` **sin `-k`** → 200 desde el servidor y desde el exterior (verify 0). Registro en DOCUMENTACION.md §14.P.

- [ ] **R6 — Copia de seguridad de la BD**  
  No hay cliente PostgreSQL (`psql`/`pg_dump`) en el servidor ⇒ no existe dump de `portalweb`. El despliegue del 2026-10-25 se hizo con backup de `.env`, `storage/` y assets pero **sin dump de BD** (aceptado explícitamente). Instalar `postgresql-client` y dejar un `pg_dump` diario + verificación de restauración.

- [x] **U1 — Despliegue del upgrade a Laravel 13** → **desplegado el 2026-10-28** (`8e06f82` L10.50.2 → `a55cfa7`/`d4d3e91` L13.38.0; runbook §14.G + §14.G.1; registro completo en **§14.M**). 7 condiciones re-verificadas; smoke íntegro en verde (incluido el 2º request con caché poblado); vídeo de la Noticia 6 servido con 206. Dos incidencias resueltas en la ventana: la allowlist `cache.serializable_classes` sin `PostMedia` (500 en la galería → fix `d4d3e91` + test de regresión) y el usuario seed creado por el seeder (eliminado; ver U2). Rollback disponible con `8e06f82` + backups del 2026-10-28.

- [ ] **U2 — Corregir la guarda de `NoticiasPrecargadasSeeder`** (no bloqueante)  
  El seeder crea `admin@gdc.gob.ve` (rol `editor`, contraseña `password`) si ese email no existe, aunque ya haya usuarios reales — en el despliegue del 2026-10-28 creó esa cuenta en producción y hubo que eliminarla (contenido reasignado al superadmin). Debe crear el usuario de respaldo **solo si la tabla `users` está vacía**, o firmar el contenido con el primer superadmin existente. Con test de regresión.

## C. Diferidos / fuera de alcance (no se van a hacer)

- **C1 — CI con GitHub Actions** — descartado: no se utilizará GitHub Actions.
- **D2 — CRUD de páginas estáticas en el admin** — archivado: **sin instrucción de que se vaya a necesitar**; `/pagina/{slug}` queda tal cual (vista `page.blade.php` lista, tabla `pages` vacía).
- **D5 — Front de la página de detalle de entes (`/entes/{slug}`)** — archivado: la vista actual es funcional (skeleton); se retoma solo con nueva instrucción de diseño.
- **E4 — Definir diseño del detalle de entes** — archivado junto con D5.
- **Tags en noticias** — omitido por decisión; si se retoma: columna `posts.tags` + chips.
- **Mejoras futuras**: newsletter, API REST, comentarios, galería, eventos, versión en inglés.
