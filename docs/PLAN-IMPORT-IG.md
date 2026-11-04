# Plan — Importador asistido de noticias desde Instagram

> **Rama:** `port-noticias` · **Estado:** F0 + B/C1 + anti-avatar reforzado + selección de portada + fecha IG · **Fecha:** 2026-11-03

## 0. Contexto

Tarea: mantener actualizadas las noticias del portal sin insumo de Comunicaciones. Fuente: Instagram de gobernación y gobernador (sin acceso a cuentas, sin API de pago). Restricciones: borrador con aprobación, cadencia manual cada 2 días, solo admins, soluciones gratis.

**Por qué no scraping server-side:** Instagram 2026 serve login wall a peticiones anónimas (og tags vacíos, `?__a=1` muerto). Meta oEmbed tokenless prohíbe extraer/persistir contenido para CMS. Bookmarklet = extracción en sesión del editor (sin login wall, sin ToS de scraping automatizado).

## 1. Alcance

- Formulario `/admin/noticias/importar` (GET/POST), permiso `news.approve`, throttle.
- Bookmarklet **híbrido async acotado al post**: abre `about:blank` en el gesto del clic → shortcode de `location.pathname` limita JSON/DOM a ESTE post → **caption = `h1` del article primero** (luego `og:description` solo si no es basura de stats) → **anti-avatar** (`isLikelyAvatarUrl`) → todas las fotos + cover de video van a **`image_urls`** (galería); **sin hero automático** → click-through “Next” solo con indicadores de carrusel → navega al form vía `location.hash`.
- **Portada la elige el editor** en el form: radio `featured_url` entre las imágenes detectadas (default: ninguna → todas a galería) **o** upload `featured_image_file` (prioridad; entonces todas a galería). También se puede cambiar en la edición del borrador.
- **Cover de video/reel (A):** el bookmarklet mete el cover (poster/og:image anti-avatar) en `image_urls` para que pueda elegirse como portada; **no se descarga el mp4**. Link C1 (“Ver publicación original en Instagram”) redirige el tráfico a IG.
- Fallback: pegar URL → intento server-side og-tags (best effort) + campos manuales; `og:image` va a `image_urls` (galería), no a portada.
- Importa siempre como **draft** con `source=instagram` y `source_url` (dedupe).
- **Carrusel:** todas las imágenes detectadas → galería `post_media` (máx. 10; allowlist IG; fallo parcial = warning), salvo la elegida como portada.
- Link “Importar IG” en el listado admin.

### Fuera de alcance (v1 / pendiente opcional)

Auto-publicación · recordatorios · filtro por cuenta · **import de archivo mp4 a galería (pendiente, si lo solicitan)** · scraping programado · oEmbed Meta · otras redes.

## 2. Criterios de aceptación (verificables)

| # | Criterio | Verificación |
|---|---|---|
| CA1 | GET/POST `/admin/noticias/importar` exige auth + `news.approve` | Test: editor → 403; guest → redirect login; superadmin → 200 |
| CA2 | POST crea draft con `source=instagram`, `source_url` canónica, `status=draft`, `published_at=null` | Test feature + aserción BD |
| CA3 | URL IG inválida (no `/p/|/reel/|/tv/` o host distinto) → 422 | Test |
| CA4 | `source_url` ya importada (viva) → warning, sin crear fila | Test + conteo |
| CA5 | Imagen solo de hosts allowlist (`*.cdninstagram.com`, `*.fbcdn.net`); host distinto → 422 o no descarga | Test unit + feature |
| CA6 | Descarga de imagen fallida → draft creado sin portada + warning | Test con `Http::fake` 500 |
| CA7 | `category_id` obligatoria | Test 422 |
| CA8 | Extracto vacío + contenido presente → se deriva automáticamente (≤1000) | Test |
| CA9 | Bookmarklet visible en GET; JS de prefill por hash presente | Test `assertSee` |
| CA10 | Auditoría `news.imported` en activity_logs | Test |
| CA11 | Throttle `import` activo (429 tras tope) | Test con config=2 |
| CA12 | Contenido sanitizado (purifier) y texto LATIN9 (sin emojis) | Test con `<script>` + emoji |
| CA13 | `composer lint` + `phpunit` en verde | Comando |
| CA14 | Rutas admin declaran `permission:*` (coverage test existente) | `AdminRouteAuthorizationCoverageTest` |
| CA15 | Carrusel: `image_urls` JSON → sin selección todas a galería; con `featured_url` esa sale de galería; host evil → 422; >11 → 422; fallo parcial → warning | Tests `NewsImportTest` |
| CA16 | Detalle público con `source_url` → link “Ver publicación original en Instagram” (`rel="noopener noreferrer"`); sin `source_url` → oculto | Tests `NewsImportTest` |
| CA17 | Bookmarklet híbrido: `about:blank` + click-through Next/Siguiente + JSON (`fromJson`) + overlay “recopilando” | Tests `assertSee` en `NewsImportTest` |
| CA18 | Bookmarklet acotado al post (shortcode + `rootArticle`); caption/portada video (`captionText`, `coverImage`, `twitter:`, `poster`); Next solo con `hasCarouselIndicators` | Tests `assertSee` en `NewsImportTest` |
| CA19 | Link “Ver en Instagram” también en bloque de medios (`ig-source-link`); reel importado lo muestra; sin `source_url` no aparece | Tests `NewsImportTest` |
| CA20 | Caption = `h1` del article; `isJunkText` rechaza stats; `isNotAvatar` excluye avatar; título = 1ª línea del caption | Tests `assertSee` en `NewsImportTest` |
| CA21 | `isLikelyAvatarUrl` filtra patrones CDN de perfil en `add()`/`coverImage()`; `collect()` prioriza DOM | Tests `assertSee` en `NewsImportTest` |
| CA22 | **Sin hero automático**: bookmarklet no manda `image_url`; todas las imgs (incl. cover de video) → `image_urls`; portada solo via `featured_url` (radio, debe ∈ `image_urls`) o upload; default = ninguna → todas a galería | Tests `NewsImportTest` |
| CA23 | Cover de video/reel entra en `image_urls` (candidata a portada); mp4 **no** se descarga; C1 ya cubierto en CA16/CA19 | Tests `NewsImportTest` + `assertSee` |
| CA24 | **Fecha IG**: bookmarklet extrae `publishedAt` (`time[datetime]` → `article:published_time` → `taken_at_timestamp`); form `datetime-local` (Caracas); import guarda `published_at` en draft; **approve/bulk conservan** la fecha si existe, si no `now()` | Tests `NewsImportTest` |
| CA25 | **Anti-avatar video**: `isLikelyAvatarUrl` filtra `t51.2885-19` y `s640x640`; `fromJson` prefiere `display_url`/`thumbnail_src`; `coverImage` en video prioriza poster→og→JSON media; cover va **primero** en `image_urls` | Tests `assertSee` en `NewsImportTest` |

## 3. Datos

- Migración: `posts.source` (string null), `posts.source_url` (string null) + índice único parcial `WHERE source_url IS NOT NULL AND deleted_at IS NULL` (permite re-importar tras soft delete).
- `Post::$fillable` += `source`, `source_url`.

## 4. Superficie / stack

- Superficie: 2 endpoints admin (GET form, POST import) + vista + bookmarklet.
- Stack: Laravel 13 existente, `Http` facade (Guzzle del framework), sin dependencias nuevas.
- Secretos: ninguno (sin tokens).

## 5. Seguridad (resumen)

| Control | Mecanismo |
|---|---|
| Authz | `permission:news.approve` en ambas rutas |
| SSRF | Descarga imagen solo allowlist host + https + max 2 redirects validados + timeout 10 s + ≤10 MB + `getimagesizefromstring` |
| CSRF | Form same-origin `@csrf` |
| Rate limit | `throttle:import` por usuario+IP (`NEWS_IMPORT_RATE_LIMIT`, default 20/min) |
| XSS | Purifier en `content` (mutador Post) |
| LATIN9 | `StoresLatin9` + middleware `SanitizeLatin9Input` |
| Dedupe | Índice único parcial + chequeo previo |
| Auditoría | `Audit::log('news.imported', …)` |

## 6. Riesgos

1. DOM de IG cambia → bookmarklet se rompe; mitigación: og:* + JSON embebido + click-through Next/Siguiente + fallback manual en el form.
2. IG solo materializa la slide activa del carrusel en el DOM → sin click-through solo se extraía la 1ª foto (bug corregido con recorrido async).
3. Barrido genérico de scripts/DOM mezclaba posts sugeridos → corregido con scoping por shortcode + `rootArticle` + Next solo con indicadores de carrusel.
4. Reels con `og:description` vacío o con stats → caption desde `h1` del article; cover de video disponible como `image_urls` (selección manual de portada).
5. `og:description`/`og:image` a veces traen contadores de la cuenta o avatar → filtrados con `isJunkText` / `isLikelyAvatarUrl` (incluye `t51.2885-19`, `s640x640`, `profile_pic`); `fromJson` prefiere `display_url`/`thumbnail_src` del media del post; en video `coverImage` prioriza poster/og/JSON y el cover va primero en `image_urls`.
6. og-tags server-side fallan por login wall → esperado; flujo manual sigue funcionando.
7. Fecha de IG en UTC → se convierte a Caracas en el form (`datetime-local`) y al hacer merge server-side; approve conserva `published_at` del draft.
8. URL de imagen firmada expira → si falla, editor sube portada a mano o reselecciona.
9. Bookmarklet no corre en apps móviles de IG → copiar link → importar en desktop.
10. Popup blocker del navegador → alert “Permite popups en Instagram”; el `about:blank` se abre en el gesto del clic.

## 7. Fuera de este plan / pendiente opcional

- **Import de mp4 del reel a la galería** (`videos/` + `post_media` type=video): pendiente; se implementa si lo solicitan. Mientras, el tráfico va al IG vía C1.
- Documentación operativa en `docs/DOCUMENTACION.md` (§5 rutas, §4 posts, §6 controlador) al cierre de F7.
