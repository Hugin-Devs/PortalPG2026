# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar cada ítem y actualizar el contador de progreso.
> Mantener sincronizado con `DOCUMENTACION.md` §12 (Estado del Desarrollo): al cerrar un ítem aquí, actualizar o eliminar su fila en §12.
> Última actualización: 2026-10-14

**Progreso:** 33 / 33 completados

---

## A. Seguridad y producción (revisar antes del despliegue)

- [x] **A1 — Cambiar/eliminar usuarios seed en producción**  
  Cerrado (D18): nuevo **`public/install.php`** — instalador web one-shot (formulario tipo Nextcloud con superadmin + admin, validación server-side, borra definitivamente los 3 usuarios seed y se **auto-elimina**; guarda de re-ejecución si el portal ya está configurado). Corre tras `migrate --seed` en el primer arranque — `DOCUMENTACION.md` §14.E/§14.F.1.

- [x] **A2 — `SESSION_SECURE_COOKIE` sin default `true`**  
  `config/session.php:171` · default `true` + clave `SESSION_SECURE_COOKIE=true` en `.env.example`.

- [x] **A3 — `.env` de producción real**  
  Cerrado (D18): nueva plantilla **`.env_example_produccion`** (versionada) con `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, rate limits **30/5/30**, `AUDIT_ENABLED=true`, `TRUSTED_PROXIES`/`TRUSTED_HOSTS` y placeholders comentados de BD/SMTP reales — `DOCUMENTACION.md` §14.D. Los valores reales del servidor se completan al desplegar.

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
  *Superseded 2026-10-14*: con la migración a PostgreSQL las suites se reescribieron a bash+psql (`tests/e2e/*.sh`, 17/17 en verde) y ya no dependen de Windows ni de Laragon; sigue sin CI (gates en local).

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

- [x] **D12 — Flujo de aprobación de noticias (permiso, badge, masivo, programación)**  
  Cerrado: permiso `news.approve` ("Aprobar noticias", por defecto superadmin+admin); `resolveStatus()` basado en permiso — sin él el estado no cambia (ni se sube ni se baja) y se emite flash `warning`; badges "Pendiente de aprobación" / "Programada · fecha" / "Publicado" en el listado admin; aprobación individual (botón "Aprobar" con confirmación) y masiva (`POST /admin/noticias/publicar` con checkboxes `form=`, solo borradores → publicar ya, omitidas las no-borrador); programación opcional vía `published_at` (campo `datetime-local` habilitado solo con `news.approve`, vacío = publicar ya, hora de Caracas) con scope `Post::live()` aplicado a las 11 consultas públicas + dashboard, migración backfill `published_at` y fix de `PostSeeder`; auditoría `news.approved`; tests `NewsApprovalTest` (13) + ampliaciones en `EditorCannotPublishTest` y `PermissionTest` (99 tests / 428 assertions); suite `test-noticias.ps1` (17ª) — 15/17 suites E2E en verde (fallos preexistentes en `test-entes`/`test-share`). Limitación documentada: al llegar la hora de una programada, los listados cacheados pueden tardar ≤ `PORTAL_CACHE_TTL` (1h) en reflejarla.

- [x] **D13 — Seeder de noticias reales + orden por fecha de publicación**  
  Cerrado: `PostSeeder` reescrito como array declarativo duplicable con docblock de instrucciones — 4 bloques de ejemplo (plantilla publicada+destacada, **migrada de redes sociales con fecha histórica pasada**, borrador de prueba y programada de prueba), `content` en nowdoc para pegar HTML tal cual, resolución de categoría por slug y autor por email con fallbacks, purga `withTrashed()` de las placeholders `noticia-destacada-*` con `forceDelete()` (borrado físico: libera el slug y evita que consultas sin filtro de `deleted_at` tomen filas borradas) e idempotencia vía `updateOrCreate(['slug' => ...])`; nuevo scope `Post::recent()` (`published_at` DESC con desempate por `id`) aplicado en las 5 consultas públicas que ordenaban por creación (home destacadas y últimas, listado `/noticias`, categoría, relacionados y búsqueda) para que las noticias migradas queden en su lugar histórico en lugar de aparecer como recientes; columna "Fecha" del listado admin ahora muestra `published_at ?? created_at`; SQL de referencia de `test-categorias.ps1` y `test-dashboard.ps1` alineados al scope `live()` (filtros `deleted_at IS NULL` y `published_at <= NOW()`, exponían la programada como "publicada"); tests `PostSeederTest` (3) y `NewsOrderingTest` (3) — 105 tests / 458 assertions y 15/17 suites E2E (mismos 2 fallos preexistentes en `test-entes`/`test-share`).

- [x] **D14 — Redes sociales: múltiples cuentas por red (una URL por línea)**  
  Cerrado: `SocialSettingSeeder` expone `REDES` con las 7 claves como **espacio para pegar las URLs reales del portal** (una por línea; varias líneas = varias cuentas del mismo tipo) y ahora rellena **solo los valores vacíos** (nunca pisa lo editado desde `/admin/rrss`); `RrssController@update` valida **cada línea** como URL http(s) (`filter_var(FILTER_VALIDATE_URL)` + `^https?://`, mensaje «La línea N no es una URL válida», `max:4000`) y guarda normalizado (CRLF→LF, sin líneas vacías); los 7 campos de `/admin/rrss` pasan de `input url` a **textarea** multi-línea con ayuda; el footer `_social-links` dibuja **un icono por línea** y `title`/`aria-label` distingue cuentas (`Facebook — https://…` cuando la red tiene 2+); snapshot/restauración de `test-social.ps1` endurecido con `HEX`/`UNHEX` (el cliente mysql escapa `\n` y rompía los valores multi-línea) + 2 secciones nuevas (multi-cuenta e inválida en medio) — 12/12 secciones; tests `SocialLinksTest` (6) — 111 tests / 486 assertions.

- [x] **D15 — Multimedia en noticias: galería + videos + wysiwyg sin imágenes**  
  Cerrado: tabla `post_media` (`post_id`, `type image|video`, `path`, `video_id` FK→`videos` nullOnDelete, `position`) + modelo `PostMedia` + relación `Post::postMedia()`; **subidas con año/mes**: `Uploads::storeDated()` → `uploads/YYYY/MM/` en biblioteca, portada y galería (carpetas `partners/`, `gacetas/`, `videos/`, `settings/` sin cambio); admin: etiqueta "Imagen de portada (hero)" + partial `news/_gallery-field` (imágenes múltiples con **botones ↑/↓** vía `formaction`+`formmethod` — ruta `move` con `match(['post','put'])` porque el form de edición lleva `_method=PUT` — y `remove_media[]`, checklist de videos existentes, textarea de URLs YouTube una por línea y archivos mp4/webm/ogg); límites **10 imágenes / 3 videos** por noticia (restando las eliminaciones en la misma operación); videos nuevos se **crean en el módulo Videos** (`is_featured=false`); `remove_media` solo acepta filas propias y no borra archivos (paridad con `remove_image`); público `news-show` con ≥2 ítems = **carrusel Bootstrap** (portada primero, `<iframe>` YouTube / `<video>` archivo), solo portada = `<img class="img-fluid">` intacto; **wysiwyg sin imágenes**: plugin/botón `image` + handler eliminados de `_editor`, `img` y esquema `data:` fuera de `allowedHtml()` (el regex de anclas `data:` se mantiene), aviso fijo bajo el editor + flash `warning` "Se eliminaron N imagen(es)…"; bug corregido en `move()` (el update vía modelo no emitía UPDATE tras la normalización de posiciones → query builder); tests `PostMediaTest` (11) + `HtmlSanitizationTest` invertido (3) + `test-wysiwyg` actualizado + sección 9 de `test-noticias` (portada+galería+2 videos → carrusel de 5, orden, remove, tope de videos) — 122 tests / 545 assertions y 15/17 suites E2E (fallos preexistentes `test-entes`/`test-share`). Nota superada por D16: las noticias reales ahora las carga `NoticiasPrecargadasSeeder` desde `public/Noticias Precargadas/` (portada + galería incluidas).

- [x] **D16 — Noticias precargadas reales (lote de 5, con portada y galería)**  
  Cerrado: carpeta fuente `public/Noticias Precargadas/` (5 noticias de Instagram: por carpeta, imágenes `001…NNN` + `.txt` con el cuerpo y una línea final `Fecha:`/`Fech:` como metadato); nuevo `NoticiasPrecargadasSeeder` (declarativo, idempotente, invocado también por `DatabaseSeeder`): la fecha sale del sello del nombre de la imagen 001 (`ddmmyyyyhhmm`/`ddmmyyhhmm` validada con `checkdate`) y, si no parsea (los sellos de las noticias 1 y 2 no traen mes), de la línea `Fecha:` del txt — para la Noticia 4 prevalece el nombre (**16:30**, decisión del cliente, pese al "Fech: 18:30" del txt); el cuerpo se conserva **íntegro** (menciones, hashtags y emojis — decisión del cliente) partido en `<p>` por párrafos en blanco y sin la línea de Fecha; la 001 → `featured_image`, las 002+ → filas `post_media` del carrusel D15 (posiciones 1..k) y cada imagen se copia a `storage/app/public/uploads/YYYY/MM/` con fila en `media` y sidecar WebP; títulos/excerpts/categorías aprobados en el mapa del seeder (lote 2 = carpeta nueva + entrada); sin videos (el paquete no incluye URLs); limpieza de BD dev: borrado de los drafts `repro-d15*` (restos de pruebas D15) y alineación del slug de la plantilla (`…distrito-capital` → `…distrito`) para que `PostSeeder` no duplique; bug de desarrollo: el patrón `Fech?:?` no incluía la letra `a` (casaba "Fech" y anulaba el fallback) → alternación `(?:Fecha|Fech)`; tests `NoticiasPrecargadasSeederTest` (2: contenido/fechas/categorías, galerías 3·0·2·3·2, `media`=15, texto íntegro, idempotencia) — 124 tests / 610 assertions; E2E `test-noticias` y `test-dashboard` en verde tras el alta (+5 noticias).

- [x] **D17 — Carrusel de noticias: sin recorte (560px) + fix "solo 1 imagen"**  
  Cerrado: el carrusel público de `news-show` (≥2 ítems) ya no recorta las fotos verticales — cada slide de imagen es un wrapper `d-flex` de **560px** centrado sobre blanco con la foto a su proporción natural (`max-height/max-width: 100%; width/height: auto`); se eliminaron `object-fit: cover`, `shadow-sm` y **`loading="lazy"`** de las imágenes de galería (los slides ocultos no cargaban a tiempo y se veían en blanco — el usuario reportaba "solo sale una imagen"), con `decoding="async"`; flechas ‹ › con `aria-label` e iconos **oscuros** (CSS inline scoped a `#newsGallery` anula el `filter: invert` blanco de Bootstrap, ilegibles sobre el fondo blanco nuevo); iframe/video a 560px (video con `object-fit: contain`); fallback de sola portada = mismo wrapper 560px sin carrusel; verificación con **Edge headless** (screenshot antes/después: foto completa centrada, sin banda recortada, flechas visibles) y curl de las 5 noticias precargadas (ítems 4·0·2·3·2, 1 `active`, 0 `lazy`, 0 `cover`); assets `/build` verificados 200 con CSP vigente; test `test_single_cover_keeps_static_image_without_carousel` ajustado al nuevo fallback — `PostMediaTest` (11) + E2E `test-noticias` en verde — 124 tests / 611 assertions.

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
| D12 | 2026-10-10 | Permiso `news.approve` (40 permisos, reparto 40/22/5 = 67 asignaciones) + `resolveStatus()` por permiso con flash `warning` + badges (Pendiente/Programada/Publicado) + aprobación individual y masiva + programación opcional vía `published_at` con scope `Post::live()` (11 consultas públicas + dashboard) y backfill; auditoría `news.approved`; `NewsApprovalTest` (13 tests) — 99 tests PHPUnit y 15/17 E2E en verde con la nueva suite `test-noticias.ps1` |
| D13 | 2026-10-13 | `PostSeeder` declarativo duplicable (4 noticias de ejemplo: plantilla, migrada con fecha histórica, borrador y programada; purga `withTrashed` de placeholders) + `Post::recent()` (orden por `published_at`) en home/listado/categoría/relacionados/búsqueda + columna Fecha del admin = `published_at ?? created_at`; SQL E2E de `test-categorias`/`test-dashboard` alineados al scope `live()`; `PostSeederTest`/`NewsOrderingTest` — 105 tests PHPUnit y 458 assertions + 15/17 suites E2E en verde |
| D14 | 2026-10-13 | Redes sociales multi-cuenta: `SocialSettingSeeder::REDES` (espacio para pegar las URLs reales, una por línea; rellena solo vacíos) + validación URL por línea en `RrssController` + textarea en `/admin/rrss` + un icono por URL en el footer con tooltip `Red — URL`; `test-social.ps1` con snapshot/restauración HEX multi-línea segura y 2 secciones nuevas (12/12); `SocialLinksTest` (6 tests) — 111 tests PHPUnit y 486 assertions en verde |
| D15 | 2026-10-13 | Multimedia en noticias: tabla `post_media` + `PostMedia` (galería con `position`, imágenes y videos enlazados) + reorden ↑/↓ (`move` con formaction/formmethod) + `remove_media[]` solo propios; `Uploads::storeDated()` → `uploads/YYYY/MM/` (biblioteca, portada, galería); límites 10 imágenes / 3 videos; videos nuevos → módulo Videos (`is_featured=false`); carrusel Bootstrap en `news-show` (portada primero) y `<img>` estático con solo portada; wysiwyg sin plugin image ni `<img>` en el sanitizador + aviso `warning`; `PostMediaTest` (11) + `HtmlSanitizationTest` invertido (3) + sección 9 de `test-noticias` — 122 tests PHPUnit y 545 assertions en verde |
| D16 | 2026-10-14 | Noticias precargadas: carpeta `public/Noticias Precargadas/` (5 noticias IG con imágenes `001…NNN` + `.txt` con línea `Fecha:`) + `NoticiasPrecargadasSeeder` (fecha por sello de nombre con fallback a la línea Fecha; 001=portada, 002+=galería `post_media`; copia a `uploads/YYYY/MM/` + `media` + WebP; texto íntegro en `<p>`; idempotente; en `DatabaseSeeder`) + limpieza de BD (drafts `repro-d15*`, slug de plantilla); `NoticiasPrecargadasSeederTest` (2 tests) — 124 tests PHPUnit y 610 assertions en verde |
| D17 | 2026-10-14 | Carrusel de noticias sin recorte: slides de imagen con wrapper 560px centrado sobre blanco (foto a proporción natural, sin `object-fit: cover` ni `loading="lazy"`, `decoding="async"`), fallback de sola portada igual, flechas con `aria-label` e iconos oscuros (CSS inline `#newsGallery`); fix "solo 1 imagen" (lazy en slides ocultos); verificación Edge headless + curl de las 5 noticias; `PostMediaTest` ajustado al nuevo fallback — 124 tests PHPUnit y 611 assertions en verde |
| D18 | 2026-10-14 | Producción: plantilla `.env_example_produccion` (APP_ENV/DEBUG, rate limits 30/5/30, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, `AUDIT_ENABLED`, trusted proxies/hosts + placeholders BD/SMTP) + instalador web one-shot `public/install.php` (alta superadmin/admin con formulario, borrado de los 3 usuarios seed, guard de re-ejecución y auto-eliminación; cierra A1 y A3 — checklist 33/33) |
| — | 2026-10-14 | Seeders de noticias: `NoticiasPrecargadasSeeder` pasa a ser el **seeder por defecto** (toma el hueco de `PostSeeder` en `DatabaseSeeder` y hereda la purga `withTrashed()`+`forceDelete()` de placeholders `noticia-destacada-*`); `PostSeeder` queda como **plantilla manual** (`db:seed --class=PostSeeder`), sin purga; test de purga movido a `NoticiasPrecargadasSeederTest` — 124 tests PHPUnit en verde |
| PG1 | 2026-10-14 | **Migración MariaDB → PostgreSQL** (repo hermano PortalPG2026): motor por defecto `pgsql` + `DB_SSLMODE`; SQL MySQL-only a portables (`FIELD`/`SUBSTRING_INDEX`/backticks→PHP, `CAST AS UNSIGNED`→`integer`, `SUM(boolean)`→`SUM(CASE WHEN…)`, `LIKE`→`ILIKE` en PG, upsert diario→`ON CONFLICT`); migraciones verificadas up/down en PG (`enum()`→varchar+CHECK); 17 suites E2E reescritas a bash+psql (`.ps1` eliminados, 663 checks verdes); gate doble PHPUnit SQLite+PG real (129/129); docs §13/§14/§16 actualizadas. Supera la decisión C4 (E2E Windows/Laragon) |
| PG2 | 2026-10-16 | **Adaptación a BD de producción LATIN9** (PostgreSQL ISO-8859-15, fija): conexión siempre `client_encoding=UTF8` (PG convierte en el cable; `DB_CHARSET` envolvible, nunca `latin9`); transliteración en dos capas — helper `App\Support\Latin9` (iconv TRANSLIT//IGNORE + emojis eliminados; acentos/€ intactos; `…`→`...`, `“ ”`→`"`, `—`→`-`), trait `StoresLatin9` en la base `App\Models\Model` y `User` (antes de HTMLPurifier y casts `encrypted`) y middleware global `SanitizeLatin9Input` (query/body/JSON, cubre `?q=` de `/buscar` → sin error 22021 en SELECT); `install.php` avisa si `server_encoding` no es `latin*`; E2E `lib.sh` fuerza `-c client_encoding=UTF8`; gates **144/144 en SQLite, PG UTF-8 y PG LATIN9 local** (`createdb -E LATIN9 -l C -T template0`) + 3 suites E2E en verde contra LATIN9; tests `Latin9Test` (7) + `Latin9EncodingTest` (8); docs §14.D/§14.K/§16 |
| PG3 | 2026-10-16 | **Blindaje de UserSeeder ante APP_KEY cambiado** (incidente real: re-`db:seed` en producción fallaba con `DecryptException: The MAC is invalid` porque `users.name` está cifrado con una clave previa): `UserSeeder` captura el `DecryptException`, imprime guía accionable (restaurar clave / `migrate:fresh --seed --force` si la BD es descartable / `DELETE` crudo vía `psql` de la fila afectada y re-seed) y re-lanza para detener el seed; §14.D fija APP_KEY única antes del primer seed, §14.E el orden `key:generate` → `migrate --seed` → `install.php`; test `UserSeederKeyMismatchTest` (2); gates **146/146 en SQLite, PG UTF-8 y PG LATIN9** + simulación del incidente end-to-end; docs §14.D/§14.E |
| G8 | 2026-10-09 | Cerrado en alcance PHPUnit/E2E: SeoTest/SitemapTest/CacheTest como cobertura funcional; DAST/manual/infra (FILE-07, FILE-09, INJ-05/06, SC-05, LOG-03) documentado para auditoría externa |
| G8 | 2026-10-08 | Parcial: `SearchXssTest` (INJ-03), trazabilidad PRIV-03 cubierta por auditoría G6; FILE-04/CFG-07 y FILE-06 ya en PHPUnit; resto pendiente de DAST/manual/infraestructura |
