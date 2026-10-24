# SECURITY.md — Riesgos heredados, waivers y condiciones de despliegue

> **Última revisión de seguridad**: 2026-10-23 · **HEAD**: `7ac0028` · **Gate**: 16✅ / 4⚠️ / 0❌
> Revisión completa bajo protocolo actualizado (gate de 20 ítems + coherencia 12 clases + gate de tests), con ejecución real: Pint ✅ · PHPUnit **180/180** ✅ · `composer audit --locked` (4 ignorados) · `npm audit` **0** ✅.
> Ronda previa (2026-10-22, HEAD `b6e2be4`): 13✅ / 7⚠️ / 0❌.
> **Revisión programada de waivers: 2026-11-21.**

## 0. Correcciones aplicadas en la revisión 2026-10-23

| Corrección | Motivo | Verificación |
|---|---|---|
| `composer.json`: `"php": "^8.1"` → `"^8.3"` | PHP 8.1 EOL (31-dic-2025) y 8.2 caduca (31-dic-2026); fijar `^8.3` hace fallar `composer install` en runtimes sin soporte en lugar de desplegar en silencio | Test de regresión `SecurityConfigTest::test_composer_requires_supported_php_version`; README/DOCUMENTACION.md actualizados a "PHP 8.3+" |
| `follow-redirects` 1.16.0 → **1.16.1** (`npm audit fix`) | 2 advisories high (GHSA-q3jh-wrc6-jgq8, GHSA-8r9p-f939-6h3c: fuga de cabeceras de credenciales vía cabeceras en forma de array / regex de sensitiveHeaders); dependencia transitiva de axios (tooling de build, no runtime PHP) | `npm audit` → **0 vulnerabilidades** |
| `cvss-scores.json` + INFORME §1.b: LOG-01 `pending` → `remediated` | Incoherencia clase 9 (contrato documentado ≠ estado real): LOG-01 estaba cerrado desde G6 (auditoría activa, 11 tests) pero los artefactos lo marcaban pendiente | Sincronizados con el cierre real de G6 |

**CVE-2026-48019 (CRLF email, High 8.9, sin parche en Laravel 10): verificado como no aplicable.** El supuesto de explotación exige que la aplicación envíe correo a direcciones suministradas por usuarios; este portal no envía ningún correo (el formulario de contacto solo persiste en BD — `ContactController::store` → `Contact::create`; `MustVerifyEmail` está comentado; sin `Mail::`/`Mailable`/`Notification` activos). Se re-evalúa si se añade cualquier flujo de correo saliente.

## 1. Condiciones obligatorias de despliegue

El portal **solo puede publicarse en internet** cuando las 7 condiciones estén verificadas.
Entorno objetivo: **Apache/Nginx + PHP-FPM, HTTPS terminado en proxy inverso.**

| # | Condición | Verificación |
|---|-----------|--------------|
| 1 | **Eliminar `public/install.php`** del artefacto publicado (instalador one-shot sin autenticación; se autoborra al completarse, pero la ventana existe) | `test ! -f public/install.php && echo OK` en el servidor |
| 2 | **`TRUSTED_PROXIES=<IP del proxy>`** y **`TRUSTED_HOSTS=portal.gdc.gob.ve`** en el `.env` de producción (sin esto el rate limiting colapsa al bucket del proxy y la auditoría registra la IP del proxy) | `grep -E '^TRUSTED_' .env` |
| 3 | **`DB_SSLMODE=require`** (cifrar el enlace a PostgreSQL) | `grep DB_SSLMODE .env` |
| 4 | **php.ini de PHP-FPM**: `upload_max_filesize=100M`, `post_max_size=110M`, `expose_php=Off` (vídeos de hasta 100 MB) | `php -i \| grep -E 'upload_max_filesize\|post_max_size'` |
| 5 | **Nginx: bloquear scripts en `/storage`** (el `.htaccess` de `storage/app/public` solo aplica en Apache): `location ~* \.(php\|phtml\|phar)$ { deny all; }` | revisión del vhost |
| 6 | **Cron de programados**: `* * * * * php /ruta/artisan schedule:run` (ejecuta `contacts:prune` a diario — retención GDPR) | `crontab -l` |
| 7 | **HSTS a nivel de proxy** (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) tras confirmar HTTPS end-to-end | `curl -I https://portal.gdc.gob.ve/` |

Checklist previa al arranque (primer lanzamiento):

- [ ] `.env` de producción completo según `.env_example_produccion` (APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning, SESSION_SECURE_COOKIE=true)
- [ ] `php artisan migrate --seed` + ejecución de `install.php` **solo desde red interna** o borrado inmediato (condición 1)
- [ ] Cambio inmediato de la contraseña seed (el instalador la fija; `EnsurePasswordChanged` la fuerza igualmente)
- [ ] `php artisan pii:encrypt` si la BD procede de una instalación anterior
- [ ] Verificación de cabeceras: `curl -I https://.../` → CSP con nonce, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS
- [ ] Copia de seguridad de la BD y de `APP_KEY` en gestor de secretos (la rotación de APP_KEY rompe el descifrado de PII; ver DOCUMENTACION §14.D)

## 2. Riesgos heredados y restricciones (waivers registrados)

| Riesgo | Restricción / motivo | Compensación | Revisión |
|--------|----------------------|--------------|----------|
| **Laravel 10 EOL** (seguridad hasta 04-feb-2025) | Versión mínima admitida por requisito del cliente; migración pendiente de presupuesto | 4 advisories en `composer.json → audit.ignore` (todos de funciones no usadas o con `APP_DEBUG=false`); SCA semanal manual (`composer audit`); CSP estricta; **PHP fijado a `^8.3`** (runtime parcheado aunque el framework esté EOL); **migrar a Laravel 12/13 antes de 2026-11-21** | 2026-11-21 |
| **Sin MFA en el panel admin** | Añadir TOTP/WebAuthn exige dependencia nueva (no se añade sin aprobación) | Rate limit por IP **y** por cuenta (5 fallos/60 s), bloqueo con mensaje genérico, auditoría de login fallido, sesión revocada al cambiar contraseña | 2026-11-21 — se recomienda L2 |
| **`style-src 'unsafe-inline'`** | Requerido por TinyMCE | `script-src` con nonce por petición (sin `unsafe-inline`), HTMLPurifier en escritura **y** lectura, `object-src 'none'`, `base-uri 'self'` | Al migrar editor |
| **`AES-256-CBC` + HMAC** (no AEAD-GCM) | Cambiar el cipher de Laravel re-cifraría todo el PII existente | Encrypt-then-MAC autenticado; clave solo en `.env`; CSPRNG para nonces | Con la migración de framework |
| **Sin SAST/DAST automatizados** | Sin CI (decisión registrada en PENDIENTES C4) y sin herramientas instaladas en el entorno de desarrollo | 180 tests PHPUnit + 17 suites E2E bash/psql + secret-scan manual + revisión de código; **recomendado: baseline ZAP contra staging antes de publicar** | Antes de publicar |
| **Cobertura de código no medida** | Sin driver Xdebug/PCOV en el entorno de desarrollo | Authn/authz/validación/cripto cubiertos por tests de comportamiento (14 negativos de autorización) | Medir en staging |
| **`public/install.php` en el repositorio** | Decisión del cliente: instalador one-shot eliminado en el despliegue (condición 1) | Guarda de doble condición + autoborrado; riesgo asumido **solo** hasta el primer arranque | Cerrada al verificar condición 1 |

## 3. Controles activos (resumen verificado)

- **Authz**: deny-by-default en 64/64 rutas admin (60 con `permission:*` + 6 justificadas sin permiso); test de regresión `AdminRouteAuthorizationCoverageTest` que **falla** si una ruta nueva queda sin permiso; jerarquía de roles (solo superadmin toca superadmin; nadie se auto-otorga permisos que no posee).
- **Authn**: bcrypt cost 12, lockout configurable (`LOGIN_LOCKOUT_ATTEMPTS`=5 / `LOGIN_LOCKOUT_DECAY`=60 s por IP y por cuenta), `auth.session` revoca otras sesiones al cambiar contraseña.
- **Sesión**: cookie `HttpOnly` + `SameSite=Lax`, lifetime 60 min, cifrada en reposo (`SESSION_ENCRYPT=true`).
- **Auditoría**: vista `/admin/auditoria` con filtros (acción, usuario, rol, rango de fechas, búsqueda libre); export CSV bajo permiso `audit.export` (neutraliza CWE-1236); retención `AUDIT_RETENTION_DAYS`=400 con `audit:prune` programado a diario; eventos ampliados (`permission.denied`, diff de permisos en `role.updated`, nombres de archivo en `media.uploaded`, `user.validation_failed`).
- **XSS**: HTMLPurifier en escritura y lectura (`rendered_content`), allowlist única en `config/purifier.php`, CSP con nonce.
- **Subidas**: extensión por contenido con fallback `bin`, SVG prohibido, nombre generado por servidor, `.htaccess` anti-ejecución en `storage/app/public`.
- **PII**: `contacts.*` y `users.name` cifrados en reposo (`pii:encrypt`); auditoría sin PII; retención de contactos con `contacts:prune` (diario).
- **Integridad**: SBOM CycloneDX en la raíz (regenerar en cada release); lockfile congelado; 0 advisories activos.
- **Runbook mínimo**: ante incidente → rotar `APP_KEY` solo si se acepta romper el descifrado de PII (ver DOCUMENTACION §14.D); revocar sesión de un usuario = cambiar su contraseña (invalida todas); parar un ataque de fuerza bruta = `LOGIN_RATE_LIMIT`/bloqueo ya activo + revisar `/admin/auditoria`.

## 4. Política de cambios

Todo PR debe pasar: `php vendor/bin/pint --test`, `php artisan test` (180+), y si toca auth/authz/validación/cripto: tests nuevos obligatorios (TDD). Antes de cada release: `composer audit`, `npm audit`, regenerar SBOM, y re-ejecutar `bash tests/e2e/run-all.sh` (17/17, requiere PostgreSQL). **PR sin tests = PR rechazado.** El constraint `php: ^8.3` está blindado por test (`SecurityConfigTest`).
