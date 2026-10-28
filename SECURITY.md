# SECURITY.md — Riesgos, waivers y condiciones de despliegue

> **Estado (2026-10-28):** **producción actualizada a Laravel 13.38.0** (despliegue U1 desde `main` = `d4d3e91`; registro en DOCUMENTACION §14.M). Suite **303 tests / 1353 assertions** en verde, E2E **17/17**, `composer audit` **0 advisories / 0 waivers**, `npm audit` **0**, SBOM **215 componentes**, **ZAP baseline final: 0 altas / 0 medias / 1 baja (artefacto del servidor dev) / 4 informativas** — evidencia en `docs/zap-baseline-final-2026-10-26.html`.
> **7/7 condiciones de despliegue verificadas** y re-verificadas tras el upgrade (§1 · DOCUMENTACION §14.L y §14.M). Bloqueantes de salida a internet: **R5** (certificado TLS vencido) y **R6** (backup de BD) — `docs/PENDIENTES.md`.
> El waiver de `style-src 'unsafe-inline'` (TinyMCE) quedó **cerrado** con la CSP de nonce + `style-src-attr` del upgrade; el historial completo de rondas y decisiones vive en el historial de git.
> **Próxima revisión de waivers: 2026-11-21.**

## 1. Condiciones obligatorias de despliegue

El portal **solo puede publicarse en internet** cuando las 7 condiciones estén verificadas.
Entorno real desplegado: **Nginx + PHP-FPM en el propio servidor, sin proxy inverso delante**
(TLS terminado en Nginx, `TRUSTED_PROXIES` vacío). *Estado: **7/7 verificadas el 2026-10-25**
y **re-verificadas el 2026-10-28 tras el upgrade a Laravel 13** — evidencia en
`docs/DOCUMENTACION.md` §14.L y §14.M.*

| # | Condición | Verificación |
|---|-----------|--------------|
| 1 | **Eliminar `public/install.php`** del artefacto publicado (instalador one-shot sin autenticación; se autoborra al completarse, pero la ventana existe) | `test ! -f public/install.php && echo OK` en el servidor (⚠️ **vuelve con cada `git pull`/`reset --hard`**: borrar tras cada despliegue) |
| 2 | **`TRUSTED_PROXIES=<IP del proxy>`** (vacío si no hay proxy delante) y **`TRUSTED_HOSTS=web.gdc.gob.ve`** en el `.env` de producción (sin esto el rate limiting colapsa al bucket del proxy y la auditoría registra la IP del proxy) | `php artisan config:show security.trusted_proxies` y `... trusted_hosts` |
| 3 | **`DB_SSLMODE=require`** (cifrar el enlace a PostgreSQL) | `php artisan config:show database.connections.pgsql.sslmode` |
| 4 | **php.ini de PHP-FPM**: `upload_max_filesize=100M`, `post_max_size=110M`, `expose_php=Off` (vídeos de hasta 100 MB) | `grep -E '^(upload_max_filesize\|post_max_size\|expose_php)' /etc/php/8.3/fpm/php.ini` (ojo: `php -i` de CLI lee otro ini) |
| 5 | **Nginx: bloquear scripts en `/storage`** (el `.htaccess` de `storage/app/public` solo aplica en Apache): `location ~* \.(php\|phtml\|phar)$ { deny all; }` **y servir los estáticos con `X-Content-Type-Options: nosniff`** (los estáticos no pasan por Laravel: `add_header X-Content-Type-Options nosniff always;` en el bloque `location` de `/build`, `/storage`, `/images`) | `curl -I` sobre `/storage/x.php` (→403) y un `/build/assets/*.js` (→200 + nosniff) |
| 6 | **Cron de programados**: `* * * * * www-data php /var/www/html/PortalPG2026/artisan schedule:run` (ejecuta `contacts:prune` a las 03:17 y `audit:prune` a las 04:23) | `cat /etc/cron.d/portal` + `php artisan schedule:run` |
| 7 | **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) en el servidor web y/o proxy, tras confirmar HTTPS end-to-end | `curl -I https://web.gdc.gob.ve/` |

Checklist previa al arranque (primer lanzamiento):

- [x] `.env` de producción completo según `.env_example_produccion` (APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning, SESSION_SECURE_COOKIE=true) — verificado con `config:show` el 2026-10-25
- [x] `php artisan migrate --seed` + ejecución de `install.php` **solo desde red interna** o borrado inmediato (condición 1) — el instalador ya corrió en la instalación real y `public/install.php` está fuera del servidor
- [x] Cambio inmediato de la contraseña seed (el instalador la fija; `EnsurePasswordChanged` la fuerza igualmente) — los usuarios seed ya no existen; `EnsurePasswordChanged` fuerza el cambio en el primer login de las cuentas reales
- [x] `php artisan pii:encrypt` si la BD procede de una instalación anterior — `--dry-run` el 2026-10-25: **0** valores en texto plano
- [x] Verificación de cabeceras: `curl -I https://.../` → CSP con nonce, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS — verificada el 2026-10-25
- [ ] **Copia de seguridad de la BD y de `APP_KEY` en gestor de secretos** (la rotación de APP_KEY rompe el descifrado de PII; ver docs/DOCUMENTACION.md §14.D) — `APP_KEY` respaldada en fichero `chmod 600` fuera del repo; **falta el dump de la BD** (PENDIENTES R6)

## 2. Riesgos heredados y restricciones (waivers registrados)

| Riesgo | Restricción / motivo | Compensación | Revisión |
|--------|----------------------|--------------|----------|
| ~~**Laravel 10 EOL** (seguridad hasta 04-feb-2025)~~ **CERRADO 2026-10-26** | El requisito institucional de permanecer en Laravel 10 fue levantado; migración completa **10 → 11 → 12 → 13** ejecutada en la rama `Laravel13-Dev` (`docs/PLAN-LARAVEL13.md`, Fases 0–3) | **`laravel/framework` 13.38.0** (`^13.10.1`, estrictamente >13.10.0 y fuera del rango de los advisories EOL); `audit.ignore` **eliminado** de `composer.json` → `composer audit --locked` = 0 activos + **0 waivers**; PHP sigue en `^8.3` (parcheado) | ~~2026-11-21~~ **Cerrada** — validación en servidor (E2E + LATIN9 + ZAP) pendiente antes del merge a `main` |
| **Sin MFA en el panel admin** | Añadir TOTP/WebAuthn exige dependencia nueva (no se añade sin aprobación) | Rate limit por IP **y** por cuenta (5 fallos/60 s), bloqueo con mensaje genérico, auditoría de login fallido, sesión revocada al cambiar contraseña | 2026-11-21 — se recomienda L2 |
| ~~**`style-src 'unsafe-inline'`**~~ **CERRADO 2026-10-26** | Requerido por TinyMCE (waiver original) | CSP endurecida: `style-src 'self' 'nonce-X'` + `style-src-attr 'unsafe-inline'` (CSP3) + nonce en los 2 `<style>` inline; baseline ZAP posterior sin la alerta (0 medias); E2E 17/17 + smoke visual sin regresión | Cerrado con hardening — revisar al migrar editor |
| **`AES-256-CBC` + HMAC** (no AEAD-GCM) | Cambiar el cipher de Laravel re-cifraría todo el PII existente | Encrypt-then-MAC autenticado; clave solo en `.env`; CSPRNG para nonces | Migración de framework completada 2026-10-26 **sin** cambiar el cipher (exige re-cifrado de PII) — reprogramado con presupuesto |
| **Sin SAST automatizado / DAST completo** | Sin CI (decisión registrada en PENDIENTES C4); el DAST baseline se ejecuta manual | **Baseline ZAP 2.17.0: 2026-10-25 (L10), repetido 2026-10-26 sobre Laravel 13 (0/1/2 → final 0/0/1/4 con CSP y redirect corregidos — `docs/zap-baseline-final-2026-10-26.html`)**; 301 tests PHPUnit + 17 suites E2E + secret-scan manual + revisión de código | Pendiente: DAST **autenticado** y SAST manual en cada release |
| **Cobertura medida** (antes: no medida) | Xdebug 3.5.3 instalado 2026-10-25 (driver de cobertura en Laragon) | **97.58% líneas total** (app 98.05% · database 95.36%, 2026-10-26); authn/authz/validación/cifrado ≈100% salvo fallback iconv de `App\Support\Latin9` (defensivo); sin CI que la gatee | Re-medir en cada release (`php vendor/bin/phpunit --coverage-text`) |
| **`public/install.php` en el repositorio** | Decisión del cliente: instalador one-shot eliminado en el despliegue (condición 1) | Guarda de doble condición + autoborrado; riesgo asumido **solo** hasta el primer arranque | Cerrada al verificar condición 1 |
| ~~**CVE-2026-114509** (TokenGuard auth bypass)~~ **CERRADO 2026-10-26** | No aplicable (sin guard `token`/`auth:api`, sin `routes/api.php`) y **parcheado** en ≥13.29.0 — el lock actual es 13.38.0; Sanctum fue eliminado del proyecto en la Fase 1 de la migración | Sin waiver: `audit.ignore` eliminado; se re-evalúa si se añade cualquier autenticación por token/API | **Cerrada** |

## 3. Controles activos (resumen verificado)

- **Authz**: deny-by-default en 64/64 rutas admin (60 con `permission:*` + 6 justificadas sin permiso); test de regresión `AdminRouteAuthorizationCoverageTest` que **falla** si una ruta nueva queda sin permiso; jerarquía de roles (solo superadmin toca superadmin; nadie se auto-otorga permisos que no posee).
- **Authn**: bcrypt cost 12, lockout configurable (`LOGIN_LOCKOUT_ATTEMPTS`=5 / `LOGIN_LOCKOUT_DECAY`=60 s por IP y por cuenta), `auth.session` revoca otras sesiones al cambiar contraseña, y `POST /admin/cambiar-contrasena` limita a 10/min por usuario+IP (`PASSWORD_CHANGE_RATE_LIMIT`).
- **Sesión**: cookie `HttpOnly` + `SameSite=Lax`, lifetime 60 min, cifrada en reposo (`SESSION_ENCRYPT=true`).
- **Auditoría**: vista `/admin/auditoria` con filtros (acción, usuario, rol, rango de fechas, búsqueda libre); export CSV bajo permiso `audit.export` (neutraliza CWE-1236); retención `AUDIT_RETENTION_DAYS`=400 con `audit:prune` programado a diario; eventos ampliados (`permission.denied`, diff de permisos en `role.updated`, nombres de archivo en `media.uploaded`, `user.validation_failed`); FK `user_id` **`nullOnDelete`** (borrar un usuario conserva su historial).
- **XSS**: HTMLPurifier en escritura y lectura (`rendered_content`), allowlist única en `config/purifier.php`, CSP con nonce.
- **Subidas**: extensión por contenido con fallback `bin`, SVG prohibido, nombre generado por servidor, `.htaccess` anti-ejecución en `storage/app/public`.
- **PII**: `contacts.*` y `users.name` cifrados en reposo (`pii:encrypt`); auditoría sin PII; retención de contactos con `contacts:prune` (diario).
- **Integridad**: SBOM CycloneDX en `docs/` (regenerar en cada release); lockfile congelado; 0 advisories activos.
- **Runbook mínimo**: ante incidente → rotar `APP_KEY` solo si se acepta romper el descifrado de PII (ver docs/DOCUMENTACION.md §14.D); revocar sesión de un usuario = cambiar su contraseña (invalida todas); parar un ataque de fuerza bruta = `LOGIN_RATE_LIMIT`/bloqueo ya activo + revisar `/admin/auditoria`.

## 4. Política de cambios

Todo PR debe pasar: `php vendor/bin/pint --test`, `php artisan test` (**317 en verde, 1407 assertions**; 6 notices `iconv` preexistentes en `Latin9Test` y 4 skips por entorno — pgsql/symlink/GD), y si toca auth/authz/validación/cripto: tests nuevos obligatorios (TDD). Antes de cada release: `composer audit` (Laravel 13.38.0: 0 advisories, 0 waivers), `npm audit`, regenerar SBOM, re-ejecutar `bash tests/e2e/run-all.sh` (17/17, requiere PostgreSQL) y repetir el baseline ZAP (`scripts/zap-baseline.yaml`). **PR sin tests = PR rechazado.** El constraint `php: ^8.3` está blindado por test (`SecurityConfigTest`).
