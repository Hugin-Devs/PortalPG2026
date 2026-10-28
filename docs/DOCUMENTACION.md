# Portal GDC — Documentación del Sistema

## 1. Descripción General

Portal institucional del **Gobierno de Distrito Capital (GDC)**.
Sitio web informativo con panel administrativo para gestión de contenidos.
Inspirado en: https://mippci.gob.ve

---

## 2. Stack Tecnológico

| Componente | Versión |
|---|---|
| PHP | 8.3+ (obligatorio: `composer.json` fija `^8.3`; 8.1/8.2 EOL) |
| Laravel | 13.x (rama `Laravel13-Dev`; producción aún en 10.x hasta U1) |
| Bootstrap | 5.3+ |
| Base de datos | PostgreSQL 14+ recomendado (probado con 18.6). Producción opera con **13.23 / LATIN9** — ver §14.K y §14.L |
| Motor de plantillas | Blade |
| Frontend Assets | Vite |
| JavaScript | Vanilla JS + Bootstrap JS |
| CSS Preprocessor | Sass |

**Prohibiciones explícitas:**
- ❌ Livewire
- ❌ Vue / React / Svelte
- ❌ jQuery

---

## 3. Estructura del Proyecto

```
Portal2026/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # Auth, Dashboard, News, Category, User,
│   │   │   │                     # Partner, Media, OrgMember, About, Role, Rrss,
│   │   │   │                     # Gaceta, Contact, Settings
│   │   │   └── Public/           # Home, News, Ente, About, Contact, Search, Page, Gaceta
│   │   └── Middleware/
│   │       ├── CheckPermission.php   # alias 'permission' (superadmin tiene bypass)
│   │       └── CheckRole.php         # alias 'role' (registrado, hoy no se usa en rutas)
│   └── Models/                   # Category, Contact, Gaceta, Media, OrgMember, Page,
│                                 # Partner, Permission, Post, Setting, User (11)
├── database/
│   ├── migrations/               # 18 migraciones (incl. org_members, permissions (+module),
│   │                             #  role_permission con unique(role, permission_id))
│   └── seeders/                  # Database, Category, Post, User, Permission, Partner,
│                                 # ContactSetting, SocialSetting (8)
├── public/
│   ├── build/                    # Assets compilados por Vite
│   ├── images/                   # Logo e imágenes estáticas
│   └── storage -> ../storage/app/public   # symlink (uploads de medios)
├── resources/
│   ├── css/app.scss              # Sass personalizado + Bootstrap
│   ├── js/app.js                 # Bootstrap JS + inicializaciones (vanilla)
│   └── views/
│       ├── layouts/              # public.blade.php (navbar+footer),
│       │                         # admin.blade.php (sidebar + checks de permisos)
│       ├── public/               # home, news, news-show, news-category,
│       │                         # entes, ente-show, about, contact,
│       │                         # search, page, gacetas
│       └── admin/                # auth, dashboard, news, categories, users,
│                                 # partners, media, org-members, about, roles,
│                                 # contact, rrss, gacetas, settings
├── routes/web.php
├── vite.config.js
├── composer.json
└── package.json
```

---

## 4. Base de Datos

### 4.1 users
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | autoincrement |
| name | varchar(255) | |
| email | varchar(255) | unique |
| email_verified_at | timestamp | nullable |
| password | varchar(255) | hasheado |
| role | enum('superadmin','admin','editor') | default 'editor' |
| is_active | boolean | default true — login bloqueado si es false (mensaje genérico) |
| last_login_at | timestamp | nullable — se registra al iniciar sesión |
| remember_token | varchar(100) | nullable |
| timestamps | | |

### 4.2 categories
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| slug | varchar(255) | unique |
| description | text | nullable |
| icon | varchar(255) | nullable |
| parent_id | bigint FK | nullable, self-referencing |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.3 posts
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| excerpt | text | nullable |
| content | longtext | |
| featured_image | varchar(255) | nullable |
| category_id | bigint FK | nullable, nullOnDelete |
| user_id | bigint FK | constrained (author) |
| status | enum('draft','published') | default 'draft' |
| published_at | timestamp | nullable |
| is_featured | boolean | default false |
| views | integer | default 0 |
| timestamps | | |
| deleted_at | timestamp | softDeletes |

### 4.4 partners (Entes Afiliados)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| slug | varchar(255) | unique, nullable — auto `Str::slug(name)` (sufijo si colisiona); usado en `/entes/{slug}` |
| description | text | nullable — tarjeta del listado y página de detalle |
| logo | varchar(255) | nullable — ruta bajo disco público (ej. `partners/1791…png`); se sube desde `/admin/entes`, nunca se escribe a mano |
| url | varchar(255) | nullable |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.5 pages
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| content | longtext | nullable |
| is_active | boolean | default true |
| timestamps | | |

### 4.6 media
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| filename | varchar(255) | |
| original_name | varchar(255) | |
| path | varchar(255) | |
| mime_type | varchar(255) | |
| size | integer | bytes |
| user_id | bigint FK | uploader |
| timestamps | | |

### 4.7 settings
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| key | varchar(255) | unique |
| value | text | nullable |
| timestamps | | |

Claves: `social_*` (7), `contact_*` (5) y las generales `site_name`, `site_logo`, `nav_funcionarios_url`, `nav_jubilados_url`, `nav_intranet_url` (panel `/admin/configuracion`). Los valores por defecto de las generales viven en `Setting::defaults()`: el sitio funciona **sin filas** en la tabla.

### 4.8 org_members (Estructura Organizativa)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| position | varchar(255) | cargo |
| photo | varchar(255) | nullable — ruta manual de imagen (input texto en el form, no hay upload) |
| description | text | nullable — descripción/biografía |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.9 permissions
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | nombre legible |
| slug | varchar(255) | unique — ej. `news.create` |
| group | varchar(255) | nullable — agrupación en la UI de roles |
| description | text | nullable |
| timestamps | | |

### 4.10 role_permission (rol ↔ permiso)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| role | varchar(255) | `superadmin` / `admin` / `editor` — único junto con `permission_id` |
| permission_id | bigint FK | permissions, cascadeOnDelete |
| timestamps | | |

---

### 4.11 contacts (mensajes del formulario)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) | |
| subject | varchar(255) | |
| message | text | |
| read_at | timestamp | nullable — se marca al abrir el detalle en el admin |
| timestamps | | |

### 4.12 gacetas (Gacetas Oficiales)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| numero | varchar(20) | solo dígitos (1–6), p.ej. `045`; sin unicidad |
| fecha | date | fecha de publicación; índice |
| path | varchar | ruta en disco `public/gacetas/` |
| original_name | varchar | nombre original del archivo |
| mime_type | varchar | `application/pdf` o `image/*` |
| size | bigint | nullable |
| user_id | bigint FK nullable | usuario que la cargó (users, `nullOnDelete`) |
| timestamps | | |

### 4.13 daily_views (visitas diarias por artículo)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| day | date | día calendario; **usa la timezone de la app** (`config/app.timezone`, hoy `UTC` — al cerrar B1 el bucketing pasa a `America/Caracas`; las filas previas conservan su etiqueta); índice + parte del único |
| post_id | bigint FK | artículo (posts, `cascadeOnDelete` — solo aplica en borrado físico, la app usa soft delete) |
| count | unsigned int | visitas del día a ese artículo (default 0) |
| unique | (day, post_id) | **invariante**: el upsert depende de este índice |

- **Registro**: `Public\NewsController@show` ejecuta un upsert atómico `ON CONFLICT ("day","post_id") DO UPDATE SET count = daily_views.count + 1` (una sentencia) además del contador lifetime `posts.views`. La fecha **siempre se enlaza desde PHP** (`now()->toDateString()`), nunca `CURDATE()` de motor (relojes distintos).
- **Nota histórica**: la versión MariaDB usaba `INSERT ... ON DUPLICATE KEY UPDATE`; al migrar a PostgreSQL (oct-2026) se sustituyó por `ON CONFLICT ... DO UPDATE` sobre el índice único `(day, post_id)`, conservando la atomicidad y eliminando la carrera insert/increment.
- **Sin backfill**: el conteo diario comienza con la activación; no hay histórico anterior. Consumidores: tarjeta "Visitas del mes" y gráfico de 30 días del dashboard (D1).

---

## 5. Rutas

### 5.1 Públicas

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/` | `Public\HomeController@index` | home |
| GET | `/noticias` | `Public\NewsController@index` | news.index |
| GET | `/noticias/{slug}` | `Public\NewsController@show` | news.show |
| GET | `/categoria/{slug}` | `Public\NewsController@category` | news.category |
| GET | `/entes` | `Public\EnteController@index` | entes.index |
| GET | `/entes/{slug}` | `Public\EnteController@show` | entes.show |
| GET | `/sobre-nosotros` | `Public\AboutController@index` | about.index |
| GET | `/contacto` | `Public\ContactController@index` | contact.index |
| POST | `/contacto` | `Public\ContactController@store` | contact.store |
| GET | `/buscar` | `Public\SearchController@index` | search |
| GET | `/gacetas` | `Public\GacetaController@index` | gacetas.index |
| GET | `/pagina/{slug}` | `Public\PageController@show` | page.show |
| GET | `/servicios` | redirect 301 → `/entes` | (compatibilidad) |

### 5.2 Admin (prefijo `/admin`, dentro de middleware `auth`)

Todas las rutas de gestión llevan además `middleware('permission:...')` (ver sección 9).

| Método | URI | Permiso | Controlador |
|---|---|---|---|
| GET/POST | `/admin/login` | — | `Admin\AuthController` |
| POST | `/admin/logout` | — | `Admin\AuthController@logout` |
| GET | `/admin` | — | `Admin\DashboardController@index` |
| GET | `/admin/noticias` (+ crear/editar/PUT/DELETE) | `news.*` | `Admin\NewsController` |
| GET | `/admin/categorias` (+ crear/editar/PUT/DELETE) | `categories.*` | `Admin\CategoryController` |
| GET | `/admin/entes` (+ crear/editar/PUT/DELETE) | `partners.*` | `Admin\PartnerController` |
| GET | `/admin/usuarios` (+ crear/editar/PUT/DELETE) | `users.*` | `Admin\UserController` |
| GET/POST/DELETE | `/admin/medios` | `media.*` | `Admin\MediaController` |
| GET/POST/DELETE | `/admin/videos` (+ `POST /admin/videos/destacar`) | `videos.*` | `Admin\VideoController` |
| GET/POST | `/admin/gacetas` (+ editar/PUT/DELETE) | `gacetas.*` | `Admin\GacetaController` |
| GET/PUT | `/admin/nosotros` | `about.edit` | `Admin\AboutController` |
| GET/PUT | `/admin/contacto` | `contact.edit` | `Admin\ContactController` |
| GET/DELETE | `/admin/contacto/mensajes` (+ `/{contact}`) | `contact.view` / `contact.delete` | `Admin\ContactController` |
| GET | `/admin/estructura` (+ crear/editar/PUT/DELETE) | `org.*` | `Admin\OrgMemberController` |
| GET/POST | `/admin/roles` | `roles.manage` | `Admin\RoleController` |
| GET/PUT | `/admin/rrss` | `rrss.manage` | `Admin\RrssController` |
| GET/PUT | `/admin/configuracion` | `settings.manage` | `Admin\SettingsController` |

> **Nota:** todas las rutas viven en `routes/web.php` (no existe `routes/admin.php`; el panel usa el prefijo `/admin` ahí mismo). El boilerplate `routes/api.php` de Laravel fue **eliminado** (C5): no hay API; el `RouteServiceProvider` solo carga `web.php`.

---

## 6. Controladores

### 6.1 Públicos (`app/Http/Controllers/Public/`)

| Controlador | Métodos | Estado |
|---|---|---|
| `HomeController` | `index()` | ✅ Destacados + últimos + categorías + "más leído" + **video destacado** (`Video::where('is_featured')`, 0 ó 1; vacío = oculta la sección) |
| `NewsController` | `index()` | ✅ Listado paginado + categorías para el filtro (sidebar/pills) |
| | `show($slug)` | ✅ Detalle + incrementa `views` + relacionados |
| | `category($slug)` | ✅ Filtrado por categoría |
| `EnteController` | `index()` / `show($slug)` | ✅ Listado y detalle de entes (sobre `partners`, activos y por `order`) |
| `AboutController` | `index()` | ✅ Página institucional |
| `ContactController` | `index, store` | ✅ Valida y **persiste** en `contacts` (bandeja en el admin) |
| `PageController` | `show($slug)` | ✅ Páginas estáticas |
| `GacetaController` | `index()` | ✅ Listado de gacetas ordenado por `fecha DESC, numero DESC` (paginado 12); cada card abre el archivo en pestaña nueva |
| `SearchController` | `index()` | ✅ Búsqueda por título/contenido/extracto |

### 6.2 Admin (`app/Http/Controllers/Admin/`)

| Controlador | Métodos | Estado |
|---|---|---|
| `AuthController` | `showLogin` / `login` / `logout` | ✅ Login: registra `last_login_at`; cuentas **inactivas** rechazadas con mensaje genérico ("Credenciales inválidas.", redirect explícito al login) |
| `DashboardController` | `index()` | ✅ Panel real: 9 tarjetas de conteos filtradas por permiso (`data-stat`), "Visitas del mes" desde `daily_views` (contador diario con tracking en `NewsController@show`), gráfico CSS de 30 días (sin librerías), últimas 5 noticias publicadas con link de edición |
| `NewsController` | CRUD completo (`index, create, store, edit, update, destroy`) | ✅ Slug automático, soft deletes; **imagen destacada/portada**: archivo nuevo (≤10 MB, `image\|mimes:...`, guardado en `uploads/YYYY/MM/`) o path de la biblioteca (validado `Rule::exists('media','path')` con `mime_type LIKE image/%`); archivos nuevos se auto-registran en `media`; prioridad: quitar > archivo > biblioteca > conservar; **no** borra archivos al reemplazar (limpieza desde `/admin/medios`); **D15 multimedia**: tabla `post_media` (galería de la noticia: imágenes y videos enlazados, `position` + reorden vía `POST /admin/noticias/{post}/media/{media}/move`), límites 10 imágenes / 3 videos por noticia, videos nuevos se **crean en el módulo Videos** (`is_featured=false`) y se enlazan, `remove_media[]` solo acepta filas propias (los archivos no se borran, paridad con `remove_image`), aviso flash `warning` cuando el contenido traía imágenes pegadas (se eliminan con el sanitizador) |
| `CategoryController` | CRUD completo | ✅ |
| `UserController` | CRUD completo | ✅ Asignación de roles + checkbox `is_active` (marcado por defecto al crear) + guard: no puedes desactivarte a ti mismo |
| `PartnerController` | CRUD completo | ✅ Entes afiliados (footer); logo por **upload** a `partners/` en disco `public` (reemplaza y borra el anterior; checkbox "quitar imagen"; borra archivo al eliminar el ente) |
| `MediaController` | `index, store, destroy` | ✅ Subida **múltiple** a disco `public` en **`uploads/YYYY/MM/`** (carpeta por año/mes desde D15, `files[]`, 10 MB por archivo), requiere `storage:link`; respuesta **JSON** (`{location}`) para el editor TinyMCE; **biblioteca reutilizable** desde el modal "Elegir de la biblioteca" de noticias + botón "Copiar URL" |
| `OrgMemberController` | CRUD completo | ✅ Estructura organizativa |
| `AboutController` | `index, update` | ✅ Edición de "Sobre Nosotros" |
| `ContactController` | `editInfo, updateInfo, messages, showMessage, destroyMessage` | ✅ Info de contacto en `settings` (`contact_address/phone/email/hours/map_url`) + bandeja de mensajes (`contacts`); parcial público `_contact-info` con defaults |
| `RoleController` | `index, update` | ✅ Matriz rol×permiso (un formulario JS por rol); `index` ordena en 3 niveles igual que el sidebar: `group` → `module` → acción (`FIELD()` sobre la parte final del slug); `update` borra y reinserta los permisos del rol |
| `RrssController` | `index, update` | ✅ Redes sociales: 7 settings `social_facebook/instagram/youtube/x/threads/telegram/whatsapp` — **multi-línea (una URL por línea = varias cuentas por red)**: validación línea a línea (`filter_var(FILTER_VALIDATE_URL)` + `^https?://`, mensaje «La línea N no es una URL válida», `max:4000`) y guardado normalizado (CRLF→LF, sin líneas vacías) |
| `SettingsController` | `index, update` | ✅ Identidad / Enlaces: `site_name` (required ≤100) + enlaces del navbar (`nav_funcionarios_url`, `nav_jubilados_url`, `nav_intranet_url`: URL absoluta válida **o** ruta `/...` sin `..` con `file_exists` en `public/`; vacío = ocultar ítem) + logo (upload `image` ≤2 MB a `settings/logo.{ext}` en disco `public`, checkbox "quitar logo"); redirige con flash |
| `GacetaController` | `index, store, edit, update, destroy` | ✅ Gacetas Oficiales: subida a `gacetas/` en disco `public` (PDF/imagen ≤20 MB), `numero` (solo dígitos) + `fecha`; `update` puede reemplazar el archivo (borra el anterior); `destroy` borra archivo físico |
| `VideoController` | `index, store, destacar, destroy` | ✅ Videos (D7): pestaña 1 carga YouTube (URL validada por regex → `embed_url`) **o** archivo (mp4/webm/ogg ≤100 MB a `videos/` en disco `public`); pestaña 2 marca **un solo** `is_featured` (radio + "Ninguno" limpia todos); `destroy` borra archivo físico |

---

## 7. Vistas

### 7.1 Layouts

| Archivo | Descripción |
|---|---|
| `layouts/public.blade.php` | Layout público: header con logo y título desde `settings` + **navbar configurable** (ítems Funcionarios Activos/Jubilados/Intranets GDC ocultos si su enlace está vacío) + footer; enlaces y bloque "Entes Afiliados" **ocultos con comentario Blade** (módulo en preparación — PENDIENTES E5) |
| `layouts/admin.blade.php` | Layout admin: sidebar con **3 secciones** — "Gestión Noticias" (collapse "Multimedia" con "Medios"/"Videos" + "Gacetas"), "Gestión Portal" (Categorías, Entes Afiliados, Sobre Nosotros, Contacto, Mensajes, Estructura Org., Redes Sociales) y "Configuración Portal" (Identidad / Enlaces, Usuarios, Roles y Permisos); cabecera de sección visible si el rol tiene **algún** permiso de sus ítems vía `User::hasAnyPermission()` (lista de slugs en `@php`), cada ítem solo con su permiso vía `hasPermission()`; collapse Multimedia abierto en `admin.media.*`/`admin.videos.*` (el `ul#mmMenu` lleva `flex-nowrap`: sin él, `.nav { flex-wrap: wrap }` + `flex-direction: column` hacía que "Medios"/"Videos" se envolvieran a una columna lateral durante la animación de `height` de Bootstrap Collapse) + topbar + main; título y logo desde `settings` |

### 7.2 Públicas (`resources/views/public/`)

| Archivo | Secciones | Estado |
|---|---|---|
| `home.blade.php` | Hero slider de destacados (imagen de fondo con velo + fallback azul si no hay imagen), categorías, **sección "VIDEOS INSTITUCIONALES"** (si hay `is_featured`: iframe YouTube embed **o** `<video controls>` para archivo subido), grid de noticias, "Lo más leído" | ✅ |
| `news.blade.php` | Listado paginado de noticias + filtro por categoría (sidebar desktop con conteos + buscador; pills en móvil) | ✅ |
| `news-show.blade.php` | Detalle de noticia + botones de compartir (FB, X, WhatsApp, Telegram, copiar enlace con vanilla JS y fallback para HTTP plano) + relacionados; contenido vía `rendered_content` (HTML del editor, con compat nl2br para texto plano legado); **carrusel multimedia (D15/D17)**: con portada+galería/videos renderiza carrusel Bootstrap `#newsGallery` (`data-bs-interval="false"`, portada primero) — slides de imagen a **560px** con la foto a proporción natural centrada sobre blanco (sin recorte, sin `loading="lazy"`, `decoding="async"`), `<iframe>` YouTube / `<video>` a 560px, flechas con `aria-label` e iconos oscuros (CSS inline scoped a `#newsGallery`); solo portada = mismo wrapper 560px sin carrusel | ✅ |
| `news-category.blade.php` | Noticias por categoría + mismo nav de categorías (activa resaltada) | ✅ |
| `_category-pills.blade.php` | Pills scrollables de categorías (móvil/tablet) | ✅ |
| `_category-sidebar.blade.php` | Sidebar desktop: buscador + categorías con conteo de publicadas | ✅ |
| `entes.blade.php` / `ente-show.blade.php` | Listado (logo, nombre, descripción, link externo o a detalle) + **buscador con filtro instantáneo** (vanilla JS inline, case- y acento-insensible, sin recarga; input vacío = mostrar todos; sin coincidencias → mensaje); detalle | ✅ |
| `about.blade.php` | "Sobre Nosotros" | ✅ |
| `contact.blade.php` | Formulario de contacto + datos de contacto | ✅ |
| `search.blade.php` | Resultados de búsqueda | ✅ |
| `page.blade.php` | Páginas estáticas — contenido vía `rendered_content` (HTML o texto plano con nl2br) | ✅ |
| `gacetas.blade.php` | Gacetas Oficiales: grid de cards (miniatura real si es imagen, icono PDF si no), "Gaceta Oficial N° {numero}" + fecha; click abre el archivo en pestaña nueva | ✅ |
| `_social-links.blade.php` | Parcial de íconos de redes (1 query `whereIn`; solo renderiza URLs `http(s)://`; vacío = oculto) — solo en el footer | ✅ |
| `_contact-info.blade.php` | Parcial con datos de contacto desde `settings` (dirección, teléfono, email, horario, mapa) + defaults — en home, /contacto y /sobre-nosotros | ✅ |

> **Componente de imágenes**: las vistas públicas + logos (header/footer público, sidebar admin y login) usan el componente Blade `<x-img path="storage/..." ...attrs>` (`resources/views/components/img.blade.php`) en lugar de `<img>`: si existe el sidecar `.webp` (mismo nombre con extensión `.webp`, ver `App\Services\Webp`) emite `<picture>` con `<source type="image/webp">` y el `<img src>` apuntando al **original como fallback**; si no existe, `<img>` idéntico al anterior (bitácora transparente para el navegador). Los atributos (`alt`, `class`, `loading`, etc.) se pasan tal cual y conservan su orden. La regla CSS `picture { display: contents; }` (en `app.scss`) evita que el `<picture>` altere layout, gap de línea base o posicionamiento.

> La plantilla por defecto `welcome.blade.php` fue eliminada (C5): la ruta `/` apunta a `HomeController`.

### 7.3 Admin (`resources/views/admin/`)

| Módulo | Archivos | Estado |
|---|---|---|
| Auth | `auth/login.blade.php` | ✅ Login independiente |
| Dashboard | `dashboard/index.blade.php` | ✅ Tarjetas (`col-6/col-md-4/col-xl-3`, icono FA + `stat-number`, link al módulo) con `hasPermission()` por tarjeta: Noticias, Visitas del mes, Usuarios, Mensajes sin leer, Gacetas, Medios, Entes, Videos, Categorías (editor ve 3, admin 7, superadmin 9) + gráfico `.dash-bar` de 30 días (barras CSS con `title` por día, estado vacío si no hay visitas) + tabla "Últimas noticias publicadas"; sin botón logout (lo tiene el layout) |
| Noticias | `news/{index,create,edit}` + parciales `news/_image-field`, `news/_gallery-field`, `news/_editor` | ✅ Imagen de portada: preview + upload + modal de biblioteca (vanilla JS inline); **galería y videos** (D15): imágenes múltiples con reorden ↑/↓ y remove, videos existentes/en nuevos; contenido con **TinyMCE 8 auto-hospedado** (`public/vendor/tinymce`, español, **sin plugin image** desde D15 — las imágenes se gestionan con portada/galería) |
| Categorías | `categories/{index,create,edit}` | ✅ |
| Usuarios | `users/{index,create,edit}` | ✅ Columnas Estado (Activo/Inactivo) y Última conexión; checkbox "Usuario activo" |
| Entes afiliados | `partners/{index,create,edit}` | ✅ Subida de logo (`file`, preview, quitar) |
| Medios | `media/index.blade.php` | ✅ Grid + subida múltiple (`files[]`, lista de archivos seleccionados en vanilla JS) |
| Videos | `videos/index.blade.php` | ✅ 2 pestañas Bootstrap: **Cargar videos** (toggle YouTube URL / archivo mp4+webm+ogg, grid con badge "Portada" y eliminar) y **Mostrar en portada** (radio por video + "Ninguno"); el JS abre la pestaña del `#hash` |
| Gacetas Oficiales | `gacetas/{index,edit}` | ✅ Grid + formulario "Nueva Gaceta" (archivo + N° + fecha) y edición con preview |
| Estructura org. | `org-members/{index,create,edit}` | ✅ |
| Sobre Nosotros | `about/index.blade.php` | ✅ Edición inline |
| Contacto | `contact/{index,messages,message-show}` | ✅ Info (5 campos) + bandeja con estado Nuevo/Leído, detalle y borrado |
| Redes Sociales | `rrss/index.blade.php` | ✅ 7 URLs de redes (vacío = oculto en el sitio) |
| Identidad / Enlaces | `settings/index.blade.php` | ✅ Nombre del sitio + logo (preview/quitar) + 3 enlaces del navbar (Funcionarios Activos, Jubilados, Intranets GDC); vacío = ocultar ítem |
| Roles y permisos | `roles/index.blade.php` | ✅ Matriz con envío JS; doble cabecera: categoría (`table-secondary`) + módulo (`table-light`, clase `perm-module`), permisos indentados |

### 7.4 Vistas de error (`resources/views/errors/`)

Páginas personalizadas en español para todos los tipos de error HTTP, con botón **Volver** (history con fallback a `/`) e **Ir al inicio**:

| Código | Tipo | Presentación |
|---|---|---|
| 400, 401, 403, 404, 405, 408, 410, 419, 422, 429 | 4xx | Extienden `layouts.public` (parcial `_box`); 419 agrega **Recargar e intentar**, 401 **Iniciar sesión**, 403 **Ir al panel** si hay sesión |
| 500, 503 | 5xx | Standalone (parcial `_standalone`, HTML+CSS inline, sin layout/BD/Vite) para renderizar aunque el sitio esté caído; 503 agrega **Reintentar** |

---

## 8. Diseño y Paleta de Colores

### Colores

| Color | Hex | Uso |
|---|---|---|
| Azul oscuro (primary) | `#273376` | Acento de UI pública: títulos, separadores, hover navbar, flechas y hover del footer, badges de categoría, CTAs outline, botones |
| Rojo/burgundy (danger) | `#A70336` | Solo estados de sistema en admin: alertas de error, botones Eliminar, badge “No” / superadmin |
| Amarillo/dorado | `#FFC907` | Detalles decorativos, botones warning (hero, Editar en admin) |
| Blanco | `#FFFFFF` | Fondos |
| Gris oscuro | `#333333` | Texto del cuerpo |
| Gris secundario | `#54595F` | Metadatos, textos secundarios |
| Gris claro | `#AFAFAF` | Bordes, separadores |

### Tipografía

| Estilo | Fuente |
|---|---|
| Headings (h1-h6) | `Georama MIPPCI` / `Georama` |
| Body | `Instrument Sans` |
| Acentos | `Georama MIPPCI` SemiBold |

### Assets
- Logo: `resources/images/logo.png` → se sirve desde `public/images/logo.png` vía `asset('images/logo.png')`
- Estilos: `resources/css/app.scss` → compilado por Vite
- JS: `resources/js/app.js` → Bootstrap + inicialización de dropdowns

---

## 9. Autenticación y Roles

### Roles disponibles
- **superadmin**: Acceso total, gestión de usuarios
- **admin**: CRUD completo de contenidos
- **editor**: Crear/editar noticias, no puede publicar ni eliminar

### Flujo de login
1. Usuario visita `/admin/login`
2. Ingresa email + password
3. Laravel `auth()->attempt()` verifica credenciales
4. Si es válido, redirige a `/admin`
5. Middleware `auth` protege todas las rutas admin

### Sistema de permisos (granular, por rol)

- **Tabla**: `permissions` (41 permisos con `slug` tipo `news.create`) + `role_permission` (rol ↔ permiso, con índice único). Reparto de asignaciones (68 filas): superadmin 41 (todos), admin 22, editor 5 (`news.view/create/edit`, `media.view`, `media.upload`). Slugs de solo-superadmin (19): `news.delete`, `media.delete`, los 4 de `videos.*`, `partners.create/edit/delete`, `org.delete`, `rrss.manage`, `settings.manage`, los 4 de `users.*`, `roles.manage`, `audit.view`, `audit.export`.
- **Grupos** (`permissions.group`, 1er nivel): los 41 permisos están agrupados en los **mismos 3 nombres que las secciones del sidebar** — `Gestión Noticias` (16: `news.*`, `media.*`, `videos.*`, `gacetas.*`), `Gestión Portal` (17: `categories.*`, `partners.*`, `about.*`, `contact.*`, `org.*`, `rrss.manage`) y `Configuración Portal` (8: `settings.manage`, `users.*`, `roles.manage`, `audit.*`).
- **Módulos** (`permissions.module`, 2º nivel): cada permiso indica además el **módulo/función = ítem del sidebar** (15 en total): `Noticias`, `Medios`, `Videos`, `Gacetas` · `Categorías`, `Entes Afiliados`, `Sobre Nosotros`, `Contacto` (solo `contact.edit`), `Mensajes` (`contact.view/delete`), `Estructura Org.`, `Redes Sociales` · `Identidad / Enlaces`, `Usuarios`, `Roles y Permisos`, `Auditoría` (`audit.view/export`). La vista `/admin/roles` renderiza categoría → módulo → permiso, ordenadas con `FIELD()` (categoría, módulo y acción `view/create/upload/edit/manage/delete`); los módulos no se repiten entre categorías.
- **Middleware**: alias `permission` registrado en `app/Http/Kernel.php` → `App\Http\Middleware\CheckPermission`. Aplicado ruta a ruta en `routes/web.php` (ej. `->middleware('permission:news.edit')`). El rol `superadmin` tiene bypass. El alias `role` → `App\Http\Middleware\CheckRole` también está registrado, pero hoy no se usa en ninguna ruta.
- **Consulta en vista**: `auth()->user()->hasPermission('slug')` y `hasAnyPermission([slugs])` (`app/Models/User.php`) — las vistas del admin ocultan botones/menús sin permiso; el sidebar usa `hasAnyPermission()` para decidir si la cabecera de cada sección ("Gestión Noticias", "Gestión Portal", "Configuración Portal") es visible.
- **Gestión**: módulo `/admin/roles` (`RoleController`) muestra la matriz rol×permiso; cada rol envía un solo formulario (JS junta los checkbox en un input oculto) y `update()` reemplaza las asignaciones del rol.

### Usuarios por defecto (seed)

Creados por `UserSeeder` — password: `password`

| Email | Rol |
|---|---|
| `admin@gdc.gob.ve` | superadmin |
| `admin2@gdc.gob.ve` | admin |
| `editor@gdc.gob.ve` | editor |

> El seed es **autoritativo en nombre y rol**: si cambias el rol de un usuario desde `/admin/usuarios`, un `php artisan db:seed` lo restaura al canónico (el password de usuarios existentes no se pisa). Si el "superadmin" desaparece del panel, es que su rol fue editado — ejecuta el seed o corrige el rol en BD.

---

## 10. Seeders

Ejecutan en este orden (definido en `DatabaseSeeder`):

| Seeder | Qué crea |
|---|---|
| `CategorySeeder` | 6 categorías: Nacionales, Política, Comunal, Cultura, Deporte, Educación |
| `NoticiasPrecargadasSeeder` | **Seeder por defecto de las noticias**: lote de noticias reales desde `public/Noticias Precargadas/` (D16): por carpeta, imágenes `001…NNN` + un `.txt` con el cuerpo y una línea final `Fecha:` (metadato); 001 → `featured_image`, 002+ → galería `post_media`; fecha del sello del nombre de la 001 (fallback: línea Fecha); copia a `uploads/YYYY/MM/` + fila `media` + sidecar WebP; texto íntegro (solo se excluye la línea Fecha); idempotente (omite posts ya sembrados con portada); **purga** con `withTrashed()` + `forceDelete()` de las placeholders `noticia-destacada-*` (borrado físico); corre tras `CategorySeeder`; si aún no hay usuarios, crea `admin@gdc.gob.ve` con `firstOrCreate` |
| `UserSeeder` | 3 usuarios: superadmin, admin, editor (password `password`) — `firstOrCreate` + restaura nombre/rol canónicos |
| `PermissionSeeder` | 41 permisos en 2 niveles alineados al sidebar: `group` = 3 categorías (`Gestión Noticias` 16 / `Gestión Portal` 17 / `Configuración Portal` 8) y `module` = 15 módulos/ítems (incluye `settings.manage` con nombre "Editar identidad y enlaces", los 4 `videos.*` y `audit.view`/`audit.export`, solo superadmin) + asignaciones a los 3 roles (41/22/5 = 68 filas) — idempotente y **migrador**: `updateOrCreate` corrige `group`/`module`/`name` de filas existentes |
| `PartnerSeeder` | **Borrador** — array vacío, pendiente de contenido (name, description, url, order; slug auto y logo vía panel) |
| `ContactSettingSeeder` | Los 5 settings de contacto (`contact_address/phone/email/hours/map_url`) con los valores por defecto — `firstOrCreate`: **no pisa** lo editado desde `/admin/contacto` |
| `SocialSettingSeeder` | Los 7 settings de redes (`social_facebook/instagram/youtube/x/threads/telegram/whatsapp`) — `REDES` es el **espacio para pegar las URLs reales** (una por línea; varias líneas = varias cuentas de la misma red, D14); crea las claves faltantes y **rellena solo los valores vacíos**: nunca pisa lo editado desde `/admin/rrss` |

**Plantilla (no corre por defecto)**:

| Seeder | Qué crea |
|---|---|
| `PostSeeder` | **PLANTILLA duplicable**, ya **no** se invoca desde `DatabaseSeeder` (las noticias reales las carga `NoticiasPrecargadasSeeder`). Array declarativo de 4 noticias de ejemplo: publicada + destacada, migrada de redes con fecha histórica pasada, borrador de prueba y programada de prueba — docblock con la receta de duplicación (cambiar `title`/`slug` único, `category` por slug, `status`+`published_at` para publicar/programar/migrar), `content` en nowdoc para pegar HTML tal cual, categoría por slug de `CategorySeeder` con fallback a la 1ª, autor por email con fallback; idempotente vía `updateOrCreate(['slug' => ...])`. Ejecución manual: `php artisan db:seed --class=PostSeeder` |

**Idempotentes**: los 7 seeders de `DatabaseSeeder` (y también la plantilla `PostSeeder`) se pueden re-ejecutar sin duplicar filas ni fallar (`firstOrCreate` / `updateOrCreate` / `upsert` contra el índice único `role+permission_id`).

### Ejecutar seeders
```powershell
php artisan db:seed
```

### Refrescar BD completa (borra todo y migra de nuevo)
```powershell
php artisan migrate:fresh --seed
```

---

## 11. Assets y Compilación

### Modo desarrollo (hot reload)
```powershell
npm run dev
# En otra terminal:
php artisan serve
```

### Build de producción (assets finales)
```powershell
npm run build      # copia TinyMCE a public/vendor + vite build a public/build
```
> `public/build/` y `public/vendor/` **no se versionan** (`.gitignore`): en el servidor hay que compilar o subirlos — ver §14.3.C.

### Archivos importantes
- Vite config: `vite.config.js`
- SCSS entry: `resources/css/app.scss`
- JS entry: `resources/js/app.js`
- Build output: `public/build/`

---

## 12. Estado del Desarrollo

> El seguimiento vivo del trabajo vive en [PENDIENTES.md](PENDIENTES.md).
> Los riesgos de seguridad, waivers y condiciones de despliegue vigentes
> están en [SECURITY.md](../SECURITY.md) (raíz del repo). Las mejoras
> futuras, en la §C de PENDIENTES.md.
>
> **Estado a 2026-10-28**: **producción en Laravel 13.38.0** (despliegue U1
> el 2026-10-28 desde `main` = `d4d3e91`; registro en **§14.M**) — 7
> condiciones de `SECURITY.md` §1 verificadas y re-verificadas tras el
> upgrade. Gates: **303 tests PHPUnit en verde** (1353 assertions),
> **cobertura 97.58%** (app 98.05%), **E2E 17/17**, **baseline ZAP final:
> 0 altas / 0 medias / 1 baja / 4 informativas** (waiver TinyMCE
> `style-src` cerrado con CSP de nonce), Pint ✅, SCA 0/0.
>
> El despliegue del 2026-10-25 (Laravel 10.50.2) quedó registrado en §14.L;
> el de la migración completa 10 → 11 → 12 → 13 está en `docs/PLAN-LARAVEL13.md`.
>
> Quedan 2 ítems de contenido del cliente (D6/E1) y, como bloqueantes para
> la salida a internet, el **certificado TLS** (R5) y la **copia de seguridad de
> la BD** (R6) (ver [PENDIENTES.md](PENDIENTES.md)).

---

## 13. Desarrollo Local (Linux / PostgreSQL)

> Esta sección es para **desarrollo**. Para el servidor real, ve §14 (Despliegue en Producción).

Requisitos: PHP 8.3+ (PHP 8.1/8.2 EOL, no admitidos) con extensiones `pdo_pgsql`, `mbstring`, `openssl`, `gd` (con WebP), `curl`, `dom`; PostgreSQL 14+; Node.js 18+; Composer. (Entorno de referencia de la migración PG: Termux/Android con PostgreSQL 18.6 y PHP 8.5.)

```bash
# 1. Navegar al proyecto
cd PortalPG2026

# 2. Dependencias (solo la primera vez)
composer install
npm ci

# 3. Compilar assets (cuando cambien Sass/JS) — necesario para que las
#    vistas con @vite rendericen (si no, 500 "Vite manifest not found")
npm run build

# 4. Base de datos: crear rol y BD dedicados (una sola vez)
createuser -P portalpg          # pedirá contraseña
createdb -O portalpg portalpg2026

# 5. Configurar .env (copiar la plantilla y completar)
cp .env.example .env
#    DB_CONNECTION=pgsql, DB_PORT=5432, DB_DATABASE=portalpg2026,
#    DB_USERNAME=portalpg, DB_PASSWORD=..., DB_SSLMODE=prefer
php artisan key:generate

# 6. Migrar y sembrar (primera vez / tras migrate:fresh)
php artisan migrate --seed
php artisan storage:link   # enlace público -> storage/app/public (subidas de medios)

# 7. Arrancar el servidor de desarrollo
php artisan serve --port=8000   #  →  http://localhost:8000
#    Panel: http://localhost:8000/admin/login  (admin@gdc.gob.ve / password)
```

> **Sesión segura por entorno:** con `APP_ENV=local` la cookie de sesión se envía sin el flag `Secure` (permite desarrollo sobre HTTP). Si `APP_ENV` no es `local`, `config/session.php` activa `secure=true` por defecto; en producción es **obligatorio HTTPS** y se puede forzar con `SESSION_SECURE_COOKIE=true` en `.env`.

---

## 14. Despliegue en Producción

**Destino:** servidor institucional Linux con salida a internet (Nginx + PHP-FPM + PostgreSQL), con acceso por **SSH** (FTP opcional). El entorno real desplegado (§14.L) es **Debian 12 + Nginx + PHP-FPM 8.3 + PostgreSQL 13.23 (LATIN9)** y la actualización se hace con **`git pull` sobre el clon del repositorio** ya instalado en el servidor.

### 14.1 Requisitos del servidor

| Componente | Mínimo | Notas |
|---|---|---|
| PHP | **8.3+** (obligatorio: `composer.json` fija `^8.3`) | Extensiones: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `dom`, **`gd` con soporte WebP** (`imagewebp()` — habilita D9 WebP con fallback; sin él el portal funciona igual pero sin convertir imágenes). No se usa `bcmath` |
| PostgreSQL | 14+ recomendado (en producción opera 13.23, verificado el 2026-10-25) | BD y usuario dedicados (no `postgres`); TLS activado (`DB_SSLMODE=require`) |
| Node.js | 18+ | Solo si se compila en el servidor; si no, compilar localmente y subir los assets (14.3.C) |
| Composer | 2.x | Solo si se instala en el servidor; si no, subir `vendor/` ya instalado en `--no-dev` |
| HTTPS | obligatorio | Certificado institucional o Let's Encrypt (§14.L: pendiente en el servidor actual) |

### 14.2 Estructura del servidor (vhost)

Recomendado: **la raíz del vhost apunta a `public/`** — así `.env`, `storage/`, `vendor/` y `.git/` quedan fuera del alcance HTTP.

```nginx
server {
    server_name web.gdc.gob.ve;
    root /var/www/html/PortalPG2026/public;   # solo public/ es accesible por HTTP
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Condición 5 (SECURITY.md): sin ejecución de scripts en subidas/estáticos
    # y nosniff en los que no pasan por Laravel (los bloques con add_header
    # propio no heredan los del server{}).
    location ^~ /storage/ {
        location ~* \.(?:php|phtml|phar)$ { deny all; }
        try_files $uri =404;
        add_header X-Content-Type-Options nosniff always;
    }
    location ^~ /build/ {
        try_files $uri =404;
        add_header X-Content-Type-Options nosniff always;
    }
    location ^~ /images/ {
        try_files $uri =404;
        add_header X-Content-Type-Options nosniff always;
    }

    location ~ /\.(?!well-known) { deny all; }   # bloquea .env, .git, .htaccess

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;       # try_files $fastcgi_script_name =404
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Equivalente Apache: `DocumentRoot /var/www/html/PortalPG2026/public` con el `.htaccess` de Laravel habilitado (`AllowOverride All`); en Apache el `.htaccess` de `storage/app/public` ya bloquea la ejecución de scripts en subidas (en Nginx hace falta el bloque `^~ /storage/` de arriba).

> ⚠️ Si la raíz web fuera la del proyecto completo, el sitio quedaría expuesto a la lectura de `.env` (credenciales BD y APP_KEY). Nunca hacerlo.
>
> ⚠️ **`expires 1y` a nivel de `server{}`** (tal como está en producción hoy) hace que también el HTML se entregue con `Cache-Control: max-age=31536000`: tras cada despliegue los visitantes pueden ver una página cacheada hasta un año. Decisión registrada 2026-10-25: **no se toca** en esta ronda; si se retoma, acotar `expires` a `location` de estáticos (`css|js|jpg|png|webp|svg|woff2`).

### 14.3 Checklist de despliegue (primera vez)

#### A. Código
1. Preferible: `git clone <repo> /var/www/html/PortalPG2026` por **SSH**, con `www-data` como propietario del árbol (`chown -R www-data:www-data`) para que PHP-FPM y los comandos artisan corran sin fricción. **El `.env` de producción no está en git**: sobrevive a cualquier `git pull`/`reset --hard`.
2. Por FTP: subir todo excluyendo `node_modules/` (se reinstala con `npm ci`) y, si en el servidor hay Composer, también `vendor/`. El `.env` de desarrollo **no se sube** (se crea en el paso D).

#### B. Dependencias PHP
```bash
cd /var/www/html/PortalPG2026
composer install --no-dev --optimize-autoloader
```
- *Sin Composer en el servidor*: ejecutar `composer install --no-dev` en una copia limpia local (sin `.env` de dev) y subir `vendor/`.

#### C. Assets (obligatorio)
`public/build/` (Vite) y `public/vendor/` (TinyMCE) **no están en git**. Sin ellos el sitio falla con *"Vite manifest not found"* y el editor no carga.

- **Opción 1 (recomendada)** — compilar en el servidor: `npm ci && npm run build`
- **Opción 2** — compilar localmente y subir las carpetas `public/build/` y `public/vendor/` (generadas por `npm run build`)

#### D. `.env` de producción
Crear **in situ** desde la plantilla **`.env_example_produccion`** (versionada en la raíz; nunca copiar el `.env` de desarrollo) — trae ya configurados `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, rate limits **30/5/30** (`LOGIN/CONTACT/SEARCH_RATE_LIMIT`), `AUDIT_ENABLED=true` y placeholders comentados de `TRUSTED_PROXIES`/`TRUSTED_HOSTS`, BD y SMTP. Completar en el servidor los valores reales (`APP_URL`, BD, SMTP, `APP_KEY` vía `key:generate`). Claves críticas:

| Clave | Valor en producción |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | **`false`** (con `true`, los errores 500 filtran trazas y datos sensibles) |
| `APP_KEY` | generarla UNA sola vez con `php artisan key:generate` **antes del primer `db:seed`** y no volver a tocarla: si se regenera con filas ya creadas, todo lo cifrado con la clave anterior (`users.name`, `contacts.name/email/subject/message`) queda ilegible y cualquier lectura revienta con `DecryptException: The MAC is invalid` (p. ej. un re-`db:seed`). Recuperación: restaurar la clave original, o si la BD es descartable `php artisan migrate:fresh --seed --force`, o borrar las filas afectadas con SQL crudo (`psql`/`DB::table`, que no descifran) y re-sembrar — `UserSeeder` imprime exactamente esta guía al detectarlo (§14.E) |
| `APP_URL` | `https://web.gdc.gob.ve` (dominio real; **definirlo UNA sola vez** — una clave repetida en `.env` confunde al parser y suele ganar la primera) |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | BD dedicada de producción (**LATIN9**, ver §14.K) |
| `DB_CHARSET` | No cambiar: la conexión va en `utf8` aunque la BD sea LATIN9 (§14.K) |
| `MAIL_*` | SMTP real (el default de `.env.example` es un host de ejemplo que no existe) |
| `SESSION_SECURE_COOKIE` | `true` |

Verificar: `php artisan config:show app.debug` (debe ser `false`) — en Laravel 10 `config:show` exige la clave; un `config:show` sin argumentos falla con *Not enough arguments (missing: "config")*.

#### E. Instalación
Orden correcto (§14.D): el `APP_KEY` se genera **una sola vez, antes** del primer seed, y no se vuelve a tocar.
```bash
php artisan key:generate         # una sola vez, ANTES de migrar/sembrar (§14.D)
php artisan migrate --force      # migraciones
php artisan db:seed              # solo la primera vez (crea los usuarios seed de prueba)
# Después, en el navegador: https://<dominio>/install.php — formulario one-shot
# que da de alta el superadmin/admin reales, borra los usuarios seed y se
# auto-elimina (ver F.1). ⚠️ Hacerlo ANTES de publicar el dominio.
php artisan storage:link         # ⚠️ requiere SSH — FTP no puede crear el symlink
php artisan webp:generate        # backfill WebP de las imágenes existentes (idempotente)
php artisan optimize             # config:cache + route:cache + view:cache
```

> ⚠️ **Nunca re-ejecutar `key:generate` tras haber sembrado** (§14.D): si el `APP_KEY` cambia,
> las filas cifradas con la clave anterior (`users.name`, `contacts.*`) quedan ilegibles y un
> re-`db:seed` revienta con `The MAC is invalid` — `UserSeeder` lo detecta y detiene el seed
> imprimiendo las opciones de recuperación (clave original / `migrate:fresh --seed --force` si la
> BD es descartable / `DELETE` crudo de la fila afectada).

> **Si solo hay FTP (sin SSH)** para `storage:link`: pedir al administrador del servidor que cree el enlace
> `ln -s /var/www/html/PortalPG2026/storage/app/public /var/www/html/PortalPG2026/public/storage`,
> o configurar un alias en el vhost: `location /storage/ { alias /var/www/html/PortalPG2026/storage/app/public/; }`.
> Sin esto, las imágenes subidas (medios, destacadas, logos de entes) no se sirven.
> ⚠️ El enlace **apunta al `storage/` de la misma instalación**: en el servidor real apuntaba a una
> copia residual en `/home/webgdc/...` y los ficheros subidos no se servían (corregido el 2026-10-25, §14.L).

#### F. Pasos de seguridad post-despliegue (obligatorios)
1. **Cambiar o eliminar los usuarios seed**: lo hace **`public/install.php`** (§14.E) en el primer arranque — formulario web que da de alta el superadmin/admin reales, **reasigna al superadmin real todo el contenido sembrado** (noticias y galerías `posts`/`media`, más `gacetas`/`videos` si los hubiera) que estuviera firmado por los usuarios seed, borra `admin@gdc.gob.ve`, `admin2@gdc.gob.ve` y `editor@gdc.gob.ve` (password `password`) y se **auto-elimina** (con guarda: si ya hay superadmin y no quedan seed, solo se retira sin tocar nada). El contenido de `NoticiasPrecargadasSeeder`/`PostSeeder` cargado con `db:seed` **no se ve afectado** por el borrado de usuarios. Si el instalador ya se borró o hay que repetirlo a mano: borrar los usuarios seed desde `/admin/usuarios` o con tinker (§15).
2. Confirmar `APP_DEBUG=false` y `APP_ENV=production`.
3. **Cifrar PII heredado**: `php artisan pii:encrypt` (idempotente; `--dry-run` para previsualizar). Re-cifra `contacts.name/email/subject/message` y `users.name` que sigan en texto plano tras la migración `encrypt_pii_columns`.
4. **HTTPS obligatorio** y HSTS fijado en el servidor web (`Strict-Transport-Security: max-age=31536000; includeSubDomains`). La app también lo emite en producción. Si hay proxy/CDN, configurar `TRUSTED_PROXIES` (CSV de IPs o `*`) y `TRUSTED_HOSTS` (CSV de dominios) en `.env`.
5. Ejecutar `composer audit` y revisar cualquier advisory activo (desde la migración a Laravel 13 no hay bloque `audit.ignore` en `composer.json`: **0 advisories, 0 waivers** — cualquier ID ignorado que aparezca debe justificarse por escrito en SECURITY.md §2).
6. Permisos: `storage/` y `bootstrap/cache/` deben ser escribibles por el usuario de PHP-FPM/Apache, p. ej. `chown -R www-data:www-data storage bootstrap/cache`.
7. `.env` con permisos restrictivos (`chmod 600 .env`) y fuera de la raíz web.
8. Cargar contenido real: entes (`PartnerSeeder` está vacío), URLs de redes sociales desde `/admin/rrss` y enlaces de "Intranet GDC" (Funcionarios Activos / Jubilados / Intranets GDC) desde `/admin/configuracion` (vacío = ítem oculto); verificar `/admin/login`.

#### G. Actualizaciones posteriores (procedimiento ejecutado el 2026-10-25)

```bash
# 0. Ventana de mantenimiento + respaldo de lo no versionado
php artisan down
tar czf ~/backups/$(date +%F)-assets.tgz public/build public/vendor
tar czf ~/backups/$(date +%F)-storage.tgz storage/app/public storage/logs
cp .env ~/backups/$(date +%F)-env.before && chmod 600 ~/backups/$(date +%F)-env.before

# 1. Código (como www-data; sin cambios locales == fast-forward seguro)
git fetch origin && git reset --hard origin/main
rm -f public/install.php          # git lo restaura: la condición 1 obliga a borrarlo otra vez

# 2. Dependencias y assets
composer install --no-dev --optimize-autoloader
npm ci && npm run build           # o subir public/build + public/vendor

# 3. Caché, BD y contenido (NUNCA un `db:seed` completo: recrearía usuarios seed)
php artisan optimize:clear        # obligatorio si cambiaron config/* o routes/*
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force   # solo si cambió (idempotente, solo añade)
php artisan pii:encrypt --dry-run # 0 pendientes ⇒ no ejecutar
php artisan webp:generate         # idempotente
php artisan optimize              # config:cache + route:cache + view:cache

# 4. Verificación mínima y apertura
php artisan migrate:status        # todas "Ran"
php artisan up
curl -sk -o /dev/null -w "%{http_code}\n" https://<dominio>/
test -f public/install.php && echo "¡FALTA BORRAR install.php!"
tail -50 storage/logs/laravel-$(date +%Y-%m-%d).log
```

> **Nunca** en una actualización: `key:generate` (rompe el PII cifrado), `migrate:fresh`, `db:seed`
> completo (`UserSeeder`/`NoticiasPrecargadasSeeder` recrean usuarios seed) ni `git pull` con
> cambios locales sin revisar (`git status` debe estar limpio salvo `public/install.php` borrado).

#### G.1 Notas del salto a Laravel 13 (rama `Laravel13-Dev`, docs/PLAN-LARAVEL13.md)

El procedimiento de G sirve tal cual; al actualizar desde una instalación en Laravel 10/11/12:

1. **PHP**: el servidor debe tener **PHP ≥ 8.3** (sin cambio respecto a la instalación actual).
2. **Dependencias**: `composer install --no-dev` resuelve el lock a **`laravel/framework` 13.38.0** (`^13.10.1`). En la primera actualización que suba de 10.x/11.x/12.x, si el servidor no tiene Composer, hay que regenerar `vendor/` localmente con el lock nuevo y subirlo completo (incluye el salto Guzzle 7 → 8).
3. **Migración nueva**: `2026_10_26_100000_drop_personal_access_tokens_table` (Sanctum se eliminó; la tabla `personal_access_tokens` se dropea). La cubre el `php artisan migrate --force` del paso G.3.
4. **`php artisan cache:clear` además de `optimize:clear`** en el primer despliegue: `config/cache.php` añade la allowlist `serializable_classes` (Laravel 13 endurece la deserialización del store `file`); vaciar el caché evita leer entradas previas al cambio.
5. **Re-login obligatorio de todos los usuarios**: `config/session.php` fija `serialization = 'json'` (endurecimiento anti-deserialización, decisión D3 del plan) — las sesiones existentes quedan inválidas al desplegar y cada usuario deberá iniciar sesión de nuevo. No requiere acción más allá de comunicarlo.
6. **CSRF**: el middleware de la app es ahora `App\Http\Middleware\PreventRequestForgery` (antes `VerifyCsrfToken`; renombrado por el framework en L13). El comportamiento para formularios clásicos (`@csrf`) y para `curl` sin cabeceras `Sec-Fetch-Site` no cambia (se valida el token). No hay referencias en `.env`, Nginx ni cron que actualizar.
7. **Verificación post-despliegue**: `php artisan about` debe mostrar `Laravel Version 13.38.0`; además del smoke de G.4, revisar login + escritura en `/admin` (sesión json) y `tail` del log sin errores de deserialización.

#### H. Cron de programados (obligatorio)
`routes/console.php` programa **2 tareas diarias** (facade `Schedule`; desde Laravel 11 el
schedule vive ahí, no en `app/Console/Kernel.php`, que ya no existe) — `contacts:prune` a las
03:17 (retención GDPR, `CONTACT_RETENTION_DAYS=365`) y `audit:prune` a las 04:23
(`AUDIT_RETENTION_DAYS=400`) — y la cola es `sync` (no hace falta worker de jobs).
**Sí hay que registrar el cron**:

```cron
# /etc/cron.d/portal  (root:root, 644, con salto de línea final)
* * * * * www-data php /var/www/html/PortalPG2026/artisan schedule:run >> /dev/null 2>&1
```

Verificación: `cat /etc/cron.d/portal` + `php artisan schedule:run` a mano (debe imprimir
*No scheduled commands are ready to run* fuera de las horas programadas, sin errores).
Sin este cron las podas no se ejecutan y la auditoría/contactos crecen sin límite (condición 6 de SECURITY.md §1).

#### I. Límites de PHP (`php.ini` de FPM)
Editar `/etc/php/8.3/fpm/php.ini` y reiniciar: `systemctl restart php8.3-fpm` (condición 4 de SECURITY.md §1).

| Directriz | Valor desplegado | Motivo |
|---|---|---|
| `upload_max_filesize` / `post_max_size` | `100M` / `110M` | Vídeos de hasta 100 MB (gacetas 20 MB, medios 10 MB); mínimo funcional 21M/21M |
| `memory_limit` | `256M` | Listados/administración con muchas filas y subidas múltiples |
| `max_execution_time` | `60` | Subidas múltiples de archivos |
| `expose_php` | `Off` | No revelar la versión de PHP en cabeceras/`X-Powered-By` |

> Los valores se comprueban en el ini de **FPM** (`grep -E '^(upload_max_filesize|post_max_size|memory_limit|max_execution_time|expose_php)' /etc/php/8.3/fpm/php.ini`): `php -i` desde CLI usa otro fichero (`/etc/php/8.3/cli/php.ini`) y puede mostrar los defaults.

#### J. Qué NO funciona en el servidor de producción
- Suites E2E (`tests/e2e/*.sh`): requieren `psql` y un servidor de desarrollo corriendo (§16); no se ejecutan contra producción.
- PHPUnit: no instalado en producción (`composer install --no-dev`); en desarrollo corre con su propia BD **sqlite en memoria** (`phpunit.xml`, sin tocar PostgreSQL) y el gate de dialecto con `DB_CONNECTION=pgsql` — §16.

#### K. Codificación de la BD: LATIN9 (ISO-8859-15)
La BD de producción es **PostgreSQL con encoding LATIN9** (no UTF-8). El proyecto está adaptado (PG2) y la app permanece 100% UTF-8: la conversión LATIN9 ↔ UTF-8 la hace PostgreSQL en el cable (`client_encoding=UTF8`, configurado en `config/database.php` — **nunca** poner `DB_CHARSET=latin9`, causaría mojibake).

- **Creación de la BD**: `createdb -E LATIN9 -l C -T template0 <nombre>` (con `template0` porque LATIN9 ≠ encoding del template).
- **Verificación** (en el servidor): `SHOW server_encoding;` → `LATIN9` y `SHOW client_encoding;` → `UTF8`. `public/install.php` muestra un aviso si `server_encoding` no es `latin*`.
- **Transliteración automática**: los caracteres sin equivalente en LATIN9 (emoji, comillas tipográficas `“ ” ‘ ’`, puntos suspensivos `…`, rayas `— –`, `¤ § ¨ ´ ¸`…) provocarían el error **22021** de PostgreSQL. El proyecto los translitera antes de que crucen el cable, en dos capas:
  - **Middleware de entrada** `SanitizeLatin9Input` (global, tras `TrimStrings`): sanea query/body/JSON — cubre también parámetros que van directos a SQL (p. ej. `?q=` de `/buscar` con ILIKE).
  - **Modelos** (trait `StoresLatin9` en la base `App\Models\Model` y en `User`): sanea todo texto en `setAttribute`, antes de los mutadores (HTMLPurifier) y de los casts `encrypted`.
  - Helper único `App\Support\Latin9`: acentos español/`€`/`Œ`/`Š`/`ž` se conservan **tal cual**; lo aproximable pasa a ASCII (`…` → `...`, `“ ”` → `"`, `—` → `-`); emojis/símbolos astrales se **eliminan** (el cuerpo de las noticias precargadas pierde los emojis de los txt de Instagram).
- **Escrituras fuera de la app**: INSERT/UPDATE crudos (`DB::table`, `psql`, dumps) con caracteres no representables **fallan con 22021** — pasar siempre por los modelos o transliterar antes. Las suites E2E fuerzan `PGOPTIONS='-c client_encoding=UTF8'` en `lib.sh`.
- **Gate de verificación**: `createdb -E LATIN9 -l C -T template0 portalpg2026_latin9` y `DB_CONNECTION=pgsql DB_DATABASE=portalpg2026_latin9 ./vendor/bin/phpunit` — `Latin9EncodingTest` verifica round-trip y que el INSERT crudo con `…` falla (§16).

#### L. Registro del despliegue real (2026-10-25)

Actualización de una instalación **ya existente con datos reales** (2 usuarios, 5 noticias) desde `aaaeb32` hasta `origin/main` = `8e06f82`. Credenciales y `APP_KEY` viven **solo** en el servidor (`.env`, `chmod 600`); este documento no las recoge.

| Componente | Valor real |
|---|---|
| Sistema | Debian 12 (bookworm), host `webgdc1`, 4 GB RAM / 18 GB disco |
| Web | Nginx + PHP-FPM **8.3.35** (`gd`, `pdo_pgsql`, `intl`, `mbstring`, `zip`; sin `bcmath`, que no se usa) |
| Raíz / dominio | `/var/www/html/PortalPG2026/public` · `https://web.gdc.gob.ve` (DNS interno → servidor) |
| BD | PostgreSQL **13.23** (`server_encoding=LATIN9`, `client_encoding=UTF8`), servidor de BD externo, `DB_SSLMODE=require` |
| Herramientas | Composer 2.5.5, Node 26 / npm 11 (ambos presentes, se compila en el servidor) |
| Acceso | SSH como usuario normal en grupo `www-data`; escalada a root con `su` (no hay `sudo`) |

**Secuencia ejecutada** (todo como `www-data`, infraestructura como root):

1. Backups previos en `/home/webgdc/backups/despliegue-2026-10-25/`: `.env` (`env.before`, 600), `public/build` + `public/vendor` (`public-assets.tgz`), `storage/app/public` + `storage/logs` (`storage.tgz`) y SHA de rollback.
2. `php artisan down` → `git fetch && git reset --hard origin/main` → `rm -f public/install.php`.
   *Hallazgo:* el árbol tenía 378 ficheros "modificados" que eran **solo bits de modo** (100644→100755, arrastrados de un `tar`), más `install.php` borrado → `reset --hard` fue limpio y `.env`/`storage/` (ignorados por git) no se tocaron.
3. `composer install --no-dev --optimize-autoloader` (3 actualizaciones de seguridad Symfony, 38 paquetes dev retirados) y `npm ci && npm run build` (123 módulos + TinyMCE).
4. **Symlink reparado**: `public/storage` apuntaba a una copia residual en `/home/webgdc/PortalPG2026/storage/app/public` (los ficheros nuevos no se servían) → repuntado al `storage/` real de la instalación; 0 ficheros pendientes de migrar.
5. `.env`: eliminado `APP_URL` duplicado (quedó `https://web.gdc.gob.ve`), `SESSION_LIFETIME` 120→60, `TRUSTED_HOSTS=web.gdc.gob.ve` (`TRUSTED_PROXIES` vacío: no hay proxy delante), añadidas `BCRYPT_ROUNDS`, `LOGIN_LOCKOUT_ATTEMPTS/DECAY`, `PASSWORD_CHANGE_RATE_LIMIT`, `CONTACT_RETENTION_DAYS`, `AUDIT_RETENTION_DAYS`, y `chmod 600`.
6. `optimize:clear` → `migrate --force` (**29/29**: índice + FK `nullOnDelete` de `activity_logs`) → `db:seed --class=PermissionSeeder --force` (**41 permisos**, incluye `audit.export`) → `pii:encrypt --dry-run` (**0** pendientes) → `webp:generate` (0 nuevos) → `optimize` + `view:cache`.
7. Infraestructura: `php.ini` de FPM a 100M/110M/256M/60 + `expose_php=Off` y reinicio de FPM; `/etc/cron.d/portal` con `schedule:run` como `www-data`; vhost Nginx con los bloques `^~ /storage|/build|/images` + denegación de dotfiles (§14.2) y `nginx -t` + reload.
8. `php artisan up` y verificación (tabla siguiente).

**Las 7 condiciones de SECURITY.md §1 — verificadas 7/7 (R4 cerrado):**

| # | Evidencia |
|---|---|
| 1 | `test ! -f public/install.php` → OK (⚠️ vuelve con cada `git reset --hard`: borrar tras cada despliegue) |
| 2 | `config:show security.trusted_hosts` → `web.gdc.gob.ve`; `trusted_proxies` → vacío |
| 3 | `config:show database.connections.pgsql.sslmode` → `require` |
| 4 | `grep '^(upload_max_filesize\|post_max_size…)' /etc/php/8.3/fpm/php.ini` → 100M/110M/256M/60, `expose_php=Off` |
| 5 | `curl .../storage/prueba.php` → **403**; `.../build/manifest.json` → 200 con `nosniff`; `.../.env` → **403** |
| 6 | `/etc/cron.d/portal` presente; `php artisan schedule:run` sin errores |
| 7 | `curl -I` → `Strict-Transport-Security: max-age=31536000` (app) + `max-age=63072000; preload` (Nginx) |

**Resultado funcional:** `/`, `/noticias`, `/gacetas`, `/contacto`, `/sobre-nosotros`, `/entes`, `/sitemap.xml`, `/admin/login` → **200**; assets del build y subidas `/storage/**` → 200; sin errores HTTP en `storage/logs`. Copia residual `/home/webgdc/PortalPG2026` (360 MB, clon desactualizado en `aaaeb32`) eliminada el 2026-10-26, conservando su `.env` en el backup.

**Rollback:** `git reset --hard aaaeb32345624cbb407fe7e19098c77894c761f4`, restaurar `.env` y `public-assets.tgz` del backup, reinstalar dependencias y `php artisan up`. Las migraciones aplicadas son **aditivas** (índice + cambio de FK), así que no requieren rollback de esquema; no se generó dump de la BD (ver PENDIENTES R6).

**Pendientes tras el despliegue:** certificado TLS (el auto-firmado actual `CN=<IP>` **venció el 2026-06-19**, el navegador rechaza la conexión), copia de seguridad automática de la BD (no hay `pg_dump` instalado) y, como mejora, acotar `expires` a estáticos (§14.2).

#### M. Registro del despliegue de Laravel 13 (2026-10-28)

Actualización del upgrade completo (U1): **Laravel 10.50.2 → 13.38.0** vía `8e06f82` →
`a55cfa7` (merge del PR #1 `Laravel13-Dev`) y corrección posterior `d4d3e91`. Procedimiento:
§14.G + §14.G.1. Sin cambios en `.env`, Nginx ni cron (config estilo L10 conservada, decisión D4
del plan — `CACHE_DRIVER`/`SESSION_DRIVER` siguen siendo las claves leídas).

| Paso | Resultado |
|---|---|
| Ventana + backups | `artisan down`; `/home/webgdc/backups/despliegue-2026-10-28-l13/` (`.env` 600, `public-assets.tgz`, `storage.tgz`, `info.txt` con SHA de rollback) |
| Respaldo de la tabla a dropear | `personal_access_tokens` comprobada con **0 filas** y clonada como `personal_access_tokens_backup_20261028` (`CREATE TABLE … (LIKE … INCLUDING ALL)`). *Lección: `INCLUDING ALL` hereda el DEFAULT que apunta a la secuencia original y el `DROP` falla con `2BP01` — corregido con `ALTER … ALTER COLUMN id DROP DEFAULT` antes de reintentar la migración* |
| Código | `git fetch && git reset --hard origin/main` + `rm -f public/install.php` |
| Composer | `composer self-update --2` → **2.5.5 → 2.10.3**; `composer install --no-dev` instala framework **13.38.0**, Carbon 3, Symfony 7.4, Guzzle 8 y retira `doctrine/dbal`/`sanctum` |
| Assets | `npm ci && npm run build` (29 ficheros en `public/build` + TinyMCE) |
| Caché | `optimize:clear` **+ `cache:clear`** (§14.G.1.4, allowlist `serializable_classes`) |
| BD | `migrate --force` → **`drop_personal_access_tokens`** (única migración nueva). `pii:encrypt --dry-run` 0, `webp:generate` sin cambios |
| Contenido | `db:seed --class=NoticiasPrecargadasSeeder` publica la **Noticia 6** (Mes Rosa) con su vídeo → 6 posts, 1 video |
| Apertura | `artisan up`; `about` → **Laravel 13.38.0 / PHP 8.3.36**; `schedule:list` → `contacts:prune` 03:17 y `audit:prune` 04:23 |

**Incidencia 1 (resuelta): usuario seed creado por el seeder.** `NoticiasPrecargadasSeeder`
crea `admin@gdc.gob.ve` (rol `editor`, **contraseña `password`**) si ese email no existe —
condición que no se cumple en una instalación real cuyos usuarios seed borró `install.php`.
Se reasignó su contenido (1 post, 1 video) al superadmin real y se **eliminó la cuenta**.
*Pendiente de corregir en el seeder (guarda "solo si no hay usuarios").*

**Incidencia 2 (resuelta, `d4d3e91`): 500 al renderizar la galería.** La allowlist
`cache.serializable_classes` no incluía `App\Models\PostMedia`; `HomeController` cachea
`Post::with('category', 'postMedia.video')` y el store `file` devolvía
`__PHP_Incomplete_Class` → `data_get(): incomplete object` en `/` y `/noticias/**` (el caché
`array` de los tests no serializa, por eso no se vio en local). Corregido añadiendo la clase a
la allowlist + **test de regresión** que hace roundtrip del payload real por `FileStore`
(`CacheTest::test_cache_serializable_classes_allowlist_roundtrips_cached_payloads`, que
reproduce el 500 sin el fix).

**Verificación post-upgrade:** `/` ×5 y `/noticias` ×3 → 200 (incluye el segundo request, con
caché ya poblado); `/noticias/mes-rosa-…`, `/gacetas`, `/contacto`, `/sobre-nosotros`,
`/entes`, `/sitemap.xml`, `/buscar?q=…`, `/admin/login` → 200; el vídeo se sirve con `206`
(Range) y la vista embebe `<video src="/storage/videos/…">`; `/storage/prueba.php` y `/.env`
→ **403**; CSP de nonce + `style-src-attr` y HSTS presentes; sin errores nuevos en el log
desde el fix. **Re-login obligatorio de todos los usuarios** (sesiones invalidadas por
`session.serialization = json`, §14.G.1.5).

**Rollback:** `git reset --hard 8e06f82` + `composer install --no-dev` + `npm ci && npm run build`
+ restaurar `public-assets.tgz` y `.env` del backup + `artisan up`. La migración es reversible
(`down()` recrea `personal_access_tokens` vacía); la tabla respaldo puede borrarse cuando
exista un dump real de la BD (PENDIENTES R6).

---

## 15. Comandos Útiles

```powershell
# Limpiar caché
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear

# Reconstruir assets
npm run build

# Calidad de código (Pint, preset laravel)
composer lint        # verifica (pint --test) — debe pasar en verde
composer lint:fix    # aplica la corrección de estilo

# Tests Unit/Feature (sqlite en memoria; no toca la BD de desarrollo)
./vendor/bin/phpunit

# Backfill WebP: genera sidecars .webp de las imágenes JPG/PNG existentes
# (idempotente; --quality=82 por defecto; requiere GD con imagewebp)
php artisan webp:generate
php artisan webp:generate --quality=75


# Usuarios por defecto (desarrollo): ya los crea UserSeeder (password: "password")
# En producción: usar public/install.php en el primer arranque (§14.E/§14.F.1) —
# da de alta superadmin/admin reales, borra los usuarios seed y se auto-elimina.
# Si necesitas uno adicional con tinker (en producción, contraseña fuerte):
php artisan tinker
\App\Models\User::create(['name'=>'Nuevo','email'=>'nuevo@gdc.gob.ve','password'=>bcrypt('Contraseña-Fuerte-2026!'),'role'=>'admin']);

# Backup de la BD (PostgreSQL): volcado con formato custom (restaurable con pg_restore)
pg_dump -Fc -h 127.0.0.1 -U portalpg -d portalpg2026 -f portalpg2026.dump
# Restauración (en una BD vacía):
# pg_restore -Fc -h 127.0.0.1 -U portalpg -d portalpg2026 portalpg2026.dump

# Refrescar BD
php artisan migrate:fresh --seed

# Ver rutas
php artisan route:list
```

---

## 16. Tests (suites E2E + PHPUnit)

Suites **bash + psql** contra un servidor de desarrollo (`php artisan serve`). Usan `curl` para HTTP y `psql` para las aserciones SQL directas sobre PostgreSQL; 
las credenciales de BD y la base URL se configuran por variables de entorno (`E2E_BASE`, por defecto `http://127.0.0.1:8000`). En **Windows/Git Bash** funcionan desde la ronda R1 (2026-10-25): `lib.sh` fija `LC_ALL=C.UTF-8` (requisito de `grep -P`), limpia los `\r` que añade `psql` de Windows, traduce las rutas MSYS en `curl` y fuerza `timezone=America/Caracas` en `PGOPTIONS` para que el `now()` de las suites coincida con la zona de la app.

> **Gate de dialecto**: además de las suites E2E, PHPUnit se ejecuta en dos motores: SQLite en memoria (rápida, default de `phpunit.xml`) y PostgreSQL real (`DB_CONNECTION=pgsql DB_DATABASE=portalpg2026 ./vendor/bin/phpunit`). Ambas corridas deben estar en verde antes de merge.
>
> **Gate LATIN9** (BD de producción, §14.K): crear la BD `createdb -E LATIN9 -l C -T template0 portalpg2026_latin9` (owner el usuario de la app) y correr `DB_CONNECTION=pgsql DB_DATABASE=portalpg2026_latin9 ./vendor/bin/phpunit` — la suite completa debe estar en verde (`Latin9EncodingTest` verifica round-trip y que un INSERT crudo con `…` falla con 22021). Las suites E2E también pueden correr contra ella: apuntar `DB_DATABASE` del `.env` a la BD LATIN9 y usar `DB_NAME=portalpg2026_latin9 bash tests/e2e/test-noticias.sh` (el `php artisan serve` hereda el `.env`; las variables de entorno por CLI no llegan al hijo `php -S`).

| Suite | Cubre |
|---|---|
| `test-categorias.sh` | Filtro por categoría en /noticias y /categoria/{slug}; orden y links del footer |
| `test-gacetas.sh` | CRUD de Gacetas Oficiales + vista pública + permisos |
| `test-social.sh` | Módulo Redes Sociales (permisos, `rrss.manage` en grupo "Gestión Portal") + formulario multi-línea (7 textareas, validación URL por línea, «línea N»), iconos **solo-footer** con **1 icono por URL** (multi-cuenta: 2 FB = 2 iconos + tooltip `Red — URL`), seeder no pisa editados, snapshot/restauración multi-línea segura vía tabla auxiliar (12 secciones) |
| `test-settings.sh` | Identidad / Enlaces (`/admin/configuracion`): nombre del sitio (títulos), logo (upload/quitar), enlaces navbar (URL absoluta, ruta válida, `..`, ruta inexistente), ocultar ítem vacío, `site_name` obligatorio, permisos (40/67, `settings.manage` solo superadmin, 3 grupos + 15 módulos incl. Auditoría, doble cabecera en `/admin/roles`), snapshot/restauración |
| `test-users.sh` | `is_active`/`last_login_at`: bloqueo de login a inactivos, registro de último acceso, guard de auto-desactivación |
| `test-wysiwyg.sh` | Editor TinyMCE (assets offline), HTML crudo en detalle, compat texto plano, **D15: sin plugin image ni handler de subida + imagen inline eliminada del texto con aviso visible**, endpoint JSON de la biblioteca Medios, "Copiar URL" en medios, permisos del editor |
| `test-contact-info.sh` | Info de contacto en settings + defaults + permisos |
| `test-contact-messages.sh` | Bandeja de mensajes (`contacts`) |
| `test-entes.sh` | CRUD de Entes afiliados + slugs + nav activo + buscador con filtro client-side |
| `test-media.sh` | Medios + imagen destacada de noticias + biblioteca |
| `test-noticias.sh` | Flujo de aprobación (D12): badges Pendiente/Programada/Publicado, botón Aprobar individual + auditoría `news.approved`, aprobación masiva (solo borradores, omitidas las no-borrador), editor sin UI de aprobación (sin checkboxes/masivo/Aprobar, 403 en rutas `news.approve`, selects deshabilitados, intento de publicar → draft + flash warning), programación (404 público hasta la fecha, badge Programada, vaciar fecha = publicar ya), editor no adelanta programadas; **D15 multimedia**: crear noticia con portada + 2 imágenes de galería + video YouTube + archivo mp4 → `post_media` con rutas `uploads/YYYY/MM/`, videos en el módulo sin `is_featured`, carrusel de 5 ítems con la portada primero, reorden ↑/↓, `remove_media[]` y tope de 3 videos; limpieza no destructiva |
| `test-videos.sh` | Módulo Videos: permisos `videos.*` (40/67, solo superadmin), sidebar (sección + dropdown por rol, 403 admin/editor), cargar YouTube (válida/inválida) + subir mp4, destacar/cambiar/"Ninguno" (siempre ≤1 `is_featured`), home refleja la portada, borrado + limpieza |
| `test-partners.sh` | Upload/replace/quitar logo de entes (incl. sidecars `.webp`) |
| `test-share.sh` | Botones de compartir + lazy loading |
| `test-webp.sh` | WebP con fallback (D9): `webp:generate` (backfill + idempotencia), sidecar al subir, GET `.webp` 200 `image/webp`, GIF y PNG corrupto sin conversión (contador `ilegibles`, exit 0), `<picture>` con fallback en `/noticias`, destroy borra original + sidecar, `picture{display:contents}` en el build |
| `test-errors.sh` | Páginas de error HTTP (incl. 503 real vía `artisan down`) |
| `test-dashboard.sh` | Dashboard (D1): redirect sin sesión, 9 tarjetas con conteos vs SQL dinámicos, tracking `daily_views` (+1 en `views` lifetime y en la tarjeta tras leer un artículo), gráfico de exactamente 30 barras + total del período, filtrado por rol (editor 3 tarjetas / admin 7 / superadmin 9), exactamente 1 form de logout, smoke de los 3 roles |

Ejecución:

```bash
bash tests/e2e/run-all.sh              # todo: migrate:fresh --seed + las 17 suites + resumen
bash tests/e2e/test-noticias.sh        # suite individual
bash tests/e2e/run-all.sh webp media   # suites seleccionadas
```

Garantías de las suites:
- **No destructivas**: solo borran las filas/archivos que ellas crean (incluidos los sidecars `.webp`); donde mutan settings (`social_*`, `contact_*`, `site_*`/`nav_*`) hacen **snapshot y restauración** de los valores previos (y del archivo de logo); los asserts no asumen BD vacía (usan conteo base).
- `test-errors.sh` garantiza `artisan up` con `try/finally`.
- `render_errors.php` renderiza las 12 vistas de error por CLI (se ejecuta desde `tests/e2e/`).

### PHPUnit (`tests/Unit`, `tests/Feature`)

- Ejecución: `./vendor/bin/phpunit` (o `--testsuite Unit,Feature`) — **303 en verde / 1353 assertions** (4 skipped: requieren PostgreSQL o el symlink `public/storage`; 6 avisos preexistentes de `iconv` en LATIN9, intencionales). *Cifra verificada el 2026-10-28 tras el fix de la allowlist de caché (§14.M); incluye el test de regresión del roundtrip por `FileStore`.*
- **Cobertura (desde R3, re-validada 2026-10-26 en Laravel 13):** con Xdebug 3.5.3 instalado, `./vendor/bin/phpunit --coverage-text --coverage-html coverage-html` → **97.58% de líneas total** (app 98.05% · database 95.36%; objetivo ≥90% superado); authn/authz/validación/cifrado ≈100% salvo el fallback de iconv de `App\Support\Latin9`. El HTML se genera en `/coverage-html` (ignorado por git).
- **BD aislada**: `phpunit.xml` define `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` (B2 cerrado) — `RefreshDatabase` migra en memoria por test, **nunca toca la BD de desarrollo**. El `APP_KEY` de `phpunit.xml` es una clave **exclusiva de tests**; no usar en producción.
- Cobertura de flujos críticos:
  - `AuthTest` — login correcto/incorrecto/inactivo, logout, `last_login_at`, mensaje genérico.
  - `AuditLogTest` — login/logout/fallido, cambio de contraseña, CRUD de usuarios, roles, noticias, mensajes; acceso a `/admin/auditoria` solo superadmin.
  - `CacheTest` — consultas de home/cacheadas y invalidación por observers al crear posts/entes.
  - `ContactStoreTest` — POST `/contacto` válido/inválido, `read_at` no manipulable.
  - `EditorCannotPublishTest` — editor sin `news.approve` no puede publicar (queda en draft + `warning`); editor **con** el permiso sí publica.
  - `GacetaHashTest` — hash SHA-256 al subir/actualizar gacetas.
  - `HtmlSanitizationTest` — sanitización HTML (XSS); desde D15 las imágenes inline `data:` y cualquier `<img>` en el texto se **eliminan** (portada/galería es la única vía) y se emite el aviso `warning`.
  - `NewsApprovalTest` — flujo D12: badges en el listado, aprobación individual (audit `news.approved`), masiva (solo borradores, omitidas, vacía = warning, 403 a editor), programación (404 público hasta `published_at`, badge Programada, `travelTo` la hace visible), editor no adelanta ni publica programadas, aprobar con campo vacío = publicar ya, `published_at` siempre presente al publicar.
  - `NewsOrderingTest` — D13: los listados públicos ordenan por `published_at` (la noticia de hace 60 días va después de la recién publicada, en `/noticias` y en el home) y el listado admin muestra la fecha original de publicación de las migradas.
  - `OrgMemberPhotoTest` — validación de rutas/extensiones en `org_members.photo`.
  - `PasswordChangeTest` — cambio forzado de contraseña en primer login.
  - `PermissionTest` — 403/200 por rol, `hasPermission()` con reparto canónico 41/22/5, `PermissionSeeder`.
  - `PiiEncryptionTest` — cifrado de PII heredado via `php artisan pii:encrypt`.
  - `NoticiasPrecargadasSeederTest` — D16 (+purga de placeholders): lote de 5 noticias precargadas desde `public/Noticias Precargadas/` — fechas correctas por sello de nombre (con fallback a la línea `Fecha:` del txt, excluida del cuerpo), categorías asignadas, portada + galerías 3/0/2/3/2 en `uploads/2026/10/`, 15 filas `media`, texto íntegro (`@nicolasmaduro`, `#13oct`) en `<p>`, posiciones consecutivas, re-ejecución sin duplicar posts/archivos/filas y **purga de placeholders `noticia-destacada-*`** (incluidas filas soft-deleted, borrado físico).
  - `PostMediaTest` — D15: galería de noticias — portada+imágenes en `uploads/YYYY/MM/` y carrusel público (portada primero), orden por posición y botón ↑/↓ (`move`), límites 10 imágenes y 3 videos con mensaje en español, URL YouTube nueva crea registro en el módulo `videos` sin `is_featured`, archivo mp4 en `videos/`, `remove_media` exige filas propias y conserva el archivo en disco, video existente enlazado y solo portada = imagen estática sin carrusel (wrapper 560px a proporción natural desde D17).
  - `PostSeederTest` — D13 (plantilla manual desde que `NoticiasPrecargadasSeeder` es el seeder por defecto): `PostSeeder` crea las 4 noticias de ejemplo con sus estados (publicada+destacada, migrada con fecha pasada, borrador, programada) y es idempotente al re-ejecutarse.
  - `RateLimitTest` — 429 y lockout en `/admin/login`.
  - `SecurityConfigTest` — rate limits leídos desde `config/security.php` (respetan `config:cache`).
  - `SearchXssTest` — query de `/buscar` escapada en la respuesta (INJ-03).
  - `SecurityHeadersTest` — presencia de CSP, HSTS, X-Frame-Options, etc.; CSP usa nonce y no incluye `'unsafe-inline'` en `script-src`.
  - `SeoTest` — meta tags dinámicos en home y detalle de noticia; título y canonical.
  - `SitemapTest` — `/sitemap.xml` responde XML 200 con URLs de home y noticias publicadas.
  - `SocialLinksTest` — D14: redes multi-cuenta (una URL por línea) — el seeder crea 7 claves/idempotente y no pisa editados, rellena vacíos desde su plantilla, el PUT acepta varias URLs por red (CRLF→LF) y el footer dibuja un icono por cada línea con tooltip `Red — URL`, cualquier línea inválida rechaza con «línea N» y deja la BD intacta, líneas en blanco no generan iconos.
  - `UploadExtensionTest` — la extensión guardada se adivina por contenido, no por nombre del cliente; `mimes` rechaza archivos de texto renombrados.
  - `UserRoleRestrictionTest` — solo superadmin asigna `superadmin`; último superadmin no se puede degradar.
  - `CorsConfigurationTest` — CORS limitado a rutas `api/*` inexistentes; páginas públicas no envían `Access-Control-Allow-Origin`.
  - `StorageSymlinkTest` — el symlink `public/storage` apunta a `storage/app/public` (skipped si no existe).
  - `AdminPartnerCrudTest` (R3) — CRUD de entes: slug único, validación de URL/logo, reemplazo y borrado de logo con sidecar, auditoría `partner.*`, authz (admin solo ve, editor 403).
  - `AdminContactInboxTest` (R3) — bandeja de contacto admin: edición de la info visible, validaciones de email/mapa, `read_at` una sola vez, borrado + auditoría, authz por rol.
  - `AdminSettingsControllerTest` (R3) — configuración: `site_name` obligatorio, las 5 ramas de `validateLink` (URL absoluta válida/inválida, ruta sin `/`, traversal, archivo inexistente, archivo existente), subida/quitar logo con sidecar, superadmin-only.
  - `InstallScriptGuardTest` (F6b) — el guard de `public/install.php` se cierra con solo existir un superadmin.
  - `Unit/WebpTest` — reglas de conversión de D9.
- El detalle funcional fino sigue en las suites E2E bash+psql (§16).
- Estilo: `composer lint` (`pint --test`) debe pasar; `composer lint:fix` aplica Pint (C2).
- Nota: el `UserFactory` usa `Hash::make('password')` (no un hash hardcodeado) para respetar `BCRYPT_ROUNDS` de tests.
