# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar cada ítem y actualizar el contador de progreso.
> Mantener sincronizado con `DOCUMENTACION.md` §12 (Estado del Desarrollo): al cerrar un ítem aquí, actualizar o eliminar su fila en §12.
> Última actualización: 2026-10-08

**Progreso:** 8 / 21 completados

---

## A. Seguridad y producción (revisar antes del despliegue)

- [ ] **A1 — Cambiar/eliminar usuarios seed en producción**  
  `database/seeders/UserSeeder.php` · `DOCUMENTACION.md` §14.F.1 · password `password` en `admin@gdc.gob.ve`, `admin2@gdc.gob.ve`, `editor@gdc.gob.ve`. Paso manual post-deploy (sin código).

- [ ] **A2 — `SESSION_SECURE_COOKIE` sin default `true`**  
  `config/session.php:171` · agregar default `true` o clave en `.env` · 1 línea.

- [ ] **A3 — `.env` de producción real**  
  `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` real, BD/SMTP dedicados · `DOCUMENTACION.md` §14.D · se configura al desplegar.

- [ ] **A4 — Revisar los 17 advisories en `audit.ignore`**  
  `composer.json:65-83` · ejecutar `composer audit` y evaluar eliminar el bloque.

## B. Comportamiento / configuración

- [ ] **B1 — `locale='en'` y timezone `UTC`**  
  `config/app.php:85` (locale → `es`) y `:72` (timezone → `America/Caracas`) · 2 líneas. Fechas/mes aparecen en inglés en la UI.

- [ ] **B2 — `phpunit.xml` corre contra la BD MySQL de desarrollo**  
  `phpunit.xml:24-25` · descomentar `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` para aislar los tests.

## C. Calidad / mantenimiento

- [ ] **C2 — Pint/PHPStan configurados**  
  `laravel/pint` está instalado pero sin `pint.json` ni script en `composer.json`. Añadir config + `composer lint`.

- [ ] **C3 — Tests PHPUnit reales**  
  `tests/Unit`, `tests/Feature` solo tienen `ExampleTest` (cobertura ≈ 0). Escribir tests de flujos críticos (login, permisos, contact store).

- [x] **C4 — Suites E2E solo Windows/Laragon**  
  Decidido: se **mantiene como suite local** Windows/Laragon (sin CI — C1 descartado). Las 16 suites corren en verde en desarrollo.

- [ ] **C5 — Limpieza cosmética**  
  Borrar `.DS_Store` de `public/images/Logos/`; valorar eliminar `routes/api.php` y `resources/views/welcome.blade.php` sin usar.

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

- [ ] **D8 — SEO: sitemap.xml + meta tags dinámicos**  
  SPECS §9.1 (title, description, og:image).

- [x] **D9 — Imágenes WebP con fallback**  
  Cerrado: SPECS §9.2 — servicio `App\Services\Webp` + componente `<x-img>` (`<picture>` con fallback al original), conversión automática en los 5 puntos de subida y borrado de sidecars, backfill `php artisan webp:generate`, CSS `picture{display:contents}`; suite `test-webp.ps1` — 15/15 suites E2E en verde. Alcance decidido: solo imágenes de template (no contenido WYSIWYG) y, en admin, solo logo de cabecera/login.

- [ ] **D10 — Cache de consultas pesadas**  
  SPECS §9.2 (home, listados).

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
| D9 | 2026-10-08 | WebP con fallback (SPECS §9.2): `App\Services\Webp` (sidecar `.webp` calidad 82, jpg/jpeg/png, degradado sin GD/ilegibles), componente `<x-img>` con `<picture>` + `<img src>` original (11 vistas públicas/admin/logo), hooks en Media/News/Gaceta/Partner/Settings (convert al subir, delete al borrar/reemplazar), `php artisan webp:generate` (backfill idempotente, contador `ilegibles`), CSS `picture{display:contents}` + build; suite `test-webp.ps1` y sidecars añadidos a las limpiezas de media/wysiwyg/gacetas/partners/settings — 15/15 E2E en verde |
