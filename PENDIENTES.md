# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar cada ítem y actualizar el contador de progreso.
> Mantener sincronizado con `DOCUMENTACION.md` §12 (Estado del Desarrollo): al cerrar un ítem aquí, actualizar o eliminar su fila en §12.
> Última actualización: 2026-10-09

**Progreso:** 25 / 29 completados

---

## A. Seguridad y producción (revisar antes del despliegue)

- [ ] **A1 — Cambiar/eliminar usuarios seed en producción**  
  `database/seeders/UserSeeder.php` · `DOCUMENTACION.md` §14.F.1 · password `password` en `admin@gdc.gob.ve`, `admin2@gdc.gob.ve`, `editor@gdc.gob.ve`. Paso manual post-deploy (sin código).

- [x] **A2 — `SESSION_SECURE_COOKIE` sin default `true`**  
  `config/session.php:171` · default `true` + clave `SESSION_SECURE_COOKIE=true` en `.env.example`.

- [ ] **A3 — `.env` de producción real**  
  `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` real, BD/SMTP dedicados · `DOCUMENTACION.md` §14.D · se configura al desplegar.

- [x] **A4 — Revisar los 17 advisories en `audit.ignore`**  
  Cerrado: bloque depurado de 17 → **3 IDs** (todos `laravel/framework`, derivados de EOL — aceptados en F/A5, justificación en DOCUMENTACION §12 riesgos 2–3 e INFORME §7 controles compensatorios). Verificado con `composer audit`: 3 ignorados + 1 advisory **low** no ignorado (XSS en página de debug, `GHSA-jh5r-qr3c-85q8` — sin impacto con `APP_DEBUG=false`, G1). Compensatorio: SCA semanal (`composer audit` + `npm audit`).

## B. Comportamiento / configuración

- [x] **B1 — `locale='en'` y timezone `UTC`**  
  Cerrado: `config/app.php` con `locale='es'` (validación en español) y `timezone='America/Caracas'`; `test-dashboard.ps1` calcula mes/ventana con TZ Caracas. 16/16 suites E2E en verde.

- [x] **B2 — `phpunit.xml` corre contra la BD MySQL de desarrollo**  
  Cerrado (como parte de C3): `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` activos + configuración migrada al esquema PHPUnit 10.5; `RefreshDatabase` migra en memoria y nunca toca la BD de desarrollo.

## C. Calidad / mantenimiento

- [x] **C2 — Pint/PHPStan configurados**  
  Cerrado (solo Pint, decidido): `pint.json` (preset `laravel`) + scripts `composer lint` (`pint --test`) y `composer lint:fix`; aplicación inicial sobre 46 archivos y `composer lint` en verde. PHPStan no se incorpora.

- [x] **C3 — Tests PHPUnit reales**  
  Cerrado: 21 tests / 79 assertions en verde — `AuthTest` (login/logout/inactivo, `last_login_at`, mensaje genérico), `PermissionTest` (403/200 por rol, `hasPermission`, `PermissionSeeder`), `ContactStoreTest` (store válido/inválido, `read_at` no manipulable) y `Unit/WebpTest`; cierra B2 (BD sqlite en memoria); `UserFactory` con `Hash::make` para `BCRYPT_ROUNDS`.

- [x] **C4 — Suites E2E solo Windows/Laragon**  
  Decidido: se **mantiene como suite local** Windows/Laragon (sin CI — C1 descartado). Las 16 suites corren en verde en desarrollo.

- [x] **C5 — Limpieza cosmética**  
  Cerrado: `.DS_Store`/`._.DS_Store` eliminados (+ `.gitignore`); `routes/api.php` y `welcome.blade.php` borrados junto con su código muerto (`RouteServiceProvider` bloque api/rate-limiter, grupo `api` de `Http\Kernel`); se eliminan también de DOCUMENTACION (§3/§5/§7/riesgos).

## D. Funcionales

- [x] **D1 — Dashboard real**  
  Cerrado: SPECS §5.4 — tabla `daily_views` (tracking diario en `NewsController@show`, upsert MySQL, `day` en timezone de la app) + `DashboardController` con 9 tarjetas filtradas por permiso (`data-stat`), "Visitas del mes" real, gráfico CSS de 30 barras (sin librería) y últimas 5 noticias; suite `test-dashboard.ps1` — 16/16 suites E2E en verde. Notas: histórico diario inicia con la activación (sin backfill); SQL del upsert es MySQL-only (aceptado); mejora futura de rendimiento (caché de permisos) documentada en DOCUMENTACION §12 "Mejoras futuras".

- [x] **D3 — Configuración general (nombre del sitio, logo)**  
  SPECS §5.4 — cerrado: módulo `/admin/configuracion` (permiso `settings.manage`, solo superadmin), enlaces navbar configurables, suite `test-settings.ps1`.

- [x] **D4 — Navbar: "Jubilados" con `href="#"`**  
  Cerrado: el enlace es configurable desde `/admin/configuracion` (vacío = ocultar ítem; por defecto vacío). El destino real sigue pendiente de decisión del cliente (ítem E3).

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [x] **D7 — Videos en home**  
  Cerrado: módulo completo `/admin/videos` (2 pestañas: cargar YouTube o archivo; elegir un destacado para la portada), 4 permisos `videos.*` (solo superadmin), home renderiza el destacado. Suite `test-videos.ps1`.

- [x] **D8 — SEO: sitemap.xml + meta tags dinámicos**  
  Cerrado: meta tags dinámicos (title, description, canonical, og:image/url/type/locale, twitter:card) en `resources/views/layouts/public.blade.php` alimentados por `$seo` desde todos los controladores públicos; `SitemapController` genera `/sitemap.xml` cacheado con rutas de home, noticias, categorías, entes, gacetas y páginas; tests `SeoTest` y `SitemapTest`; suite `test-settings.ps1` (título home/404).

- [x] **D9 — Imágenes WebP con fallback**  
  Cerrado: SPECS §9.2 — servicio `App\Services\Webp` + componente `<x-img>` (`<picture>` con fallback al original), conversión automática en los 5 puntos de subida y borrado de sidecars, backfill `php artisan webp:generate`, CSS `picture{display:contents}`; suite `test-webp.ps1` — 15/15 suites E2E en verde. Alcance decidido: solo imágenes de template (no contenido WYSIWYG) y, en admin, solo logo de cabecera/login.

- [x] **D10 — Cache de consultas pesadas**  
  Cerrado: `config/portal.php` con TTLs, `App\Support\CacheKeys` con claves para home, nav de noticias, entes, gacetas y sitemap; `Cache::remember` en `HomeController`, `NewsController`, `EnteController`, `GacetaController`; observers `PostObserver`, `CategoryObserver`, `VideoObserver`, `PartnerObserver`, `GacetaObserver`, `PageObserver` invalidan cachés afectadas al guardar/borrar; `Admin\VideoController@destacar` usa Eloquent para disparar observers; test `CacheTest` (acierto e invalidación).

## E. Contenido / decisiones del cliente

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)
- [x] **E2 — URLs de redes sociales**  
  Resuelta: la fase de desarrollo está completa (módulo `/admin/rrss` listo y probado); las URLs se cargan en **producción** desde el panel.
- [x] **E3 — Destino del enlace "Jubilados"**  
  Resuelta: se setea en **producción** desde `/admin/configuracion` (vacío = ocultar ítem del navbar).

## F. Diferidos / fuera de alcance (no se van a hacer)

- **A5 — Upgrade de Laravel 11/12** — descartado: es **requisito de la institución** que el portal sea Laravel 10 (pese a EOL feb 2026); se mantiene en 10.x.
- **C1 — CI con GitHub Actions** — descartado: no se utilizará GitHub Actions.
- **D2 — CRUD de páginas estáticas en el admin** — archivado: **sin instrucción de que se vaya a necesitar**; `/pagina/{slug}` queda tal cual (vista `page.blade.php` lista, tabla `pages` vacía).
- **D5 — Front de la página de detalle de entes (`/entes/{slug}`)** — archivado: la vista actual es funcional (skeleton); se retoma solo con nueva instrucción de diseño.
- **E4 — Definir diseño del detalle de entes** — archivado junto con D5.
- **Tags en noticias** — omitido por decisión (SPECS §5.4); si se retoma: columna `posts.tags` + chips.
- **Mejoras futuras** (SPECS §10): newsletter, API REST, comentarios, galería, eventos, versión en inglés.

## G. Hallazgos del análisis de vulnerabilidades 2026

> Ver [INFORME-VULNERABILIDADES.md](INFORME-VULNERABILIDADES.md) y [PLAN-VULNERABILIDADES.md](PLAN-VULNERABILIDADES.md).

- [x] **G1 — Remediar hallazgos Críticos (AUTH-02 / CFG-03)**  
  Forzar cambio de contraseña en primer login (middleware + vista); `APP_DEBUG=false` por defecto; Ignition solo en dev. SLA ≤ 7 días.

- [x] **G2 — Remediar hallazgos Altos (AUTHZ-01 / FILE-03 / PRIV-01 / SC-01)**  
  Restricción de asignación de `superadmin` (+ protección del último superadmin); hash SHA-256 en gacetas; cifrado de PII (`contacts.name/email/subject/message` y `users.name`) con comando `php artisan pii:encrypt` para datos heredados; dependencias PHP/JS actualizadas y `audit.ignore` depurado (de 17 a 3 IDs justificados). SLA ≤ 30 días.

- [x] **G3 — Rate limiting y abuso de superficie pública (AUTH-01 / ABUSE-01)**  
  `RateLimiter` + `throttle` en `/admin/login`, `POST /contacto` y `/buscar`. CAPTCHA posible mejora futura. SLA ≤ 90 días.

- [x] **G4 — Sanitización de contenido y uploads (INJ-01 / INJ-02 / FILE-01)**  
  HTMLPurifier en `Post::content` y `Page::content` (trait `App\Support\SanitizesHtml`) con post-proceso que elimina `<a href="data:...">` mientras conserva `<img src="data:...">`; SVG eliminado de mimes de uploads; la extensión guardada en los 7 puntos de subida se adivina por contenido (`App\Support\Uploads::safeExtension`), no por `getClientOriginalExtension()`. SLA ≤ 90 días.

- [x] **G5 — Hardening de headers, sesión y rate limits (CFG-01 / CFG-02 / CFG-05 / AUTH-01 / ABUSE-01)**  
  Middleware `AddSecurityHeaders` (CSP con `frame-ancestors`/`form-action`, HSTS, X-Frame-Options, etc.); `SESSION_SECURE_COOKIE` por entorno (seguro salvo `APP_ENV=local`); `TrustHosts` activo con whitelist; `TrustProxies` configurable vía `config/security.php`; rate limits de login/contacto/búsqueda centralizados en `config/security.php` para respetar `php artisan config:cache`; `JSON_HEX_TAG` en el JSON de la barra de compartir. SLA ≤ 90 días.

- [x] **G6 — Auditoría y controles compensatorios EOL (LOG-01 / SC-02)**  
  Cerrado (LOG-01): migración `activity_logs` + modelo `ActivityLog` + helper `App\Support\Audit` + hooks en `AuthController` (login success/failed/logout/password change), `UserController` (create/update/delete), `RoleController`, `NewsController`, `GacetaController`, `ContactController` (view/delete); vista `/admin/auditoria` con paginación, superadmin-only mediante permiso `audit.view`; 11 tests en `AuditLogTest`. Los controles EOL (SC-02) quedan como compensatorios documentados en INFORME §7.

- [x] **G7 — Endurecer CSP a nonce/hashes (P3-2)**  
  Cerrado: `AddSecurityHeaders` genera un **nonce por petición**, lo expone a las vistas vía `request()->attributes->get('csp_nonce')` y CSP pasa a `script-src 'self' 'nonce-...'` (sin `'unsafe-inline'`); todos los `<script>` del proyecto (inline y externos, incl. TinyMCE) reciben `nonce`; los ~14 `onclick="return confirm(...)"` se migraron a `data-confirm` + listener global en `layouts/admin.blade.php` y `layouts/public.blade.php`; los botones de error (`data-action="reload"` / `data-action="back"`) también se migran. `style-src` mantiene `'unsafe-inline'` por TinyMCE. Tests en `SecurityHeadersTest`.

- [x] **G8 — Verificación residual de casos de prueba del plan (FILE-04/06/07/09, INJ-03/05/06, CFG-07, SC-05, LOG-03, PRIV-03)**  
  Cerrado en el alcance posible con PHPUnit/E2E: **INJ-03** (`SearchXssTest`), **PRIV-03** cubierta por auditoría G6, **FILE-04** (`UploadExtensionTest`), **CFG-07** (`CorsConfigurationTest`), **FILE-06** (`StorageSymlinkTest`); se añaden `SeoTest`, `SitemapTest` y `CacheTest` como cobertura funcional de D8/D10. Los casos que requieren DAST/manual/infraestructura (FILE-07, FILE-09, INJ-05, INJ-06, SC-05, LOG-03) quedan documentados en `PLAN-VULNERABILIDADES.md` y `INFORME-VULNERABILIDADES.md` para auditoría externa.

---

## Registro de cierre

> Al marcar un ítem: fecha + nota breve. Ejemplo: `A2 — 2026-10-10, añadido default true en session.php`.

| Ítem | Fecha | Nota |
|---|---|---|
| D3 | 2026-10-07 | `/admin/configuracion` + permiso `settings.manage` + `Setting::defaults()/values()`; suite `test-settings.ps1`; 13/13 suites E2E en verde |
| D4 | 2026-10-07 | Enlace configurable en `/admin/configuracion` (vacío = oculto); el destino real queda como contenido (E3) |
| D11 | 2026-10-07 | Movido a E2 (es contenido, no código); módulo `/admin/rrss` quedó listo |
| E2 | 2026-10-07 | Fase de desarrollo resuelta (módulo `/admin/rrss` listo); URLs se cargan en producción |
| E3 | 2026-10-07 | Resuelta: destino se setea en producción desde `/admin/configuracion` (vacío = oculto) |
| A5 | 2026-10-07 | Descartado (sección F): la institución requiere Laravel 10 |
| C1 | 2026-10-07 | Descartado (sección F): no se usará GitHub Actions |
| C4 | 2026-10-07 | Decidido: suites E2E se mantienen como suite local Windows/Laragon (13/13 en verde) |
| D2 | 2026-10-07 | Archivado (sección F): sin instrucción de necesidad del CRUD de páginas |
| D5 | 2026-10-07 | Archivado (sección F): vista de detalle de entes actual funcional; retomar con nueva instrucción |
| E4 | 2026-10-07 | Archivado (sección F) junto con D5 |
| D7 | 2026-10-08 | Módulo `/admin/videos` (2 pestañas) + 4 permisos `videos.*` (solo superadmin) + video destacado en home; sidebar con sección "Gestión Noticias/Multimedia"; suite `test-videos.ps1` (14/14) |
| — | 2026-10-08 | Ajuste UI: sidebar en 3 secciones ("Gestión Noticias" con Gacetas, "Gestión Portal", "Configuración Portal"); `Configuración` renombrado a `Identidad / Enlaces` (sidebar + h1); rutas y permisos sin cambios |
| — | 2026-10-08 | Permisos reagrupados en los mismos 3 `group` del sidebar (15/17/6, `updateOrCreate` en `PermissionSeeder`); permiso `settings.manage` renombrado a "Identidad / Enlaces"; matriz `/admin/roles` ordenada igual que el sidebar |
| — | 2026-10-08 | Jerarquía de permisos 2 niveles: nueva columna `permissions.module` (14 módulos = ítems del sidebar) con migración `2026_10_08_000001`; vista `/admin/roles` con doble cabecera (categoría → módulo → permiso, orden por `FIELD()` + acción); `settings.manage` renombrado a "Editar identidad y enlaces" |
| — | 2026-10-08 | Fix dropdown Multimedia: `flex-nowrap` en `ul#mmMenu` — `.nav { flex-wrap: wrap }` con eje vertical + `height` animada por Bootstrap Collapse hacía aparecer "Medios"/"Videos" lado a lado durante la animación; aserción en `test-videos.ps1` |
| D1 | 2026-10-08 | Dashboard real (SPECS §5.4): migración `daily_views` (`UNIQUE(day, post_id)`, `cascadeOnDelete`) + modelo `DailyView` + upsert en `NewsController@show`; `DashboardController` con agregados + 9 tarjetas por permiso (`data-stat`) + gráfico CSS 30 días + últimas noticias; suite `test-dashboard.ps1` — 16/16 E2E en verde |
| B1 | 2026-10-08 | `config/app.php`: `locale='es'` + `timezone='America/Caracas'`; `test-dashboard.ps1` pasa a TZ Caracas (`TimeZoneInfo`) para mes/ventana |
| B2 | 2026-10-08 | `phpunit.xml` con `sqlite/:memory:` activos + migración a esquema PHPUnit 10.5 — tests aislados de la BD de desarrollo |
| C2 | 2026-10-08 | `pint.json` (preset laravel) + `composer lint`/`composer lint:fix`; aplicación inicial a 46 archivos; lint en verde (solo Pint, sin PHPStan) |
| C3 | 2026-10-08 | 21 tests PHPUnit en verde (`AuthTest`, `PermissionTest`, `ContactStoreTest`, `Unit/WebpTest`); `UserFactory` con `Hash::make`; cierra B2 |
| C5 | 2026-10-08 | `.DS_Store` + `.gitignore`; `routes/api.php`, `welcome.blade.php` y código muerto asociado (provider/Kernel) eliminados |
| D9 | 2026-10-08 | WebP con fallback (SPECS §9.2): `App\Services\Webp` (sidecar `.webp` calidad 82, jpg/jpeg/png, degradado sin GD/ilegibles), componente `<x-img>` con `<picture>` + `<img src>` original (11 vistas públicas/admin/logo), hooks en Media/News/Gaceta/Partner/Settings (convert al subir, delete al borrar/reemplazar), `php artisan webp:generate` (backfill idempotente, contador `ilegibles`), CSS `picture{display:contents}` + build; suite `test-webp.ps1` y sidecars añadidos a las limpiezas de media/wysiwyg/gacetas/partners/settings — 15/15 E2E en verde |
| G1-G6 | 2026-10-08 | Hallazgos de seguridad añadidos tras ejecución del análisis de vulnerabilidades; ver INFORME-VULNERABILIDADES.md |
| A2 | 2026-10-08 | Default `true` en `config/session.php:171` y clave `SESSION_SECURE_COOKIE=true` en `.env.example` |
| G1 | 2026-10-08 | Forzar cambio de contraseña (middleware + vista) + `APP_DEBUG=false` + Ignition solo en dev |
| G2 | 2026-10-08 | Restricción superadmin + hash SHA-256 gacetas + cifrado PII + actualización de dependencias |
| G3 | 2026-10-08 | Rate limiting en login, contacto y búsqueda |
| G4 | 2026-10-08 | HTMLPurifier en posts y eliminación de SVG en uploads |
| G5 | 2026-10-08 | Middleware de headers de seguridad + HSTS condicional |
| — | 2026-10-08 | Auditoría comprobatoria: `pii:encrypt` para PII heredado, sanitización de `Page`, `SESSION_SECURE_COOKIE` por entorno, rate limiter por IP + lockout, `audit.ignore` depurado, `TrustHosts` + `JSON_HEX_TAG`, validación de `org_members.photo`, `TrustProxies` configurable, `frame-ancestors`/`form-action` en CSP, protección del último superadmin |
| — | 2026-10-08 | Verificación: 55 tests PHPUnit + 16/16 suites E2E en verde; `composer lint` passed; `npm run build` OK; `npm audit` 0; `composer audit` 1 low + 3 ignorados justificados |
| G4 | 2026-10-08 | Extensión de subidas por contenido (`App\Support\Uploads` + 7 controladores); eliminación de `<a href="data:...">` en `SanitizesHtml`; tests `UploadExtensionTest` + caso adicional en `HtmlSanitizationTest` |
| G5 | 2026-10-08 | `config/security.php` centraliza rate limits, `TRUSTED_PROXIES` y `TRUSTED_HOSTS`; `RouteServiceProvider`, `TrustProxies` y `TrustHosts` leen de `config()`; test `SecurityConfigTest` |
| G8 | 2026-10-08 | Añadidos `CorsConfigurationTest` (CFG-07) y `StorageSymlinkTest` (FILE-06); FILE-04 parcialmente cubierto en `UploadExtensionTest`; resto de casos residuales documentados para DAST/manual |
| A4 | 2026-10-08 | `audit.ignore` depurado de 17 → 3 IDs (laravel/framework EOL, justificados en DOCUMENTACION §12 e INFORME §7); `composer audit` verificado: 3 ignorados + 1 low sin impacto (`APP_DEBUG=false`); compensatorio SCA semanal |
| G6 | 2026-10-08 | Log de auditoría: `activity_logs` + `Audit` + hooks en login/usuarios/roles/noticias/gacetas/contacto; vista `/admin/auditoria` + permiso `audit.view`; 11 tests en `AuditLogTest` |
| G7 | 2026-10-08 | CSP endurecido: nonce por petición en `AddSecurityHeaders`, `script-src` sin `'unsafe-inline'`, todos los scripts con nonce, `onclick` confirm migrados a `data-confirm`; tests en `SecurityHeadersTest` |
| D8 | 2026-10-09 | Meta tags dinámicos en public layout + `$seo` en controladores públicos + `/sitemap.xml` cacheado; tests `SeoTest`/`SitemapTest` |
| D10 | 2026-10-09 | `config/portal.php` + `App\Support\CacheKeys` + cache en home/noticias/entes/gacetas + observers para invalidación; test `CacheTest` |
| G8 | 2026-10-09 | Cerrado en alcance PHPUnit/E2E: SeoTest/SitemapTest/CacheTest como cobertura funcional; DAST/manual/infra (FILE-07, FILE-09, INJ-05/06, SC-05, LOG-03) documentado para auditoría externa |
| G8 | 2026-10-08 | Parcial: `SearchXssTest` (INJ-03), trazabilidad PRIV-03 cubierta por auditoría G6; FILE-04/CFG-07 y FILE-06 ya en PHPUnit; resto pendiente de DAST/manual/infraestructura |
