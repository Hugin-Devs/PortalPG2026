# Informe de Análisis de Vulnerabilidades — Portal2026

> 📄 **Entregable (estado actual, imprimible):** [informe-estado-actual.html](informe-estado-actual.html) —
> instantánea sin historial para entrega institucional; exportar el PDF con `scripts/export-informe.ps1`
> o Imprimir → Guardar como PDF (tamaño carta). Este documento es el informe de trabajo completo con historial.
>
> **Fecha:** 2026-10-08 · **Última actualización:** 2026-10-25 (ronda de verificación R1–R3 — commit `60de864`)  
> **Proyecto:** Portal institucional GDC (Laravel 10 + Blade + **PostgreSQL**, PHP `^8.3`)  
> **Ejecutado por:** Análisis automatizado + revisión manual de código y configuración  
> **Documento base:** [PLAN-VULNERABILIDADES.md](PLAN-VULNERABILIDADES.md)  
> **Entregables asociados:** `SBOM-Portal2026.json`, `composer-audit.json`, `npm-audit.json`, `cvss-scores.json`, `zap-baseline-2026-10-25.{html,json}`

---

## 1. Resumen ejecutivo

> **Nota de estado (2026-10-23):** los párrafos de este §1 describen el estado **inicial** al momento del análisis (2026-10-08). Todas las filas de los hallazgos listados están **remediadas** salvo `SC-02` (aceptado con compensatorios); el estado real vigente está en §1.b y el detalle del refuerzo F6 en §1.c.

Se ejecutó el **Plan de Análisis de Vulnerabilidades** aprobado para el Portal2026, combinando análisis de dependencias (`composer audit`, `npm audit`), análisis estático de código (SAST), revisión de configuración y validación de la suite de pruebas existente. **No se realizaron pruebas activas de explotación** contra producción; la evaluación se limitó al repositorio y a la réplica local de desarrollo, conforme a las reglas de engagement.

**Hallazgos principales (estado inicial, 2026-10-08):**

| Severidad | Cantidad | IDs de hallazgos representativos |
|---|---|---|
| Crítica | 2 | AUTH-02 (credenciales seed), CFG-03 (APP_DEBUG + Ignition) |
| Alta | 4 | AUTHZ-01 (escalada a superadmin), FILE-03 (integridad de gacetas), **SC-01** (dependencias vulnerables), PRIV-01 (PII sin cifrado en reposo) |
| Media | 9 | AUTH-01, AUTHZ-02, INJ-01, INJ-02, CFG-01, CFG-02, SC-02, LOG-01, ABUSE-01 |
| Baja | 0 | — |

**Riesgos de cumplimiento (LOPDP / Infogobierno):**

- La tabla `contacts` almacena PII ciudadana (nombre, correo, mensaje) sin cifrado en reposo y sin trazabilidad de auditoría, lo que dificulta cumplir el deber de seguridad y la notificación de brechas de la LOPDP.
- La tabla `users` contiene credenciales de funcionarios públicos con contraseñas seed predecibles.
- Las Gacetas Oficiales son documentos de integridad institucional; su manejo sin control de integridad ni anti-manipulación expone al cumplimiento de la Ley de Infogobierno (art. 25 y 31).

**Recomendación ejecutiva:**

Tratar los **2 hallazgos Críticos** como incidentes de seguridad potenciales y remediarlos antes del despliegue. Implementar los controles compensatorios para **Laravel 10 EOL** de inmediato, dado que la institución descartó el upgrade. Actualizar `PENDIENTES.md` con los nuevos ítems de remediación y sus SLA.

---

## 1.b Estado de remediación (actualizado 2026-10-23)

Tras cuatro rondas de cierre (remediación inicial + auditoría comprobatoria + endurecimiento PG + ajustes F6), el estado de los hallazgos es:

| ID | Hallazgo | Estado | Observaciones |
|---|---|---|---|
| AUTH-01 | Login sin rate limiting | ✅ Remedado | Rate limiter por IP + **lockout configurable** (`LOGIN_LOCKOUT_ATTEMPTS=5` / `LOGIN_LOCKOUT_DECAY=60`, `config/security.php` para `config:cache`) |
| AUTH-02 | Password seed `password` | ✅ Remedado | Middleware `EnsurePasswordChanged` + vista `/admin/cambiar-contrasena` |
| AUTHZ-01 | Escalada a `superadmin` | ✅ Remedado | Solo `superadmin` asigna `superadmin`; no se puede degradar al último |
| AUTHZ-02 | Editor puede publicar | ✅ Remedado | `NewsController::resolveStatus` fuerza `draft` para `editor` |
| INJ-01 | XSS en `rendered_content` | ✅ Remedado | Trait `SanitizesHtml` (HTMLPurifier) en `Post` **y** `Page`; post-proceso elimina `<a href="data:...">` |
| INJ-02 | SVG con extensión del cliente | ✅ Remedado | `svg` eliminado de los mimes de los 8 puntos de subida |
| FILE-01 | Extensión de archivos subidos controlada por cliente | ✅ Remedado | `App\Support\Uploads::safeExtension()` usa la extensión adivinada por contenido en 8 puntos de subida |
| FILE-03 | Gacetas sin integridad | ✅ Remedado | Columna `file_hash` (SHA-256) en `gacetas` |
| CFG-01 | Sin headers de seguridad | ✅ Remedado | Middleware `AddSecurityHeaders` (CSP, HSTS, X-Frame-Options, `frame-ancestors`, `form-action`, …) |
| CFG-02 | `SESSION_SECURE_COOKIE` sin default | ✅ Remedado | `env('SESSION_SECURE_COOKIE', env('APP_ENV') !== 'local')`; sesión con lifetime **60 min** y cookie cifrada en reposo (`'encrypt' => true` en `config/session.php:49`) |
| CFG-03 | `APP_DEBUG=true` + Ignition | ✅ Remedado | `APP_DEBUG=false` por defecto; Ignition solo en `require-dev` |
| CFG-06 | `env()` fuera de `config/` con `config:cache` | ✅ Remedado | Nuevo `config/security.php` centraliza rate limits, `TRUSTED_PROXIES` y `TRUSTED_HOSTS`; leídos con `config()` |
| SC-01 | Dependencias vulnerables | ✅ Remedado | Guzzle 7.15.5, commonmark 2.10.3, flysystem 3.36.0, Vite 8.3.4; `composer audit` **0 advisories activos**, 4 IDs de `laravel/framework` ignorados bajo waiver EOL (§2); `npm audit` 0 vulnerabilidades |
| SC-02 | Laravel 10 EOL | ✅ Cerrado 2026-10-26 | Migración completa a **Laravel 13.38.0** en rama `Laravel13-Dev` (waiver institucional levantado; `docs/PLAN-LARAVEL13.md`) — ver §7 |
| LOG-01 | Sin auditoría de acciones | ✅ Remedado | Migración `activity_logs`, hooks `Audit::log`, vista `/admin/auditoria` con **filtros** (acción/usuario/rol/fechas/búsqueda) y **export CSV** (permiso `audit.export`, CWE-1236 neutralizado); **retención 400 días** (`audit:prune` diario 04:23); eventos ampliados (`permission.denied`, diff de permisos en `role.updated`, nombres de archivo en `media.uploaded`, `user.validation_failed` sin valores); 31 tests |
| ABUSE-01 | `POST /contacto`/`buscar` sin límite | ✅ Remedado | `throttle:contact` y `throttle:search` configurables |
| PRIV-01 | PII sin cifrar en reposo | ✅ Remedado | Casts `encrypted` + comando `php artisan pii:encrypt` para datos heredados |

**Gaps detectados en la auditoría comprobatoria y cerrados en esta ronda:**

- **FILE-01 (alto):** la extensión guardada de los uploads dependía de `getClientOriginalExtension()` → `App\Support\Uploads::safeExtension()` adivina la extensión por contenido en 7 puntos de subida; tests en `UploadExtensionTest`.
- **CFG-06 (medio):** `env()` fuera de archivos de configuración hacía inútiles las variables de `.env` tras `php artisan config:cache` → `config/security.php` centraliza rate limits, `TRUSTED_PROXIES` y `TRUSTED_HOSTS`; test `SecurityConfigTest`.
- **INJ-01 residual (medio):** `data:` permitido globalmente habilitaba `<a href="data:...">` → post-proceso en `SanitizesHtml` elimina el ancla completa mientras conserva `<img src="data:...">`; test en `HtmlSanitizationTest`.

**Casos de prueba del plan verificados en esta ronda:**

- `FILE-04` parcial: un archivo de texto renombrado como imagen es rechazado por la regla `mimes` (`UploadExtensionTest`).
- `CFG-07`: CORS limitado a rutas `api/*` inexistentes; las páginas públicas no envían `Access-Control-Allow-Origin` (`CorsConfigurationTest`).
- `FILE-06`: el symlink `public/storage` apunta a `storage/app/public` (`StorageSymlinkTest`, skipped si no existe el enlace).

**Casos residuales pendientes de DAST / verificación manual / infraestructura:**

`FILE-07` (cuotas de almacenamiento), `FILE-09` (sidecars WebP desde imágenes maliciosas), `INJ-03` (XSS reflejado), `INJ-05` (CRLF / open redirect), `INJ-06` (SSTI en Blade), `SC-05` (integridad de `public/vendor/tinymce`), `LOG-03` (exposición de archivos sensibles) y `PRIV-03` (trazabilidad para notificación de brechas). Seguimiento en `PENDIENTES.md` D. *Nota 2026-10-25: el baseline ZAP (pasivo) no cubre estos casos — siguen pendientes de pruebas activas en staging.*

**Verificación final (2026-10-25):** **237 tests PHPUnit OK** (1032 assertions) · **cobertura 82.22%** (Xdebug) · pint passed · `composer audit` 0 activos + 5 ignorados justificados · `npm audit` 0 vulnerabilidades · **17/17 suites E2E** en verde contra PostgreSQL local · **baseline ZAP 2.17.0**: 0 altas / 1 media (waiver TinyMCE) / 2 bajas / 4 informativas (§1.d). Rondas anteriores: 180 tests (2026-10-23), 68 tests + 16/16 E2E (2026-10-08).

---

## 1.c Ronda de ajustes 2026-10-23 (F6, commit `7ac0028`)

Refuerzo de los controles compensatorios de `SECURITY.md` sobre los hallazgos ya remedados (AUTH-01, CFG-02, LOG-01), sin cambios en el estado de riesgo:

| Ajuste | Hallazgo | Detalle |
|---|---|---|
| Lockout configurable | AUTH-01 | Umbral y ventana de bloqueo leídos de `config/security.php` (`LOGIN_LOCKOUT_ATTEMPTS=5`, `LOGIN_LOCKOUT_DECAY=60`), respetando `config:cache` |
| Sesión endurecida | CFG-02 | Cookie con lifetime **60 min** y **cifrada en reposo** (`SESSION_ENCRYPT=true`); invariantes blindadas por test (`SecurityConfigTest`) |
| Auditoría filtrable + export | LOG-01 | Vista `/admin/auditoria` con filtros (acción/usuario/rol/fechas/búsqueda) y export CSV con neutralización de CWE-1236, tras el permiso nuevo `audit.export` |
| Retención de auditoría | LOG-01 | `AUDIT_RETENTION_DAYS=400` (~13 meses, ciclo anual completo) + comando `audit:prune` programado a diario (04:23) e índice `activity_logs.user_id` |
| Eventos ampliados | LOG-01 | `permission.denied` (denegaciones de autorización), diff real de permisos en `role.updated` (concedidos/quitados), nombres de archivo en `media.uploaded`, `user.validation_failed` (solo nombres de campo, sin valores para no exponer PII/credenciales) |

**Tests:** 156 → **180** (nuevos: `RateLimitTest::test_lockout_threshold_comes_from_config`, `SecurityConfigTest::test_session_cookie_invariants`, `AuditFilterTest` (7), `AuditExportTest` (5), `AuditEventsTest` (6), `AuditPruneTest` (4)). `pint` y suite en verde.

---

## 1.d Ronda de verificación previa 2026-10-25 (R1–R3, commit `60de864`)

Cierre de las tres verificaciones que bloqueaban la publicación:

| Verificación | Resultado | Evidencia |
|---|---|---|
| **R1 — Suite E2E completa** | **17/17 en verde** contra PostgreSQL local (incluidos `test-entes`/`test-share`, antes en rojo por bugs de entorno Windows/Git Bash, no del código). Fixes en `tests/e2e/lib.sh`: locale UTF-8 para `grep -P`, `tr -d '\r'` en las salidas de `psql`, wrapper de `curl` para rutas MSYS + `MSYS2_ARG_CONV_EXCL`, `timezone=America/Caracas` en `PGOPTIONS` | `bash tests/e2e/run-all.sh` (log 2026-10-25); backup `pg_dump` previo |
| **R2 — DAST baseline (OWASP ZAP 2.17.0)** | Spider de 67 URLs + passive scan contra `http://127.0.0.1:8000` → **0 altas / 1 media / 2 bajas / 4 informativas**. **Fixes:** cookie `XSRF-TOKEN` con `HttpOnly` (la app no usa `X-XSRF-TOKEN`) y `X-Powered-By` retirado en runtime. **Residual:** media = CSP `style-src unsafe-inline` (waiver TinyMCE §2/SECURITY.md); bajas = cuerpo estándar de la redirección de `/admin/login` (378 B, sin datos sensibles) y `nosniff` ausente en **ficheros estáticos** (no pasan por Laravel → añadido a la condición 5 de despliegue) | `docs/zap-baseline-2026-10-25.html` + `.json`; job reproducible `scripts/zap-baseline.yaml`; regresiones en `SecurityHeadersTest` |
| **R3 — Cobertura de código** | Xdebug 3.5.3 (PHP 8.3 ZTS vs16) → **82.22% de líneas** en `app/` (objetivo ≥80%). Authn/authz/validación/cifrado ≈100% salvo el fallback de iconv de `App\Support\Latin9` (defensivo, no forzable en este runtime). Tests nuevos: `AdminPartnerCrudTest` (12), `AdminContactInboxTest` (8), `AdminSettingsControllerTest` (10) + 7 regresiones críticas | `php vendor/bin/phpunit --coverage-text`; informe HTML en `/coverage-html` (local) |

**Suite total:** 180 → **237 tests** (1032 assertions, 0 fallos) · Pint en verde.

---

## 2. Alcance y metodología

### 2.1 Alcance evaluado

- **Aplicación:** rutas públicas (`/`, `/noticias`, `/contacto`, `/buscar`, etc.) y panel administrativo (`/admin/*`).
- **Código:** `app/`, `routes/`, `config/`, `resources/views/`, `database/seeders/`.
- **Dependencias:** `composer.lock` (PHP) y `package-lock.json` (Node).
- **Configuración:** `.env.example`, `config/session.php`, `config/cors.php`, `public/.htaccess`, `Http/Kernel.php`.
- **Infraestructura de despliegue:** revisada mediante configuración documentada en `DOCUMENTACION.md` §14; no se realizaron escaneos de red.

### 2.2 Metodología (fases ejecutadas)

| Fase | Técnica | Herramientas | Evidencia |
|---|---|---|---|
| F0 | Preparación | Revisión de `PLAN-VULNERABILIDADES.md`, reglas de engagement | Este informe |
| F1 | Reconocimiento | `php artisan route:list --json`, listado de rutas y middleware | §3.1 y §4 |
| F2.1 | SCA | `composer audit --format=json`, `npm audit --json` | `composer-audit.json`, `npm-audit.json` |
| F2.2 | SAST | `grep`/`Select-String` sobre PHP/Blade, revisión manual | §4 |
| F2.4 | Config review | Lectura de `config/`, `.env.example`, `.htaccess` | §4.2 |
| F2.5 | Validación de controles | `vendor/bin/phpunit --testdox` + `--coverage-text` (Xdebug) | **237/237 tests OK (1032 assertions), cobertura 82.22%**; ronda inicial: 68/68 |
| F3 | Explotación controlada | **Baseline ZAP 2.17.0** (spider + passive scan) ejecutado 2026-10-25; activo/fuzzing no ejecutado por reglas de engagement | `zap-baseline-2026-10-25.{html,json}` · activo pendiente en staging |
| F4 | Informe | Markdown + CVSS v4.0 (`cvss` 3.6) + SBOM | `INFORME-VULNERABILIDADES.md`, `SBOM-Portal2026.json` |

### 2.3 Limitaciones

- **DAST baseline ejecutado el 2026-10-25** con OWASP ZAP 2.17.0 contra el servidor local (0 altas / 1 media waiver / 2 bajas — §1.d). Queda pendiente la **revalidación autenticada y la repetición contra el entorno de producción ya desplegado** (2026-10-25, ver `DOCUMENTACION.md` §14.L), además del fuzzing/activo, que no se ejecutó por reglas de engagement.
- No se realizó pentest de infraestructura ni pruebas de denegación de servicio destructivas.
- El `.env` real no fue inspeccionado para evitar exposición de secretos; se usó `.env.example` como referencia.

---

## 3. Superficie y controles de línea base

### 3.1 Rutas identificadas

- **14 rutas públicas** (13 de navegación + `POST /contacto`, único endpoint de escritura pública).
- **`GET|POST /admin/login`** fuera del middleware `auth`; `POST /admin/login` aplica `throttle:login` (la ruta GET no).
- **66 rutas administrativas** bajo `prefix('admin')`: 64 dentro del grupo `auth` + `auth.session` + `password.changed` y 60 con `permission:<slug>` (6 justificadas y cubiertas por `AdminRouteAuthorizationCoverageTest`).
- **Rutas de Ignition** expuestas automáticamente: `_ignition/execute-solution`, `_ignition/health-check`, `_ignition/update-config` (activas solo con `APP_DEBUG=true`; Ignition está en `require-dev` y no se instala en producción).

### 3.2 Controles de seguridad presentes

| Control | Estado | Evidencia |
|---|---|---|
| CSRF | ✅ Activo en todo `web` | `VerifyCsrfToken.php` (`$except` vacío) |
| Hash de contraseñas | ✅ bcrypt | `config/hashing.php` |
| Regeneración de sesión | ✅ En login/logout | `AuthController.php:56` (login), `:75-76` (logout) |
| Cookies HttpOnly / SameSite | ✅ `http_only=true`, `same_site=lax` | `config/session.php` |
| Validación de entrada | ✅ Generalizada (Form Request/validate) | Múltiples controladores |
| Rate limiting | ✅ Implementado | `throttle:login`/`contact`/`search` (`routes/web.php`), limiters en `RouteServiceProvider`, lockout configurable 5/60 s (`config/security.php`) |
| Headers de seguridad | ✅ Presentes | `AddSecurityHeaders` (CSP/HSTS/X-Frame-Options/`frame-ancestors`/`form-action`), test `SecurityHeadersTest` |
| Secure cookie | ✅ Con default por entorno | `config/session.php:171` (`APP_ENV !== 'local'`); lifetime 60 min, `'encrypt' => true` |
| TrustHosts | ✅ Implementado | `app/Http/Middleware/TrustHosts.php`, registrado en `app/Http/Kernel.php` |
| Auditoría de acciones | ✅ Presente | `activity_logs` + `Audit::log`, `/admin/auditoria` con filtros y export CSV, `audit:prune` diario |

---

## 4. Hallazgos

### 4.1 Tabla resumen

> El estado se sincroniza con la remediación real (§1.b); ver §1.c para el refuerzo F6 de 2026-10-23. El detalle histórico de cada hallazgo (evidencia de origen, vector CVSS, recomendación) se conserva en §4.2 sin cambios.

| ID | Hallazgo | CVSS v4.0 | Severidad | SLA | Estado |
|---|---|---|---|---|---|
| AUTH-01 | `/admin/login` sin rate limiting ni lockout | 6.9 (M) | Media | 90 días | ✅ Remedado (lockout configurable) |
| AUTH-02 | Usuarios seed con contraseña `password` | 9.3 (C) | Crítica | 7 días | ✅ Remedado |
| AUTHZ-01 | `users.edit` permite asignar rol `superadmin` | 8.6 (H) | Alta | 30 días | ✅ Remedado |
| AUTHZ-02 | Editor puede publicar noticias (`status=published`) | 5.3 (M) | Media | 90 días | ✅ Remedado |
| INJ-01 | XSS almacenado en `Post::rendered_content` | 5.1 (M) | Media | 90 días | ✅ Remedado |
| INJ-02 | Uploads SVG con extensión decidida por el cliente | 5.1 (M) | Media | 90 días | ✅ Remedado |
| FILE-03 | Gacetas oficiales sin control de integridad/acceso | 7.1 (H) | Alta | 30 días | ✅ Remedado |
| CFG-01 | Ausencia de CSP, HSTS, X-Frame-Options, X-Content-Type-Options | 5.3 (M) | Media | 90 días | ✅ Remedado |
| CFG-02 | `SESSION_SECURE_COOKIE` sin default `true` | 6.3 (M) | Media | 90 días | ✅ Remedado (sesión 60 min + cifrada) |
| CFG-03 | `APP_DEBUG=true` + endpoints de Ignition habilitados | 9.3 (C) | Crítica | 7 días | ✅ Remedado |
| SC-01 | Dependencias PHP/JS con advisories conocidos | 6.9 (M)* | Alta | 30 días | ✅ Remedado |
| SC-02 | Laravel 10.50.2 EOL (sin parches de seguridad) | 6.9 (M) | Media | 90 días | ✅ Cerrado 2026-10-26 (Laravel 13.38.0) |
| LOG-01 | Sin registro de auditoría de acciones administrativas | 6.9 (M) | Media | 90 días | ✅ Remedado (filtros, export, retención 400 d) |
| ABUSE-01 | `POST /contacto` y `/buscar` sin rate limiting | 6.9 (M) | Media | 90 días | ✅ Remedado |
| PRIV-01 | PII (`contacts`, `users`) sin cifrado en reposo | 7.1 (H) | Alta | 30 días | ✅ Remedado |

\* El score CVSS v4.0 base es 6.9; se eleva a **Alta** por la presencia de advisories individuales clasificados como High (p. ej. `league/commonmark` DoS/XSS, `guzzlehttp/guzzle` host/cookie bypass).

### 4.2 Detalle de hallazgos

---

#### AUTH-01 — `/admin/login` sin rate limiting ni lockout

- **Caso de prueba:** AUTH-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Score:** 6.9 (Media)
- **Ubicación:** `routes/web.php:40-41`, `app/Http/Controllers/Admin/AuthController.php:14-42`
- **Evidencia:** El middleware `throttle` está registrado en `Kernel.php:82` pero **nunca se aplica** en ninguna ruta. `AuthController::login()` no implementa lockout ni CAPTCHA. Los mensajes de error son genéricos (mitiga enumeración de usuarios), pero la ausencia de límite de intentos permite ataques de fuerza bruta y credential stuffing.
- **Impacto:** Compromiso de cuentas administrativas; acceso a PII ciudadana y gestión de contenidos oficiales.
- **Recomendación:**
  1. Aplicar `Route::middleware('throttle:5,1')` a `POST /admin/login`.
  2. Implementar lockout progresivo (`RateLimiter::for('login', ...)`).
  3. Considerar CAPTCHA tras varios intentos fallidos.
- **Esfuerzo:** Bajo

---

#### AUTH-02 — Usuarios seed con contraseña `password`

- **Caso de prueba:** AUTH-02
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N`
- **Score:** 9.3 (Crítica)
- **Ubicación:** `database/seeders/UserSeeder.php:24`
- **Evidencia:** Los tres usuarios seed (`admin@gdc.gob.ve`, `admin2@gdc.gob.ve`, `editor@gdc.gob.ve`) se crean con `Hash::make('password')`. Aunque `DOCUMENTACION.md` §14.F indica cambiarlos post-deploy, no existe control que lo impida ni auditoría de cumplimiento.
- **Impacto:** Compromiso total del panel administrativo, incluyendo `superadmin`; acceso a PII y a la publicación de gacetas/noticias oficiales.
- **Recomendación:**
  1. Forzar cambio de contraseña en el primer login (campo `password_changed_at` + middleware).
  2. Añadir política de fortaleza (≥12 caracteres, mayúsculas, minúsculas, dígitos, símbolos).
  3. Eliminar el seeder en producción o generar contraseñas aleatorias de un solo uso.
- **Esfuerzo:** Bajo-Medio

---

#### AUTHZ-01 — `users.edit` permite asignar rol `superadmin`

- **Caso de prueba:** AUTHZ-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:H/VI:H/VA:N/SC:N/SI:N/SA:N`
- **Score:** 8.6 (Alta)
- **Ubicación:** `app/Http/Controllers/Admin/UserController.php:30,56`
- **Evidencia:** Tanto `store()` como `update()` validan `'role' => 'required|in:superadmin,admin,editor'` sin verificar que el usuario autenticado sea `superadmin`. Un `admin` con permiso `users.edit` puede crear/editar otro usuario y asignarle `superadmin`, o incluso auto-elevarse editando su propio registro (a menos que la lógica de `is_active` lo detenga, el rol no está protegido).
- **Impacto:** Escalación horizontal/vertical de privilegios; bypass de separación de roles institucional.
- **Recomendación:**
  1. Restringir la asignación de `superadmin` a usuarios con rol `superadmin`.
  2. Implementar Form Request dedicado para `users` con reglas condicionales (`Gate::allows('assign-superadmin')`).
  3. Añadir test PHPUnit que un `admin` no pueda crear `superadmin`.
- **Esfuerzo:** Bajo

---

#### AUTHZ-02 — Editor puede publicar noticias (`status=published`)

- **Caso de prueba:** AUTHZ-02
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:N/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Score:** 5.3 (Media)
- **Ubicación:** `app/Http/Controllers/Admin/NewsController.php:37,71`
- **Evidencia:** `NewsController::store()` y `update()` validan `'status' => 'nullable|in:draft,published'` sin verificar el rol del usuario. SPECS §5.3 establece que el editor no puede publicar, pero esa regla solo está en la UI; el servidor la acepta.
- **Impacto:** Publicación no autorizada de contenido oficial por parte de usuarios con menor privilegio.
- **Recomendación:** En el Form Request/Controlador, forzar `status=draft` si el usuario tiene rol `editor`.
- **Esfuerzo:** Bajo

---

#### INJ-01 — XSS almacenado en `Post::rendered_content`

- **Caso de prueba:** INJ-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:P/VC:L/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Score:** 5.1 (Media)
- **Ubicación:** `app/Models/Post.php:23-28`, `resources/views/public/news-show.blade.php:83`, `resources/views/public/page.blade.php:17`
- **Evidencia:** `getRenderedContentAttribute()` devuelve el HTML almacenado sin sanitizar cuando detecta etiquetas, y las vistas lo renderizan con `{!! !!}`. TinyMCE no es un sanitizer; un editor/admin puede inyectar `<script>` u otros elementos activos.
- **Impacto:** Ejecución de scripts en el navegador de visitantes; posible robo de sesiones si las cookies no fueran `HttpOnly` (actualmente sí lo son), keylogging, defacement o acciones no autorizadas en nombre de un usuario autenticado.
- **Recomendación:**
  1. Sanitizar el HTML server-side con HTMLPurifier o una librería equivalente antes de almacenar/renderizar.
  2. Añadir CSP (`script-src 'self'`) como control de profundidad.
  3. Restringir el uso de `{!! !!}` exclusivamente a salidas sanitizadas.
- **Esfuerzo:** Medio

---

#### INJ-02 — Uploads SVG con extensión decidida por el cliente

- **Caso de prueba:** INJ-02
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:P/VC:L/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Score:** 5.1 (Media)
- **Ubicación:** `app/Http/Controllers/Admin/MediaController.php:27`, `NewsController.php:126`, `PartnerController.php:107`, `SettingsController.php:71`
- **Evidencia:** Los 5 puntos de subida usan `$file->getClientOriginalExtension()` para construir el nombre final. SVG está permitido en medios, destacadas, logos y settings. Un archivo `.svg` con JavaScript embebido se sirve desde `/storage/...` con el MIME type `image/svg+xml`, ejecutándose en el origen del portal.
- **Impacto:** XSS almacenado vía archivo; el payload se ejecuta cuando cualquier usuario visita la URL del archivo.
- **Recomendación:**
  1. **Prohibir SVG** en uploads de usuario o sanitizarlo (DOMPurify server-side) y servirlo con `Content-Disposition: attachment`.
  2. Usar `$file->getClientOriginalExtension()` solo como hint; forzar la extensión según `getMimeType()`/`guessExtension()`.
  3. Validar que la extensión final coincida con el MIME real.
- **Esfuerzo:** Medio

---

#### FILE-03 — Gacetas oficiales sin control de integridad ni acceso

- **Caso de prueba:** FILE-03
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:N/VI:H/VA:N/SC:N/SI:N/SA:N`
- **Score:** 7.1 (Alta)
- **Ubicación:** `app/Http/Controllers/Admin/GacetaController.php:23-31,54-74`
- **Evidencia:** Las gacetas se almacenan en `storage/app/public/gacetas/` con nombres `time().uniqid()` y se sirven públicamente vía `/storage/...`. No existe hash de integridad ni control de acceso: cualquier usuario que conozca/construya la URL puede descargar cualquier gaceta (incluso borradores o versiones históricas si el slug es predecible). No hay firma digital ni checksum publicado.
- **Impacto:** Manipulación o divulgación no autorizada de documentos oficiales; afecta la integridad institucional y el cumplimiento de la Ley de Infogobierno.
- **Recomendación:**
  1. Generar y publicar un hash SHA-256 por gaceta; validar en descarga.
  2. Servir gacetas no publicadas a través de controlador con autenticación/permisos, no directamente desde `/storage`.
  3. Considerar firma digital PDF o sello de tiempo para documentos oficiales.
- **Esfuerzo:** Medio-Alto

---

#### CFG-01 — Ausencia de headers de seguridad

- **Caso de prueba:** CFG-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:P/VC:N/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Score:** 5.3 (Media)
- **Ubicación:** `public/.htaccess`, `app/Http/Kernel.php`
- **Evidencia:** No hay middleware ni configuración del servidor que agregue `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options` ni `Referrer-Policy`. El `.htaccess` es el stock de Laravel.
- **Impacto:** Mayor impacto de XSS (sin CSP), clickjacking del panel admin (sin X-Frame-Options), downgrade de HTTPS (sin HSTS), MIME-sniffing (sin X-Content-Type-Options).
- **Recomendación:**
  1. Implementar middleware `AddSecurityHeaders` o configurar headers en Nginx/Apache.
  2. CSP inicial restrictivo: `default-src 'self'; script-src 'self' 'unsafe-inline' (solo si TinyMCE lo requiere); style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-src https://www.youtube.com https://www.google.com`.
  3. HSTS con `max-age=31536000; includeSubDomains; preload` tras validar HTTPS completo.
- **Esfuerzo:** Bajo-Medio

---

#### CFG-02 — `SESSION_SECURE_COOKIE` sin default `true`

- **Caso de prueba:** CFG-02
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:H/AT:N/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Score:** 6.3 (Media)
- **Ubicación:** `config/session.php:171`
- **Evidencia:** `'secure' => env('SESSION_SECURE_COOKIE')` no tiene fallback `true`. Si la variable no está definida en `.env`, la cookie se envía incluso sobre conexiones HTTP. `.env.example` no incluye `SESSION_SECURE_COOKIE`.
- **Impacto:** En redes no confiables, un atacante puede capturar la cookie de sesión (MitM) y suplantar al administrador.
- **Recomendación:** Cambiar a `'secure' => env('SESSION_SECURE_COOKIE', true)` y añadir `SESSION_SECURE_COOKIE=true` en `.env.example` y producción.
- **Esfuerzo:** Mínimo

---

#### CFG-03 — `APP_DEBUG=true` + endpoints de Ignition habilitados

- **Caso de prueba:** CFG-03
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N`
- **Score:** 9.3 (Crítica)
- **Ubicación:** `.env.example:4`, rutas `_ignition/*` registradas por `spatie/laravel-ignition`
- **Evidencia:** `.env.example` establece `APP_DEBUG=true`. Cuando debug está activo, Laravel Ignition registra rutas como `_ignition/execute-solution` y `_ignition/update-config`, protegidas solo por `RunnableSolutionsEnabled`. Versiones anteriores de Ignition han tenido vulnerabilidades de RCE. Incluso sin explotación, el debug expone stack traces, rutas internas y variables de entorno.
- **Impacto:** RCE potencial, exposición de secretos, fuga de información sensible; afecta confidencialidad, integridad y disponibilidad.
- **Recomendación:**
  1. Cambiar `.env.example` a `APP_DEBUG=false`.
  2. Verificar `APP_DEBUG=false` en producción y desinstalar/ignorar Ignition en build de producción.
  3. Deshabilitar `spatie/laravel-ignition` en `composer.json` `dont-discover` para producción o moverlo a `require-dev`.
- **Esfuerzo:** Bajo

---

#### SC-01 — Dependencias PHP/JS con advisories conocidos

- **Caso de prueba:** SC-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:L/VA:L/SC:N/SI:N/SA:N` (base 6.9; se eleva a Alta por advisories individuales High)
- **Score:** 6.9 (Media) → **Alta** por contexto
- **Ubicación:** `composer.json`, `composer.lock`, `package-lock.json`
- **Evidencia:**
  - `composer audit` detectó advisories en `guzzlehttp/guzzle` (High: noncanonical host bypass; Medium: cookies, redirects, DoS), `laravel/framework` (Low: XSS en debug page), `league/commonmark` (múltiples High: DoS, XSS vía atributos) y `league/flysystem` (Low: path traversal UTF-8).
  - `npm audit` detectó **7 vulnerabilidades** (1 low, 1 moderate, 5 high) en `axios`, `esbuild`, `laravel-vite-plugin`, `nanoid`, `postcss`, `source-map-js` y `vite`. `vite` tiene path traversal y command injection en Windows.
  - `composer.json:65-87` contiene un bloque `audit.ignore` con **17 IDs PKSA-\*** que ocultan advisories del reporte habitual.
- **Impacto:** La cadena de suministro amplía la superficie de ataque; las vulnerabilidades en `commonmark` y `flysystem` son explotables indirectamente a través del manejo de Markdown/archivos.
- **Recomendación:**
  1. Ejecutar `composer update` para `guzzlehttp/guzzle`, `league/commonmark`, `league/flysystem` dentro de las restricciones de Laravel 10.
  2. Actualizar `vite` y dependencias de build; migrar `laravel-vite-plugin` si es compatible.
  3. Revisar uno a uno los 17 IDs en `audit.ignore`, eliminar los que ya no apliquen y documentar la justificación de los aceptados.
  4. Integrar `composer audit` y `npm audit` en el flujo de despliegue manual como paso obligatorio.
- **Esfuerzo:** Medio

---

#### SC-02 — Laravel 10.50.2 EOL sin parches de seguridad

- **Caso de prueba:** SC-02
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:L/VA:L/SC:N/SI:N/SA:N`
- **Score:** 6.9 (Media)
- **Ubicación:** `composer.lock`, `PENDIENTES.md` F/A5
- **Evidencia:** Laravel 10 alcanzó EOL en febrero 2026. La institución descartó el upgrade a Laravel 11/12 como requisito. Nuevas vulnerabilidades del framework no recibirán correcciones oficiales.
- **Impacto:** Acumulación de deuda de seguridad; eventual incompatibilidad con parches de dependencias transitivas.
- **Recomendación (controles compensatorios obligatorios):**
  1. Mantener un registro mensual de advisories de Laravel 10 y sus dependencias.
  2. Colocar un WAF/reverse-proxy (ModSecurity, Nginx + reglas OWASP CRS) delante de la aplicación.
  3. Restringir acceso administrativo por IP/VPN si es posible.
  4. Planificar re-evaluación semestral para decidir si el requisito institucional puede revisarse.
- **Esfuerzo:** Alto
- **Estado:** **✅ Cerrado 2026-10-26** — el requisito institucional de permanecer en Laravel 10 fue levantado; migración completa **10 → 11 → 12 → 13.38.0** (`laravel/framework ^13.10.1`, estrictamente >13.10.0 y fuera del rango de todos los advisories EOL) ejecutada en la rama `Laravel13-Dev` según `docs/PLAN-LARAVEL13.md` (Fases 0–3); `audit.ignore` eliminado de `composer.json` → `composer audit --locked` con **0 advisories activos y 0 waivers**; los controles compensatorios de arriba se mantienen como buena práctica, ya no como compensación de EOL. *Pendiente antes del merge: validación E2E + LATIN9 + ZAP en servidor (Fase 4).*

---

#### LOG-01 — Sin registro de auditoría de acciones administrativas

- **Caso de prueba:** LOG-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:N/VI:N/VA:L/SC:N/SI:N/SA:N`
- **Score:** 6.9 (Media)
- **Ubicación:** Aplicación completa
- **Evidencia:** No existe middleware, observer ni evento que registre logins, CRUD administrativos, cambios de rol, publicaciones o acceso a mensajes de contacto. Laravel registra solo errores/debug.
- **Impacto:** Imposibilidad de detectar y responder a incidentes de seguridad; incumplimiento del principio de rendición de cuentas de la LOPDP y de Infogobierno.
- **Recomendación:**
  1. Implementar tabla `activity_log` (manual o paquete `spatie/laravel-activitylog`) con: actor, acción, recurso, IP, user-agent, timestamp.
  2. Registrar como mínimo: login/logout, fallos de auth, CRUD de usuarios/roles, publicación de noticias/gacetas, acceso a mensajes de contacto.
  3. Proteger logs de acceso (fuera de `storage/logs` accesible por web) y retención definida.
- **Esfuerzo:** Medio

---

#### ABUSE-01 — `POST /contacto` y `/buscar` sin rate limiting

- **Caso de prueba:** ABUSE-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:N/VI:N/VA:L/SC:N/SI:N/SA:N`
- **Score:** 6.9 (Media)
- **Ubicación:** `routes/web.php:34,36`, `ContactController`, `SearchController`
- **Evidencia:** Ninguna ruta pública tiene throttle. `POST /contacto` permite envío masivo de PII y spam; `/buscar` ejecuta `LIKE` sobre títulos/contenido sin límite de longitud ni paginación estricta.
- **Impacto:** DoS por carga de BD, almacenamiento abusivo de PII ciudadana, saturación de la bandeja de mensajes.
- **Recomendación:**
  1. Aplicar `throttle:5,1` a `POST /contacto` y `throttle:30,1` a `/buscar`.
  2. Añadir CAPTCHA al formulario de contacto.
  3. Limitar longitud del término de búsqueda y paginar resultados.
- **Esfuerzo:** Bajo

---

#### PRIV-01 — PII sin cifrado en reposo

- **Caso de prueba:** PRIV-01
- **Vector CVSS v4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:H/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Score:** 7.1 (Alta)
- **Ubicación:** Tablas `contacts` y `users`
- **Evidencia:** `contacts` almacena `name`, `email`, `subject`, `message`; `users` almacena `name`, `email`, `password` (hash bcrypt). PostgreSQL no cifra estas columnas por defecto. Un acceso no autorizado a la BD o a un backup expone PII en texto claro.
- **Impacto:** Brecha de PII ciudadana y de funcionarios; obligación de notificación según LOPDP; daño reputacional institucional.
- **Recomendación:**
  1. Cifrar columnas sensibles con `Encrypted` casts de Laravel (atributos `name`, `email`, `message` de `contacts`; opcionalmente `email` de `users`).
  2. Usar cifrado en reposo del servidor de PostgreSQL (o cifrado de volumen/backups), ya que el motor no lo aplica por defecto.
  3. Restringir backups y credenciales de BD; no incluir datos reales en entornos de desarrollo.
- **Esfuerzo:** Medio-Alto

---

## 5. Resumen de pruebas y calidad de código

- **PHPUnit (2026-10-25):** **237 tests, 1032 assertions, 100 % OK** (3 skipped requieren PostgreSQL o el symlink `public/storage`; 6 avisos preexistentes de `iconv` en LATIN9, intencionales). La suite cubre autenticación, permisos, contacto, rate limiting, XSS, sanitización HTML, uploads (extensión por contenido), gacetas (hash), PII (cifrado), headers de seguridad, CORS, cambio forzado de contraseña, restricción de roles, auditoría (37 tests), y desde la ronda R3 también CRUD de entes, bandeja de contacto y validación de configuración.
- **Cobertura (Xdebug 3.5.3):** **82.22% de líneas** en `app/` (objetivo ≥80%); authn/authz/validación/cifrado ≈100% salvo el fallback de iconv de `App\Support\Latin9` (documentado).
- **E2E (bash + psql):** **17/17 suites en verde** contra PostgreSQL local (2026-10-25).
- **DAST baseline:** OWASP ZAP 2.17.0 → 0 altas / 1 media (waiver) / 2 bajas / 4 informativas (`zap-baseline-2026-10-25.html`).
- **Pint / lint:** `vendor/bin/pint --test` **pasa limpio**; se ejecuta como gate antes de cada despliegue.

---

## 6. Hoja de ruta de remediación

> **Cierre de §6 (2026-10-23):** todas las filas de prioridad Crítica, Alta y Media fueron ejecutadas y verificadas. ~~El único punto sin cerrar es la migración a Laravel 12/13 (SC-02)~~ **SC-02 cerrado el 2026-10-26**: migración completa a **Laravel 13.38.0** en rama `Laravel13-Dev` (`docs/PLAN-LARAVEL13.md`), fuera de plazo respecto a la revisión del 2026-11-21. **Hoja de ruta: 100% cerrada**, sujeta a la validación en servidor de la Fase 4 (E2E + LATIN9 + ZAP).

| Prioridad | Hallazgos | Acción | Responsable sugerido | Estado |
|---|---|---|---|---|
| **Crítico (≤7 días)** | AUTH-02, CFG-03 | Cambiar passwords seed, forzar cambio, poner `APP_DEBUG=false`, deshabilitar Ignition en prod | DevSecOps / SysAdmin | ✅ Ejecutado |
| **Alto (≤30 días)** | AUTHZ-01, FILE-03, PRIV-01, SC-01 | Restringir asignación de superadmin, proteger gacetas, cifrar PII, actualizar dependencias vulnerables | Desarrollo | ✅ Ejecutado |
| **Medio (≤90 días)** | AUTH-01, AUTHZ-02, INJ-01, INJ-02, CFG-01, CFG-02, LOG-01, ABUSE-01, SC-02 | Rate limiting, sanitización HTML, headers de seguridad, auditoría, controles compensatorios EOL | Desarrollo / Infra | ✅ Ejecutado (SC-02 cerrado 2026-10-26: Laravel 13.38.0) |

---

## 7. Controles compensatorios para riesgos aceptados

### Laravel 10 EOL (SC-02) — ✅ CERRADO 2026-10-26

> El requisito institucional de permanecer en Laravel 10 fue levantado. La migración
> completa **10 → 11 → 12 → 13.38.0** se ejecutó en la rama `Laravel13-Dev`
> (`docs/PLAN-LARAVEL13.md`, Fases 0–3) con `composer audit --locked` en **0 advisories
> y 0 waivers**. Los controles siguientes dejan de ser compensación de EOL y se
> conservan como buenas prácticas de SCA/operaciones (1, 2, 3 y 4); el 5 (reevaluación
> semestral del requisito) se da por cumplido con este cierre.

1. **SCA continuo:** ejecutar `composer audit` y `npm audit` semanalmente; revisar y justificar cualquier ID ignorado.
2. **WAF/Reverse proxy:** desplegar reglas OWASP CRS y reglas específicas para Laravel ante el servidor web.
3. **Acceso administrativo restringido:** limitar `/admin/*` a rangos IP institucionales o VPN si la arquitectura lo permite.
4. **Monitoreo de integridad:** detectar cambios no autorizados en archivos de la aplicación (`app/`, `routes/`, `config/`, `public/`).
5. ~~**Reevaluación semestral** del requisito de permanecer en Laravel 10.~~ Cumplido: requisito levantado y migración ejecutada el 2026-10-26.

---

## 8. Cumplimiento normativo

| Norma | Hallazgos relacionados | Estado |
|---|---|---|
| **LOPDP** (Venezuela, Gaceta 6.210) | PRIV-01 (cifrado de PII), LOG-01 (auditoría), AUTH-02/AUTH-01 (seguridad de credenciales) | ✅ Remediado (PII cifrado, auditoría activa con retención 400 d) |
| **Ley de Infogobierno** (Gaceta 40.274) | FILE-03 (integridad de documentos oficiales), CFG-01/CFG-03 (protección de TIC), LOG-01 (rendición de cuentas) | ✅ Remediado (hash SHA-256 en gacetas, headers, auditoría) |

---

## 9. Conclusiones

> **Actualización (2026-10-23):** los párrafos siguientes describen el estado **inicial** (2026-10-08). Los dos riesgos Críticos, los cuatro Altos y los nueve Medios listados están **remediados** (§1.b); el único riesgo residual vigente es **SC-02 (Laravel 10 EOL)**, aceptado con los compensatorios de §7 y con revisión obligatoria el 2026-11-21.

El Portal2026 presentaba, al momento del análisis, **dos riesgos Críticos** (credenciales seed y debug/Ignition) que debían corregirse antes de cualquier despliegue público. Además existían **cuatro riesgos Altos** de escalada de privilegios, integridad de gacetas, dependencias vulnerables y protección de PII. La mayoría de los hallazgos tenían correcciones de bajo o medio esfuerzo y estaban bien delimitados en el código.

El principal riesgo sistémico identificado era la **combinación** de: autenticación sin rate limiting + contraseñas por defecto + ausencia de headers de seguridad + EOL de Laravel 10. Esta combinación aumentaba la probabilidad de compromiso del panel administrativo y la posterior manipulación de contenido oficial y PII.

Se ejecutó la hoja de ruta §6 y se actualizaron `PENDIENTES.md` y `DOCUMENTACION.md`. Persiste la recomendación de una **revalidación en staging** que incluya pruebas DAST con OWASP ZAP/Burp para los casos no validados dinámicamente en este análisis (§2.3), y de la **migración de framework** fuera de Laravel 10 (§7).

> **Actualización (2026-10-25):** se ejecutó el **baseline DAST con OWASP ZAP 2.17.0** contra el servidor local (0 altas, 1 media correspondiente al waiver de CSP ya registrado, 2 bajas documentadas y corregidas/en condición de despliegue — §1.d), la **suite E2E completa quedó en 17/17** y la **cobertura alcanzó 82.22%**. Solo queda pendiente, de esta recomendación, la revalidación **autenticada contra staging real** tras el despliegue; la migración de framework sigue bajo waiver con revisión el 2026-11-21.

---

## 10. Anexos

### 10.1 Archivos de evidencia generados

| Archivo | Descripción |
|---|---|
| `composer-audit.json` | Salida JSON de `composer audit --format=json` (0 advisories activos + 5 ignorados justificados) |
| `npm-audit.json` | Salida JSON de `npm audit --json` (0 vulnerabilidades) |
| `SBOM-Portal2026.json` | SBOM CycloneDX 1.5 con 214 componentes (regenerado 2026-10-23) |
| `cvss-scores.json` | Scores CVSS v4.0 calculados para cada hallazgo |
| `zap-baseline-2026-10-25.html` / `.json` | Informe del baseline DAST (ZAP 2.17.0, spider + passive scan, 2026-10-25) |
| `informe-estado-actual.html` | Entregable imprimible del estado actual (instantánea sin historial) |

### 10.2 Métricas del SBOM

- **Total de componentes:** 214
- **Componentes PHP (Composer):** extraídos de `composer.lock`
- **Componentes JS (npm):** extraídos de `package-lock.json`
- **Formato:** CycloneDX 1.5 (`bomFormat`, `specVersion`, `components`, `metadata`)

### 10.3 Referencias

- [PLAN-VULNERABILIDADES.md](PLAN-VULNERABILIDADES.md) (histórico, ejecutado)
- [DOCUMENTACION.md](DOCUMENTACION.md) §14 (despliegue en producción) y §16 (tests)
- [PENDIENTES.md](PENDIENTES.md) (trabajo abierto) e [historico-cierres-pendientes.md](historico-cierres-pendientes.md)
- [SECURITY.md](../SECURITY.md) (waivers y condiciones de despliegue)
- OWASP Top 10:2025
- OWASP ASVS 5.0.0 Nivel 2
- CVSS v4.0 (FIRST)
- LOPDP (Venezuela, Gaceta Oficial N.º 6.210, 2022)
- Ley de Infogobierno (Venezuela, Gaceta Oficial N.º 40.274, 2013)

---

*Informe generado automáticamente a partir de la ejecución del Plan de Análisis de Vulnerabilidades del Portal2026 — octubre 2026.*
