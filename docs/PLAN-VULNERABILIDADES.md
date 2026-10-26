# Plan de Análisis de Vulnerabilidades de Ciberseguridad — Portal2026

> **Versión:** 1.0 · **Fecha:** octubre 2026 · **Estado:** ⚠️ **histórico — plan ejecutado y cerrado (2026-10-08 … 2026-10-23). No actualizar.**
> Resultado y remediación viva: [INFORME-VULNERABILIDADES.md](INFORME-VULNERABILIDADES.md). Fechas de cierre: [historico-cierres-pendientes.md](historico-cierres-pendientes.md).
> **Aplica a:** Portal institucional GDC (sitio público + panel `/admin`) — Laravel 10 + Blade + MySQL *(nota histórica: el portal migró a PostgreSQL — ver DOCUMENTACION.md §13)*
> **Documentos relacionados:** [DOCUMENTACION.md](DOCUMENTACION.md) (fuente de verdad técnica), [PENDIENTES.md](PENDIENTES.md) (ítems abiertos), [SPECS.md](SPECS.md) (histórico)

---

## 1. Objeto y finalidad

Este documento define el **plan de análisis de vulnerabilidades** del Portal2026, alineado con los estándares de ciberseguridad vigentes en 2026 y adaptado a la finalidad y alcance reales del proyecto: un **portal web institucional gubernamental** (Gobierno de Distrito Capital) con:

- **Sitio público informativo**: noticias, entes afiliados, gacetas oficiales, estructura organizativa, contacto.
- **Panel administrativo** (`/admin`): gestión de contenidos con roles `superadmin`, `admin` y `editor` (41 permisos granulares).
- **Datos personales (PII)**: formulario de contacto ciudadano (`contacts`) y cuentas de funcionarios (`users`).
- **Documentos oficiales**: PDF/imágenes de Gacetas Oficiales, de integridad institucional crítica.

**Finalidad del plan:**

1. Identificar, clasificar y priorizar vulnerabilidades antes de su explotación maliciosa.
2. Verificar los controles de seguridad existentes frente a los estándares 2026 (OWASP Top 10:2025, ASVS 5.0 Nivel 2).
3. Atender los riesgos ya conocidos (`DOCUMENTACION.md` §12, `PENDIENTES.md` A1–A4) y los derivados de la restricción institucional de permanecer en Laravel 10 (EOL).
4. Sustentar el cumplimiento normativo venezolano aplicable (LOPDP, Ley de Infogobierno).
5. Dejar un ciclo cerrado: análisis → informe → remediación → revalidación.

Este plan **no ejecuta** el análisis: define su alcance, metodología, casos de prueba, criterios de riesgo y entregables. La ejecución se realizará como fase posterior siguiendo este documento.

---

## 2. Marco normativo y de referencia (vigencia 2026)

### 2.1 Estándares técnicos

| Área | Estándar | Versión vigente 2026 | Uso en este plan |
|---|---|---|---|
| Riesgos de aplicaciones web | OWASP Top 10 | **:2025** | Taxonomía de riesgos (A01–A10) |
| Requisitos verificables | OWASP ASVS | **5.0.0** (mayo 2025) | Lista de verificación — **Nivel 2** |
| Guía de pruebas | OWASP WSTG | *latest* | Procedimientos de prueba por caso |
| Metodología de pentest | PTES + NIST SP 800-115 | vigentes | Estructura de fases |
| Scoring de severidad | CVSS | **v4.0** (FIRST) | Clasificación de cada hallazgo (vector Base + Threat + Environmental) |
| Priorización de remediación | EPSS + CISA KEV | vigentes | Priorización de dependencias vulnerables |
| Gobernanza / SGSI | ISO/IEC 27001:2022, NIST CSF 2.0 | vigentes | Contexto de gestión del riesgo |
| Divulgación de vulnerabilidades | ISO/IEC 29147 / 30111 | vigentes | Procedimiento de reporte interno |
| Hardening | CIS Benchmarks (PHP 8.x, Nginx, PostgreSQL), CIS Controls v8.1 | vigentes | Revisión de configuración de infraestructura |
| Supply chain | SBOM (CycloneDX), OWASP Dependency-Check, `composer audit` / `npm audit` | vigentes | Fase 2.1 — SCA |

### 2.2 Marco legal aplicable (Venezuela)

| Norma | Relevancia para este plan |
|---|---|
| **Ley Orgánica de Protección de Datos Personales (LOPDP)** — Gaceta Oficial N.º 6.210 (2022) y su Reglamento | Tratamiento de PII: `contacts` (nombre, correo, mensaje) y `users` (nombre, correo, hash de contraseña). Deber de seguridad, confidencialidad y notificación de brechas. |
| **Ley de Infogobierno** — Gaceta Oficial N.º 40.274 (2013) | Obligaciones del Poder Público en TIC: protección y seguridad de la información, interoperabilidad y confiabilidad de los servicios electrónicos ciudadanos (art. 25: protección de datos personales en el uso de TIC por el Poder Público). |
| Normativa CNTI / Sistema Nacional de Protección y Seguridad de la Información | Lineamientos de seguridad de la información para órganos y entes públicos (hardening, gestión de incidentes). |

> **Nota:** el contenido de `contacts` y `users` es PII según la LOPDP; una brecha que exponga estas tablas o el historial de mensajes constituye un **incidente de seguridad de datos personales** sujeto a notificación. El plan trata la confidencialidad de estas tablas como criterio de severidad (ver §7).

---

## 3. Alcance

### 3.1 Dentro del alcance (*in scope*)

| Componente | Detalle |
|---|---|
| Aplicación web pública | 13 rutas (`routes/web.php:25-37`): `/`, `/noticias`, `/noticias/{slug}`, `/categoria/{slug}`, `/entes`, `/entes/{slug}`, `/sobre-nosotros`, `/contacto` + `POST /contacto`, `/gacetas`, `/buscar`, `/pagina/{slug}`, redirect `/servicios` |
| Autenticación | `GET|POST /admin/login` (`routes/web.php:40-41`) — fuera del middleware `auth` |
| Panel administrativo | Todas las rutas `/admin/*` con middleware `auth` + `permission:<slug>` (`routes/web.php:43-127`) |
| Puntos de subida de archivos | Medios (`MediaController`), imagen destacada (`NewsController`), logos de entes (`PartnerController`), gacetas PDF/imagen (`GacetaController`), videos (`VideoController`), logo del sitio (`SettingsController`) |
| Lógica de autorización | `CheckPermission`, `User::hasPermission()`, roles y permisos (41 permisos / 3 roles) |
| Capa de datos | PostgreSQL (producción: 13.23, encoding LATIN9 con conexión UTF8) — 19 tablas de la app + `migrations`, en particular `users`, `contacts`, `posts`, `gacetas`, `permissions`, `role_permission`, `activity_logs` |
| Código frontend servido | Vistas Blade (incl. `{!! rendered_content !!}`), TinyMCE auto-hospedado, JS/Sass propios |
| Dependencias | `composer.json`/`composer.lock` (Laravel 10.50.2 + paquetes), `package.json`/`package-lock.json` (TinyMCE, Bootstrap, Vite) |
| Configuración de la app | `config/` (session, cors, hashing, filesystems, app), `.env.example`, `public/.htaccess` |
| Infraestructura de despliegue | Servidor institucional Nginx + PHP-FPM 8.3 + PostgreSQL (requisitos §14.1 de DOCUMENTACION.md; desplegado el 2026-10-25 — §14.L), permisos de `storage/`, `bootstrap/cache`, `.env`, cron `/etc/cron.d/portal` |
| Assets estáticos servidos | `/storage/*` (symlink a uploads), `public/video/*.mp4`, `public/vendor/tinymce` |

### 3.2 Fuera del alcance (*out of scope*)

- Pruebas destructivas de denegación de servicio contra el entorno productivo real (solo simulaciones acotadas en staging).
- Ingeniería social dirigida a personal de la institución (phishing, vishing).
- Ataques físicos a las instalaciones.
- Pentest de red interna del datacenter / segmentos de red (exigible a un plan de infraestructura separado).
- Pruebas contra servicios de terceros (YouTube, Google Maps embebidos) o dominios no listados en §3.1.
- Análisis forense o respuesta a incidentes en curso (se rige por el procedimiento de incidentes, no por este plan).
- Evaluación de código de TinyMCE/Bootstrap de terceros más allá de su gestión de versiones (SCA).

### 3.3 Entornos de prueba

| Entorno | Uso | Restricción |
|---|---|---|
| **Staging / réplica** | Explotación controlada y validación (F3) | Obligatorio para cualquier prueba activa |
| **Producción** | Solo pasivo: reconocimiento, headers, SCA, revisión de configuración | Prohibida la explotación; ventana acordada para escaneos activos |
| **Local (Laragon/Windows)** | Reproducción de PoCs y pruebas de regresión | Datos de prueba, nunca PII real |

---

## 4. Superficie de ataque (inventario del proyecto)

Inventario verificado contra el código; los casos de prueba de §6 se referencian a él.

### 4.1 Puntos de entrada

| ID | Superficie | Ubicación | Observaciones de riesgo |
|---|---|---|---|
| S1 | `POST /contacto` | `routes/web.php:34`, `ContactController` | Único endpoint público de escritura; captura PII; **sin rate limiting** |
| S2 | `GET /buscar` | `routes/web.php:36` (search) | Consulta tipo LIKE; **sin rate limiting**; posible DoS por carga de BD |
| S3 | `GET|POST /admin/login` | `routes/web.php:40-41`, `AuthController` | **Sin throttle ni lockout**; objetivo de fuerza bruta |
| S4 | Panel `/admin/*` (40+ rutas) | `routes/web.php:43-127` | Autorización por middleware `permission` + bypass hardcodeado para `superadmin` (`app/Http/Middleware/CheckPermission.php:18-21`) |
| S5 | 5 puntos de subida de archivos | `MediaController.php:21-28`, `NewsController`, `PartnerController`, `GacetaController`, `VideoController`, `SettingsController` | Extensión final decidida por `getClientOriginalExtension()`; **SVG permitido**; PDF oficiales; videos hasta 100 MB |
| S6 | Contenido HTML de noticias/páginas | `app/Models/Post.php:23-28` → `{!! !!}` en `news-show.blade.php`, `page.blade.php` | HTML crudo sin sanitización server-side → **XSS almacenado** |
| S7 | Archivos estáticos públicos | `/storage/*` (symlink), `public/video/*.mp4` (~29 MB en git) | Servidos sin autenticación ni anti-hotlinking; nombres con `time().uniqid()` |
| S8 | Gestión de usuarios/roles | `UserController` (`role` validado solo con `in:superadmin,admin,editor`, sin restricción por actor — `UserController.php:30,56`), `RoleController` | Posible **escalada de privilegios** (asignación de `superadmin`) |
| S9 | `org_members.photo` | `OrgMemberController` (`max:255`, texto libre) | Ruta/URL arbitraria reflejada en HTML |
| S10 | Dependencias y runtime | `composer.lock` (Laravel 10.50.2 **EOL**), 17 advisories ocultos (`composer.json:65-87`), `package-lock.json` | Supply chain |

### 4.2 Controles de seguridad existentes (línea base)

| Control | Estado | Evidencia |
|---|---|---|
| CSRF | Activo en todo el grupo `web`, sin excepciones | `app/Http/Middleware/VerifyCsrfToken.php` (`$except` vacío) |
| Hash de contraseñas | bcrypt | `config/hashing.php` |
| Política de contraseñas | `min:8|confirmed` | `UserController.php:29,55` |
| Regeneración de sesión | `regenerate()` en login, `invalidate()+regenerateToken()` en logout | `AuthController` |
| Validación de entrada | Form Request/`validate()` en controladores | generalizado |
| Consultas BD | Eloquent (parámetros enlazados) + 1 consulta raw en `CheckPermission` | `CheckPermission.php:23-27` |
| Cookies de sesión | `http_only=true`, `same_site=lax`, encrypt=false | `config/session.php` |
| Rate limiting | **Ausente en toda la aplicación** | alias `throttle` registrado, nunca aplicado |
| Headers de seguridad | **Ausentes** (sin CSP, HSTS, X-Frame-Options, X-Content-Type-Options) | `public/.htaccess` stock |
| `SESSION_SECURE_COOKIE` | Sin default `true` (`config/session.php:171`) | `PENDIENTES.md` A2 |
| `TrustHosts` | Comentado | `app/Http/Kernel.php:41` |
| Logging de auditoría | **Ausente** (sin registro de accesos/acciones admin) | Top 10:2025 A09 |
| CI / análisis automático | Sin CI, sin PHPStan (Pint sí) | `PENDIENTES.md` C1 |

---

## 5. Metodología (fases)

Basada en PTES y NIST SP 800-115, adaptada al tamaño del proyecto. Duración total estimada: **3–4 semanas**.

### Fase 0 — Preparación y reglas de engagement (2 días)

- Autorización escrita de la institución (alcance §3, entornos, ventanas).
- Definir equipo, canales de comunicación y contacto de emergencia.
- Preparar staging (réplica con datos sintéticos; **nunca copia de PII real sin anonimizar**).
- Acordar manejo de hallazgos críticos encontrados en producción (escalado inmediato vía §11).
- Firmar confidencialidad de la información vista durante la evaluación (LOPDP art. de deber de secreto).

### Fase 1 — Reconocimiento y mapeo (3 días)

- Confirmar el inventario §4 contra el entorno real (rutas expuestas, versiones servidas, archivos públicos).
- Enumeración de directorios y archivos: `/storage/*`, `public/vendor/tinymce`, `public/video/`, respaldos, `.env` accesible, `composer.lock`/`package-lock.json` expuestos.
- Identificación de tecnologías y versiones (banner grabbing, cabeceras de respuesta).
- Descubrimiento de rutas huérfanas o artifacts de despliegue (`.phpunit.result.cache`, respaldos `*.sql`, `README`, etc.).

### Fase 2 — Análisis de vulnerabilidades (2 semanas)

| # | Técnica | Herramientas | Foco |
|---|---|---|---|
| 2.1 | **SCA / Supply chain** | `composer audit`, `npm audit`, generador SBOM CycloneDX, NVD/CISA KEV, EPSS | 17 advisories ignorados en `composer.json:65-87`; impacto del EOL de Laravel 10.50.2; TinyMCE/Bootstrap/Vite |
| 2.2 | **SAST** | Semgrep (reglas PHP/Laravel), `php artisan route:list`, revisión manual | XSS (`rendered_content`), validación de uploads, autorización, mass-assignment, manejo de errores |
| 2.3 | **DAST** | OWASP ZAP (baseline + active scan) / Burp Suite | Las 13 rutas públicas, `POST /contacto`, `/buscar`, `GET|POST /admin/login` |
| 2.4 | **Revisión de configuración** | Manual + scripts | Headers, sesión, `.env`, permisos de filesystem, TLS, `TrustHosts`, CORS, debug, `composer audit` |
| 2.5 | **Pruebas manuales adversariales** | WSTG | Autorización (roles), uploads maliciosos, XSS almacenado, fuerza bruta, IDOR |
| 2.6 | **Hardening de infraestructura** | CIS Benchmarks (Nginx, PHP, PostgreSQL) | Servidor de despliegue §14 de DOCUMENTACION.md: permisos, usuarios de BD, exposición de servicios |

### Fase 3 — Explotación controlada y validación (4 días, solo staging)

- Convertir hallazgos en PoC reproducibles (evidencia: request/response, captura, script).
- Confirmar impacto real (ej.: ¿el SVG carga script en el origen del portal? ¿el rol `editor` puede publicar?).
- Calcular severidad con **CVSS v4.0** (vector completo) ponderada por el contexto del §7.
- Estimar probabilidad de explotación (**EPSS** para vulnerabilidades con CVE; juicio experto para hallazgos de aplicación).

### Fase 4 — Informe y gestión (3 días)

- Informe ejecutivo (riesgo de negocio, impacto LOPDP/Infogobierno) + informe técnico (ver plantilla §12.1).
- Clasificación de cada hallazgo: ID, CVSS v4.0, EPSS, evidencia, PoC, recomendación, esfuerzo de corrección.
- Socialización con el equipo de desarrollo y carga de hallazgos al seguimiento (`PENDIENTES.md`).

### Fase 5 — Revalidación y cierre (1 semana después de remediación)

- Re-ejecutar los casos de prueba de los hallazgos corregidos (mismo checklist).
- Verificar controles compensatorios para riesgos aceptados (EOL Laravel 10).
- Cierre formal: estado final, riesgo residual aceptado por la institución, próxima evaluación (recomendada: **anual** o tras cambios mayores).

---

## 6. Casos de prueba priorizados

Cada caso mapea a OWASP Top 10:2025 y ASVS 5.0 (Nivel 2). Prioridad: **P1** (obligatorio) / **P2** (importante) / **P3** (deseable).

### 6.1 Autenticación (A07 — Authentication Failures)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| AUTH-01 | Fuerza bruta / credential stuffing en `POST /admin/login` (ausencia de throttle/lockout) | ASVS V2.2, WSTG-ATHN-04 | P1 |
| AUTH-02 | Políticas de contraseña: entropía, contraseñas por defecto (seed `password` — `UserSeeder`), reutilización | ASVS V2.1 | P1 |
| AUTH-03 | Session fixation / rotación de ID de sesión en login/logout | ASVS V3.2, WSTG-ATHN-03 | P1 |
| AUTH-04 | Cookie de sesión: flags `Secure` (pendiente A2), `HttpOnly`, `SameSite`, expiración (120 min) | ASVS V3.4 | P1 |
| AUTH-05 | Secuencia de logout y invalidación de sesión/token (remember) | ASVS V3.3 | P2 |
| AUTH-06 | Enumeración de usuarios vía mensajes de error (debe ser genérico) | ASVS V2.7 | P2 |

### 6.2 Autorización y control de acceso (A01 — Broken Access Control)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| AUTHZ-01 | Escalada de privilegios: `users.edit`/`users.create` asignan rol `superadmin` sin restricción (`UserController.php:30,56`) | ASVS V4.1 | P1 |
| AUTHZ-02 | El rol `editor` puede enviar `status=published` vía API (`NewsController.php:37`) — regla "editor no publica" no forzada server-side | ASVS V4.1 | P1 |
| AUTHZ-03 | IDOR en route-model binding (`{post}`, `{contact}`, `{user}`, `{gaceta}`, `{media}`): acceso cruzado entre usuarios | ASVS V4.2, WSTG-ATHZ-04 | P1 |
| AUTHZ-04 | Bypass del middleware `permission` (parámetro manipulable, cobertura completa de rutas vs. 41 permisos) | ASVS V4.1 | P1 |
| AUTHZ-05 | Bypass/abuso del bypass hardcodeado de `superadmin` (`CheckPermission.php:18-21`) | ASVS V4.1 | P2 |
| AUTHZ-06 | Manipulación de `RoleController` (asignación de permisos) por rol distinto de superadmin | ASVS V4.1 | P1 |
| AUTHZ-07 | Acceso directo a métodos/acciones sin ruta declarada (mass-assignment, verbos HTTP) | ASVS V4.1 | P2 |

### 6.3 Inyección y XSS (A03 — Injection; A05 según Top 10:2025)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| INJ-01 | **XSS almacenado** vía `{!! rendered_content !!}` (`Post.php:23-28`): payload HTML en `content` de noticias/páginas | ASVS V5.3, WSTG-XSS-01 | P1 |
| INJ-02 | **XSS almacenado vía SVG** subido (`MediaController`, `PartnerController`, `SettingsController`): SVG con `<script>` servido desde `/storage/...` | ASVS V12.4 | P1 |
| INJ-03 | XSS reflejado en `/buscar`, formularios, parámetros `slug`, mensajes de error | ASVS V5.2 | P1 |
| INJ-04 | SQLi en la consulta raw de `CheckPermission` y cualquier `DB::raw`/`whereRaw` del proyecto | ASVS V5.3 | P1 |
| INJ-05 | Inyección de headers/CRLF, open redirect (`redirect('/servicios', ...)` y parámetros de redirección) | ASVS V12.2 | P2 |
| INJ-06 | SSTI en Blade (contenido de usuario interpolado en vistas) | WSTG-SSTI | P2 |
| INJ-07 | Stored XSS en campos "seguros" (nombre de usuario, categorías, settings, RRSS, `org_members.photo`) | ASVS V5.1 | P1 |

### 6.4 Subida y manejo de archivos (A08 — Software and Data Integrity Failures)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| FILE-01 | Discrepancia MIME/extensión: la extensión final proviene de `getClientOriginalExtension()` (los 5 puntos de subida) — renombrado de archivos (p. ej. PNG con extensión `.svg`, PHP doble extensión) | ASVS V12.4 | P1 |
| FILE-02 | Subida de SVG activo (ver INJ-02) — definir si se prohíbe o se sanitiza (DOMPurifier/sanear en origen) | ASVS V12.4 | P1 |
| FILE-03 | PDF de Gacetas con contenido activo (JS embebido, payloads), nombres predecibles (`time_uniqid.pdf`), integridad del documento oficial | ASVS V12.4 | P1 |
| FILE-04 | Bypass de `mimes:`/`max`: archivos con firma falsa, polyglots, archivos >10 MB/20 MB/100 MB (videos) | ASVS V12.3 | P1 |
| FILE-05 | Path traversal / control de rutas en `org_members.photo` (texto libre `max:255`) y en nombres de archivo | ASVS V12.4 | P1 |
| FILE-06 | Explotación del symlink `/storage` para servir archivos fuera de `storage/app/public` | ASVS V12.4 | P1 |
| FILE-07 | DoS por almacenamiento: videos de 100 MB, re-subidas masivas, sin cuotas ni limpieza | ASVS V12.3 | P2 |
| FILE-08 | Ejecución de código subido (PHP, `.phtml`, `.htaccess` en `storage/`) según el servidor web real | ASVS V12.4 | P1 |
| FILE-09 | Integridad de sidecars WebP (`app/Services/Webp.php`): generación desde imágenes maliciosas (GD) | WSTG | P3 |

### 6.5 Diseño, configuración y criptografía (A02, A04, A06)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| CFG-01 | Headers de seguridad: CSP, HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` (ausentes hoy) | ASVS V14.4 | P1 |
| CFG-02 | `SESSION_SECURE_COOKIE` sin default (`config/session.php:171`) — flag `Secure` tras HTTPS | ASVS V3.4, PENDIENTES A2 | P1 |
| CFG-03 | `APP_DEBUG`, `APP_KEY`, secretos en `.env`/repositorio; exposición de trazas de error (Ignition) | ASVS V7.3, PENDIENTES A3 | P1 |
| CFG-04 | HTTPS/TLS: versiones, cipher suites, certificado, redirección HTTP→HTTPS, HSTS preload | ASVS V9.1 | P1 |
| CFG-05 | `TrustHosts` deshabilitado (`Kernel.php:41`): cache poisoning / host header attack | ASVS V12.2 | P2 |
| CFG-06 | Permisos de `storage/`, `bootstrap/cache`, `.env` (chmod 600) en el servidor (§14.F DOCUMENTACION.md) | CIS Benchmark | P1 |
| CFG-07 | CORS (`config/cors.php`: `allowed_origins: ['*']`, paths `api/*` inexistentes): verificar comportamiento real | ASVS V13.2 | P3 |
| CFG-08 | Endurecimiento del servidor: usuarios de BD con privilegios mínimos, deshabilitar listado de directorios, servicios expuestos | CIS Benchmarks | P1 |
| CFG-09 | Protección CSRF completa (verificar en todas las rutas de escritura, incl. JSON/ajax de TinyMCE) | ASVS V4.2 | P1 |

### 6.6 Supply chain (A08)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| SC-01 | Los **17 advisories PKSA-\* ocultos** en `composer.json:65-87`: evaluar cada uno, quitar el `ignore` y remediar/aceptar con justificación | OWASP SCVS, PENDIENTES A4 | P1 |
| SC-02 | Laravel 10.50.2 **EOL** (feb 2026) sin upgrade posible (restricción institucional): definir controles compensatorios (WAF, SCA continuo, monitoreo) | NIST SP 800-40 | P1 |
| SC-03 | Vulnerabilidades en npm (TinyMCE 8.9.2, Bootstrap 5.3, Vite 4) y en scripts de build (`copy-tinymce.js`) | npm audit | P2 |
| SC-04 | Generación y mantenimiento de **SBOM** (CycloneDX) del proyecto | SLSA/EO 14028 | P2 |
| SC-05 | Integridad de `public/vendor/tinymce` (build local, no CDN): verificación de hash/origen | ASVS V14.2 | P2 |

### 6.7 Registro, monitoreo y manejo de errores (A09, A10)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| LOG-01 | Ausencia de registro de auditoría de accesos y acciones administrativas (login, CRUD, cambios de rol) | Top 10:2025 A09, ASVS V7.1 | P1 |
| LOG-02 | Fugas de información en errores: stack traces, versiones, rutas internas, mensajes de validación | ASVS V7.3, A10 | P1 |
| LOG-03 | Exposición de archivos sensibles: `.env`, `composer.lock`, respaldos, `phpunit.result.cache`, videos en git | WSTG-INFO-05 | P1 |
| LOG-04 | Manejo de errores en endpoints JSON (`$request->expectsJson()` en `MediaController`) | A10 | P2 |

### 6.8 Privacidad de datos (LOPDP)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| PRIV-01 | Protección de `contacts` (PII ciudadana): cifrado en reposo, acceso restringido (permiso `contact.view`), retención y eliminación | LOPDP | P1 |
| PRIV-02 | Protección de `users` (PII de funcionarios): hash bcrypt, `remember_token`, `last_login_at`, expurgo de datos | LOPDP | P1 |
| PRIV-03 | Trazabilidad para notificación de brechas (art. LOPDP): capacidad de detectar y reportar incidentes | LOPDP, Infogobierno | P1 |
| PRIV-04 | Publicación de PII en el sitio público (estructura organizativa, datos de contacto institucional vs. personal) | LOPDP | P2 |

### 6.9 Abuso de superficie pública (A04, A06)

| ID | Caso | Mapeo | P |
|---|---|---|---|
| ABUSE-01 | Spam/inyección masiva vía `POST /contacto` sin rate limiting; almacenamiento abusivo de PII | WSTG-BUSL, A06 | P1 |
| ABUSE-02 | DoS por consulta: `/buscar` con LIKE sobre tablas grandes; paginación sin límite | WSTG-DOS | P2 |
| ABUSE-03 | Enumeración de contenidos no publicados (drafts, soft-deleted posts, gacetas no publicadas) vía URL directa | ASVS V4.2 | P1 |
| ABUSE-04 | Clickjacking/robo de sesión del panel admin por ausencia de `X-Frame-Options` | CFG-01 | P2 |

---

## 7. Matriz de riesgo y SLA de remediación

### 7.1 Severidad (CVSS v4.0)

Cada hallazgo se documenta con su **vector CVSS v4.0 completo** (Base + Threat + Environmental). Los vectores Environmental ajustarán:

- **MVC/MVI/MVA**: activos con PII (`contacts`, `users`) e integridad institucional (gacetas oficiales) → impacto elevado en Confidencialidad/Integridad.
- **E (Exploit Maturity)**: corroborado con PoC, EPSS o CISA KEV.

| Severidad CVSS v4.0 | Rango | SLA de remediación | Escalado |
|---|---|---|---|
| **Crítica** | 9.0–10.0 | ≤ 7 días naturales | Inmediato a responsable + dirección |
| **Alta** | 7.0–8.9 | ≤ 30 días | Responsable de sistemas |
| **Media** | 4.0–6.9 | ≤ 90 días | Backlog de desarrollo |
| **Baja** | 0.1–3.9 | ≤ 180 días | Backlog / mejora continua |

### 7.2 Factores de priorización adicional

1. **EPSS** ≥ umbral del día para CVEs → adelantar remediación aunque el CVSS sea medio.
2. Presencia en **CISA KEV** → remediación inmediata (independiente del score).
3. Explotación confirmada en F3 → se eleva a Crítica.
4. Impacto LOPDP (exposición de PII) → severidad no inferior a Alta.

### 7.3 Riesgos aceptados (requieren decisión institucional documentada)

| Riesgo | Condición de aceptación |
|---|---|
| Laravel 10 EOL (sin upgrade posible) | Controles compensatorios obligatorios: SCA continuo, WAF/reverse-proxy con reglas OWASP, monitoreo, re-evaluación semestral |
| Riesgos corrientes de `DOCUMENTACION.md` §12 (ítems 4–6: sin CI, cobertura limitada, E2E solo Windows) | Plan de mejoras de calidad aparte; no bloquean el análisis |

---

## 8. Herramientas

| Fase | Herramienta | Uso | Licencia |
|---|---|---|---|
| 2.1 SCA | `composer audit`, `npm audit`, OWASP Dependency-Check, generador SBOM CycloneDX | Dependencias y SBOM | Gratuitas |
| 2.2 SAST | Semgrep (reglas PHP/Laravel), PHPStan (opcional) | Análisis estático | Gratuitas |
| 2.3 DAST | OWASP ZAP (baseline/active scan) | Análisis dinámico | Gratuita |
| 2.5 manual | Burp Suite Community (opcional), scripts propios | Pruebas manuales | Freemium |
| 2.6 infra | Nmap, Nikto, testssl.sh / ssllabs, CIS-CAT Lite (opcional) | Superficie y TLS | Gratuitas |
| F4 | Plantilla §12.1, hoja de cálculo de riesgos | Informe | — |
| Todo | `vendor/bin/phpunit` (21 tests), `composer lint` | Regresión post-remediación | — |

---

## 9. Calendario y responsables

| Fase | Duración | Responsable sugerido | Entregable |
|---|---|---|---|
| F0 Preparación | 2 días | Responsable de seguridad + institución | Reglas de engagement firmadas, staging listo |
| F1 Reconocimiento | 3 días | Analista de seguridad | Inventario confirmado |
| F2 Vulnerabilidades | 2 semanas | Analista de seguridad + desarrollador líder | Evidencia por caso (§6) |
| F3 Explotación validada | 4 días | Analista de seguridad | PoCs + scoring CVSS v4.0 |
| F4 Informe | 3 días | Analista de seguridad | Informe ejecutivo + técnico |
| F5 Revalidación | 1 semana tras remediación | Analista + equipo de desarrollo | Acta de cierre, riesgo residual |

> Las fases F2 y F5 requieren coordinación con el equipo que mantiene `PENDIENTES.md`; los hallazgos se cargan como ítems de la sección A (seguridad) o una sección nueva "Análisis de vulnerabilidades".

---

## 10. Entregables

1. **Este plan** (`PLAN-VULNERABILIDADES.md`) — aprobado por la institución.
2. **Informe de hallazgos**: ejecutivo (riesgo, impacto legal LOPDP/Infogobierno) + técnico (por ID de §6, con plantilla §12.1).
3. **SBOM** (CycloneDX) del proyecto.
4. **Plan de remediación** priorizado (SLA según §7.1), integrado a `PENDIENTES.md`.
5. **Acta de revalidación** con estado final de cada hallazgo y riesgo residual aceptado.
6. **Recomendaciones de controles compensatorios** para el EOL de Laravel 10.

---

## 11. Reglas de engagement y manejo de incidentes

- Toda prueba activa (F3) ocurre **únicamente en staging**; en producción solo pruebas pasivas o escaneos acotados con ventana autorizada.
- Si durante el análisis se detecta una **explotación activa en producción** o exposición de PII: **detener, preservar evidencia y escalar de inmediato** al responsable institucional (procedimiento de incidente LOPDP — notificación de brecha).
- Los datos personales a los que se acceda (mensajes de contacto, usuarios) se tratan como confidenciales; las PoCs usan datos sintéticos.
- La evidencia (capturas, requests) se almacena cifrada y con acceso limitado al equipo del análisis.
- Divulgación de hallazgos: interna primero (ISO/IEC 29147); sin publicación externa sin autorización.

---

## 12. Anexos

### 12.1 Plantilla de hallazgo

```text
ID:            AV-2026-XXX          (AV = Análisis Vulnerabilidades)
Título:        [nombre corto]
Caso de prueba: [ID de §6, ej. INJ-01]
Top 10:2025:   [A0X — nombre]
ASVS 5.0:      [VX.Y.Z]
Fecha:         [detección / validación]
Activo:        [componente, ej. POST /admin/login]

Descripción:
  [qué es, por qué ocurre, referencia de código file:line]

Evidencia:
  [requests/responses, capturas, logs — PoC reproducible]

Vector CVSS v4.0:  CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N
Score:             X.X (Crítica/Alta/Media/Baja)
EPSS / KEV:        [si aplica]

Impacto:
  Técnico:  [confidencialidad/integridad/disponibilidad]
  Legal:    [LOPDP / Infogobierno — si aplica]
  Negocio:  [imagen institucional, servicios ciudadanos]

Recomendación:
  [corrección concreta, referencia a ASVS/CIS]
Esfuerzo:  [Bajo/Medio/Alto]
SLA:       [según §7.1]

Estado:      [Abierto / En corrección / Revalidado / Aceptado]
Responsable: [equipo/persona]
```

### 12.2 Checklist ASVS 5.0 Nivel 2 (subset aplicable)

Verificar al menos los siguientes capítulos ASVS 5.0 contra el Portal2026:

| Capítulo ASVS | Aplicación al proyecto | Casos §6 |
|---|---|---|
| V2 Autenticación | Login admin, contraseñas, sesiones | AUTH-01…06 |
| V3 Sesión | Cookies, rotación, logout | AUTH-03…05 |
| V4 Control de acceso | Roles/permisos, IDOR, bypass | AUTHZ-01…07 |
| V5 Validación, escape y codificación | XSS, SQLi, SSTI | INJ-01…07 |
| V7 Manejo de errores y logging | Auditoría, trazas | LOG-01…04 |
| V9 Comunicación | TLS, HSTS | CFG-04 |
| V12 Archivos y recursos | Uploads, SVG, PDF | FILE-01…09 |
| V13 Configuración de APIs | CORS (bajo impacto: sin API) | CFG-07 |
| V14 Configuración | Headers, secretos, dependencias | CFG-01…03, SC-* |

### 12.3 Relación con el resto de documentación

| Documento | Relación |
|---|---|
| `DOCUMENTACION.md` §12 (riesgos conocidos) | Sus 7 ítems son **hallazgos ya identificados** que este plan debe verificar, cuantificar (CVSS) y dar seguimiento |
| `DOCUMENTACION.md` §14.F (seguridad post-despliegue) | Los pasos allí listados (cambiar seeds, `APP_DEBUG=false`, `composer audit`, permisos de `storage/`/`.env`) son **precondiciones** de F0 y casos CFG/SC de este plan |
| `PENDIENTES.md` sección A (A1–A4) | Resultados del análisis actualizan su estado; los hallazgos nuevos se añaden con ID `AV-2026-XXX` |
| `SPECS.md` §9.3 | Requisitos de seguridad originales; donde el código difiera (p. ej. XSS vía `{!! !!}` pese a "XSS escapado por Blade"), prevalece la verificación de este plan |

### 12.4 Criterios de aceptación de este plan

- [x] Alcance y exclusiones definidos y alineados al proyecto real.
- [x] Estándares 2026 referenciados (OWASP Top 10:2025, ASVS 5.0, CVSS v4.0, EPSS, ISO 29147).
- [x] Marco legal venezolano incluido (LOPDP, Ley de Infogobierno).
- [x] Casos de prueba priorizados con mapeo y referencias al código.
- [x] SLA de remediación y criterios de riesgo definidos.
- [x] Entregables, fases y reglas de engagement establecidos.

---

*Documento generado como plan de análisis de vulnerabilidades del Portal2026 — octubre 2026. Sujeto a revisión anual o tras cambios mayores de la aplicación.*
