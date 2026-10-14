# Portal GDC — Documentación del Sistema

## 1. Descripción General

Portal institucional del **Gobierno de Distrito Capital (GDC)**.
Sitio web informativo con panel administrativo para gestión de contenidos.
Inspirado en: https://mippci.gob.ve

---

## 2. Stack Tecnológico

| Componente | Versión |
|---|---|
| PHP | 8.2+ (recomendado; `composer.json` admite `^8.1`) |
| Laravel | 10.x |
| Bootstrap | 5.3+ |
| Base de datos | MySQL 8 / MariaDB 10+ |
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

Claves: `social_*` (7), `contact_*` (5) y las generales `site_name`, `site_logo`, `nav_funcionarios_url`, `nav_jubilados_url` (panel `/admin/configuracion`). Los valores por defecto de las generales viven en `Setting::defaults()`: el sitio funciona **sin filas** en la tabla.

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

- **Registro**: `Public\NewsController@show` ejecuta `INSERT ... ON DUPLICATE KEY UPDATE count = count + 1` (una sentencia atómica) además del contador lifetime `posts.views`. La fecha **siempre se enlaza desde PHP** (`now()->toDateString()`), nunca `CURDATE()` de MySQL (relojes distintos).
- ⚠️ **MySQL específico**: `ON DUPLICATE KEY UPDATE` es SQL de MySQL/MariaDB; no se planea migrar a otro SGBD (Postgres usaría `ON CONFLICT ... DO UPDATE`).
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
| `SettingsController` | `index, update` | ✅ Identidad / Enlaces: `site_name` (required ≤100) + enlaces del navbar (`nav_funcionarios_url`, `nav_jubilados_url`: URL absoluta válida **o** ruta `/...` sin `..` con `file_exists` en `public/`; vacío = ocultar ítem) + logo (upload `image` ≤2 MB a `settings/logo.{ext}` en disco `public`, checkbox "quitar logo"); redirige con flash |
| `GacetaController` | `index, store, edit, update, destroy` | ✅ Gacetas Oficiales: subida a `gacetas/` en disco `public` (PDF/imagen ≤20 MB), `numero` (solo dígitos) + `fecha`; `update` puede reemplazar el archivo (borra el anterior); `destroy` borra archivo físico |
| `VideoController` | `index, store, destacar, destroy` | ✅ Videos (D7): pestaña 1 carga YouTube (URL validada por regex → `embed_url`) **o** archivo (mp4/webm/ogg ≤100 MB a `videos/` en disco `public`); pestaña 2 marca **un solo** `is_featured` (radio + "Ninguno" limpia todos); `destroy` borra archivo físico |

---

## 7. Vistas

### 7.1 Layouts

| Archivo | Descripción |
|---|---|
| `layouts/public.blade.php` | Layout público: header con logo y título desde `settings` + **navbar configurable** (ítems Funcionarios Activos/Jubilados ocultos si su enlace está vacío) + footer |
| `layouts/admin.blade.php` | Layout admin: sidebar con **3 secciones** — "Gestión Noticias" (collapse "Multimedia" con "Medios"/"Videos" + "Gacetas"), "Gestión Portal" (Categorías, Entes Afiliados, Sobre Nosotros, Contacto, Mensajes, Estructura Org., Redes Sociales) y "Configuración Portal" (Identidad / Enlaces, Usuarios, Roles y Permisos); cabecera de sección visible si el rol tiene **algún** permiso de sus ítems vía `User::hasAnyPermission()` (lista de slugs en `@php`), cada ítem solo con su permiso vía `hasPermission()`; collapse Multimedia abierto en `admin.media.*`/`admin.videos.*` (el `ul#mmMenu` lleva `flex-nowrap`: sin él, `.nav { flex-wrap: wrap }` + `flex-direction: column` hacía que "Medios"/"Videos" se envolvieran a una columna lateral durante la animación de `height` de Bootstrap Collapse) + topbar + main; título y logo desde `settings` |

### 7.2 Públicas (`resources/views/public/`)

| Archivo | Secciones | Estado |
|---|---|---|
| `home.blade.php` | Hero slider de destacados (imagen de fondo con velo + fallback azul si no hay imagen), categorías, **sección "VIDEOS INSTITUCIONALES"** (si hay `is_featured`: iframe YouTube embed **o** `<video controls>` para archivo subido), grid de noticias, "Lo más leído" | ✅ |
| `news.blade.php` | Listado paginado de noticias + filtro por categoría (sidebar desktop con conteos + buscador; pills en móvil) | ✅ |
| `news-show.blade.php` | Detalle de noticia + botones de compartir (FB, X, WhatsApp, Telegram, copiar enlace con vanilla JS y fallback para HTTP plano) + relacionados; contenido vía `rendered_content` (HTML del editor, con compat nl2br para texto plano legado) | ✅ |
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
| Identidad / Enlaces | `settings/index.blade.php` | ✅ Nombre del sitio + logo (preview/quitar) + 2 enlaces del navbar; vacío = ocultar ítem |
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

- **Tabla**: `permissions` (38 permisos con `slug` tipo `news.create`) + `role_permission` (rol ↔ permiso, con índice único). Reparto: superadmin 38 / admin 21 / editor 5 (`news.*`, `media.view`, `media.upload`). Slugs de solo-superadmin: `rrss.manage`, `settings.manage` y los 4 de `videos.*`.
- **Grupos** (`permissions.group`, 1er nivel): los 38 permisos están agrupados en los **mismos 3 nombres que las secciones del sidebar** — `Gestión Noticias` (15: `news.*`, `media.*`, `videos.*`, `gacetas.*`), `Gestión Portal` (17: `categories.*`, `partners.*`, `about.*`, `contact.*`, `org.*`, `rrss.manage`) y `Configuración Portal` (6: `settings.manage`, `users.*`, `roles.manage`).
- **Módulos** (`permissions.module`, 2º nivel): cada permiso indica además el **módulo/función = ítem del sidebar** (14 en total): `Noticias`, `Medios`, `Videos`, `Gacetas` · `Categorías`, `Entes Afiliados`, `Sobre Nosotros`, `Contacto` (solo `contact.edit`), `Mensajes` (`contact.view/delete`), `Estructura Org.`, `Redes Sociales` · `Identidad / Enlaces`, `Usuarios`, `Roles y Permisos`. La vista `/admin/roles` renderiza categoría → módulo → permiso, ordenadas con `FIELD()` (categoría, módulo y acción `view/create/upload/edit/manage/delete`); los módulos no se repiten entre categorías.
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
| `PostSeeder` | Array declarativo de 4 noticias de ejemplo: plantilla publicada + destacada (fecha fija 08/10/2026), **migrada de redes sociales con fecha histórica pasada (15/07/2026)**, borrador de prueba (pendiente de aprobación) y programada de prueba (01/01/2027) — docblock con la receta de duplicación (cambiar `title`/`slug` único, `category` por slug, `status`+`published_at` para publicar/programar/migrar), `content` en nowdoc para pegar HTML tal cual, categoría por slug de `CategorySeeder` con fallback a la 1ª, autor por email con fallback; **purga** con `withTrashed()` + `forceDelete()` de las placeholders `noticia-destacada-*` (borrado físico: libera el slug y evita que consultas sin filtro de `deleted_at` tomen filas borradas) e idempotente vía `updateOrCreate(['slug' => ...])`; corre tras `CategorySeeder`; si aún no hay usuarios, crea `admin@gdc.gob.ve` con `firstOrCreate` (`UserSeeder`, posterior, reanuda nombre/rol/password canónicos) |
| `UserSeeder` | 3 usuarios: superadmin, admin, editor (password `password`) — `firstOrCreate` + restaura nombre/rol canónicos |
| `PermissionSeeder` | 38 permisos en 2 niveles alineados al sidebar: `group` = 3 categorías (`Gestión Noticias`/`Gestión Portal`/`Configuración Portal`) y `module` = 14 módulos/ítems (incluye `settings.manage` con nombre "Editar identidad y enlaces" y los 4 `videos.*`, solo superadmin) + asignaciones a los 3 roles — idempotente y **migrador**: `updateOrCreate` corrige `group`/`module`/`name` de filas existentes |
| `PartnerSeeder` | **Borrador** — array vacío, pendiente de contenido (name, description, url, order; slug auto y logo vía panel) |
| `ContactSettingSeeder` | Los 5 settings de contacto (`contact_address/phone/email/hours/map_url`) con los valores por defecto — `firstOrCreate`: **no pisa** lo editado desde `/admin/contacto` |
| `SocialSettingSeeder` | Los 7 settings de redes (`social_facebook/instagram/youtube/x/threads/telegram/whatsapp`) — `REDES` es el **espacio para pegar las URLs reales** (una por línea; varias líneas = varias cuentas de la misma red, D14); crea las claves faltantes y **rellena solo los valores vacíos**: nunca pisa lo editado desde `/admin/rrss` |
| `NoticiasPrecargadasSeeder` | Lote de noticias reales desde `public/Noticias Precargadas/` (D16): por carpeta, imágenes `001…NNN` + un `.txt` con el cuerpo y una línea final `Fecha:` (metadato); 001 → `featured_image`, 002+ → galería `post_media`; fecha del sello del nombre de la 001 (fallback: línea Fecha); copia a `uploads/YYYY/MM/` + fila `media` + sidecar WebP; texto íntegro (solo se excluye la línea Fecha); idempotente (omite posts ya sembrados con portada); incluido en `DatabaseSeeder` |

**Idempotentes**: los 7 seeders se pueden re-ejecutar sin duplicar filas ni fallar (`firstOrCreate` / `updateOrCreate` / `upsert` contra el índice único `role+permission_id`).

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

### ✅ Completado
- [x] Proyecto Laravel 10 + Bootstrap 5.3 (Vite + Sass), sin Livewire/Vue/React/jQuery
- [x] Layouts público y admin (sidebar con checks de permisos)
- [x] Migraciones de **todas** las tablas (incluye `org_members`, `permissions`, `role_permission`)
- [x] Modelos Eloquent (11) con relaciones y slugs
- [x] Seeders: categorías, posts, usuarios (3 roles), permisos (34) — todos idempotentes; `PartnerSeeder` creado como borrador (contenido pendiente)
- [x] Autenticación admin (login independiente + logout)
- [x] Roles y permisos granulares: middleware `permission:*`, `User::hasPermission()`, ocultación en UI, módulo `/admin/roles`
- [x] CRUD admin: noticias, categorías, usuarios, entes afiliados, medios, estructura organizativa, "Sobre Nosotros"
- [x] Home pública: hero slider de destacados, categorías, grid de noticias, "Lo más leído"
- [x] Páginas públicas: listado/detalle de noticias, por categoría, entes (sobre `partners`), sobre nosotros, contacto, búsqueda, páginas estáticas
- [x] Contador de visitas (`views`) en detalle de noticia
- [x] Footer con entes afiliados desde BD
- [x] Gestor de medios (subida a `public/uploads` vía disco `public`)
- [x] Imagen destacada de noticias integrada con la biblioteca: upload propio (≤10 MB) **o** selección desde Medios, auto-registro en `media`, quitar imagen; 4 vistas públicas renderizan con `asset('storage/...')`
- [x] `storage:link` configurado
- [x] Paleta y tipografía institucional (`#273376`, Georama / Instrument Sans) — fuentes cargadas **localmente** vía `@fontsource/georama` (700) e `@fontsource/instrument-sans` (400-700) en `app.js`; woff2 emitidos por Vite, funciona **offline**
- [x] Módulo de contacto: edición de la información visible (dirección, teléfono, email, horario, mapa) desde `/admin/contacto` vía `settings`, con parcial pública `_contact-info` en home + /contacto + sobre-nosotros
- [x] Persistencia de mensajes del formulario: tabla `contacts`, guardado en `Public\ContactController@store` y bandeja en `/admin/contacto/mensajes` (paginada, badge Nuevo/Leído, detalle que marca `read_at`, borrado)
- [x] Vistas de error en español (400/401/403/404/405/408/410/419/422/429/500/503) con botones Volver/Inicio (y Recargar en 419, Reintentar en 503); 5xx standalone sin dependencias
- [x] Redes sociales: módulo `/admin/rrss` (sidebar "Redes Sociales", permiso `rrss.manage`, solo superadmin) con 7 URLs en `settings`; parcial `_social-links` con íconos **solo en el footer** — vacío = oculto; URLs reales: **se cargan en producción** desde el panel (PENDIENTES E2)
- [x] Botones de compartir en el detalle de noticia (Facebook, X, WhatsApp, Telegram + "Copiar enlace" con vanilla JS) — enlaces `target="_blank" rel="noopener noreferrer"`, SPECS §4.6
- [x] Lazy loading (`loading="lazy" decoding="async"`) en las imágenes bajo el fold: grids de noticias (inicio/listado/categoría), entes, estructura organizativa y logos del footer; hero e imagen del artículo quedan eager (LCP) — SPECS §9.2
- [x] **Gacetas Oficiales**: CRUD admin `/admin/gacetas` (grid + formulario archivo + N° + fecha; edición con preview y reemplazo de archivo; permisos `gacetas.*` para superadmin y admin) + página pública `/gacetas` con grid de miniaturas (icono PDF para PDFs), título "Gaceta Oficial N° {numero}", orden `fecha DESC`, click abre el archivo en pestaña nueva; item en el navbar antes de "Intranet"
- [x] **Filtro por categoría en `/noticias`** (SPECS §4.5): sidebar de 2 columnas en desktop (categorías activas con conteo de publicadas + buscador) y pills scrollables en móvil; también en `/categoria/{slug}` con estado activo resaltado; parciales `_category-pills` y `_category-sidebar` (SPECS §4.5 "sidebar o tabs")
- [x] **Footer**: enlaces en el orden del navbar — Inicio, Noticias, Entes, Sobre Nosotros, Gacetas Oficiales, Contacto (Contacto al final, sin bloque Intranet)
- [x] **Suite E2E en el proyecto** (`tests/e2e/`): 17 suites PowerShell + `render_errors.php`, rutas portables (`$PSScriptRoot`, temp del sistema); limpiezas quirúrgicas con snapshot/restauración de datos preexistentes (nunca pisan datos reales)
- [x] **Medios: subida múltiple** (SPECS §5.4): input `files[]` con selección múltiple + lista de seleccionados, validación 10 MB por archivo, flash con conteo
- [x] **Usuarios: `is_active` + `last_login_at`** (SPECS §7.1): migración + checkbox "Usuario activo" (default activo), badge Estado y columna Última conexión en el listado; login bloquea inactivos con mensaje genérico y registra `last_login_at`; guard de auto-desactivación
- [x] **Font Awesome offline** (SPECS §9.2): FA 7.3.1 vía npm (`@fortawesome/fontawesome-free`) importado en `app.js` — webfonts emitidos por Vite como assets locales; sin CDN, portal funciona **offline**; elimina el warning de consola `Glyph bbox was incorrect` del CDN 6.4.0
- [x] **Editor WYSIWYG** (SPECS §9.4): TinyMCE 8 auto-hospedado (`tinymce` + `tinymce-i18n` por npm → `public/vendor/tinymce` vía `scripts/copy-tinymce.js` en `npm run build`), español, vanilla JS, **licencia GPL declarada** (`license_key: 'gpl'`, sin key ni nube); en `content` de noticias; subida de imágenes desde el editor a `/admin/medios` (handler envía `Accept: application/json` + `X-Requested-With` y recibe `{location}`); validación de contenido client-side visible (`triggerSave` + alert, sin `required` nativo sobre el textarea oculto) + server-side; render compatible (`rendered_content`: HTML crudo / nl2br para texto plano legado)
- [x] **"Copiar URL" en Medios** (SPECS §5.4): botón por card con patrón doble (Clipboard API + fallback `execCommand`) — para pegar imágenes/documentos en el contenido
- [x] **Permisos**: `media.view` + `media.upload` para el rol editor (puede subir imágenes a sus noticias y copiar URLs); reparto 40/22/5
- [x] **Sidebar con 3 secciones**: "Gestión Noticias" (Noticias + collapse "Multimedia" con Medios/Videos + Gacetas), "Gestión Portal" (Categorías, Entes Afiliados, Sobre Nosotros, Contacto, Mensajes, Estructura Org., Redes Sociales) y "Configuración Portal" (Identidad / Enlaces, Usuarios, Roles y Permisos); cabecera visible si el rol tiene **algún** permiso de sus ítems (`User::hasAnyPermission()`), cada ítem con su permiso; superadmin ve las 3, admin Noticias+Portal, editor solo Noticias
- [x] **Módulo Videos (D7)**: migración `videos` (`type` youtube/file, `is_featured`), 4 permisos `videos.*` (grupo "Gestión Noticias", solo superadmin), `/admin/videos` con 2 pestañas (cargar YouTube o archivo ≤100 MB / elegir **un** destacado con opción "Ninguno"), home renderiza el destacado (iframe embed o `<video>`); suite `test-videos.ps1`
- [x] **Identidad / Enlaces** (ex-Configuración general, SPECS §5.4): módulo `/admin/configuracion` (sidebar "Identidad / Enlaces" en la sección "Configuración Portal", permiso `settings.manage` — nombre del permiso en la matriz de roles "Editar identidad y enlaces", bajo el módulo "Identidad / Enlaces", solo superadmin) — nombre del sitio usado como título del navegador en el sitio público, panel, login y errores 4xx; logo (upload a `settings/`, previsualización, "quitar logo"); enlaces del navbar "Funcionarios Activos"/"Jubilados" (URL absoluta válida **o** ruta `/...` con archivo existente en `public/`; **vacío = ocultar ítem**, abren en pestaña nueva); valores en `settings` con defaults en `Setting::defaults()` (sin filas funciona); suite `test-settings.ps1`
- [x] **Buscador en `/entes`**: filtro instantáneo client-side (vanilla JS inline en la vista, sin jQuery ni recarga) sobre nombre + descripción, normalización de mayúsculas/accentos (NFD), mensaje "sin resultados" y `aria-label`; cobertura en `test-entes.ps1` (sección BUSCADOR)
- [x] **Imágenes WebP con fallback** (SPECS §9.2, D9): servicio `App\Services\Webp` (calidad 82, solo `jpg`/`jpeg`/`png` → sidecar `.webp` junto al original; GD nativo `imagewebp`, degrada a *sin conversión* si falta GD o el archivo es ilegible) + componente Blade `<x-img>` con `<picture>`/`<source type="image/webp">` y `<img src>` original como fallback; **conversión automática al subir** en los 5 puntos de carga (Medios, imagen destacada, Gacetas, logos de Entes, logo del sitio) y **borrado del sidecar** al borrar o reemplazar; backfill idempotente con `php artisan webp:generate`; CSS `picture { display: contents; }`; suite `test-webp.ps1` (comando, upload, render público, GIF/PNG-corrupto no convertibles, destroy, build CSS) — **15/15 suites E2E en verde**
- [x] **Dashboard real** (SPECS §5.4, D1): tabla `daily_views` (día + artículo + contador, `UNIQUE(day, post_id)`) con tracking en `Public\NewsController@show` (upsert atómico `ON DUPLICATE KEY UPDATE`, fecha enlazada desde PHP) + `DailyView` (12º modelo); `DashboardController@index` con agregados (noticias total/publicadas/borradores, visitas del mes, usuarios, mensajes sin leer, gacetas/medios/entes/videos/categorías, últimas 5 publicadas) y **9 tarjetas** filtradas por permiso (editor 3, admin 7, superadmin 9) con atributos `data-stat` estables + **gráfico de barras CSS de 30 días** (sin librerías, tooltips por día, estado vacío) + bloque "Últimas noticias publicadas"; suite `test-dashboard.ps1` — **16/16 suites E2E en verde**
- [x] **Idioma español + timezone Caracas** (B1): `config/app.php` con `locale='es'` (mensajes de validación en español — antes en inglés) y `timezone='America/Caracas'` (fechas y bucketing de `daily_views` en hora local, UTC-4); ajuste de `test-dashboard.ps1` a TZ Caracas (`TimeZoneInfo`)
- [x] **Pint configurado** (C2): `pint.json` (preset `laravel`) + scripts `composer lint` (`pint --test`) y `composer lint:fix`; aplicación inicial sobre 46 archivos (estilo) — `composer lint` en verde
- [x] **Tests PHPUnit reales** (C3, cierra B2): `phpunit.xml` migrado a esquema 10.5 con `sqlite/:memory:` (los tests **nunca** tocan la BD de desarrollo); **124 tests / 610 assertions** en verde (1 skipped cuando no existe `public/storage`) — cobertura crítica en `AuthTest`, `AuditLogTest`, `CacheTest`, `ContactStoreTest`, `CorsConfigurationTest`, `EditorCannotPublishTest`, `GacetaHashTest`, `HtmlSanitizationTest`, `NewsApprovalTest`, `NewsOrderingTest`, `NoticiasPrecargadasSeederTest`, `OrgMemberPhotoTest`, `PasswordChangeTest`, `PermissionTest`, `PiiEncryptionTest`, `PostMediaTest`, `PostSeederTest`, `RateLimitTest`, `SearchXssTest`, `SecurityConfigTest`, `SecurityHeadersTest`, `SeoTest`, `SitemapTest`, `SocialLinksTest`, `StorageSymlinkTest`, `UploadExtensionTest`, `UserRoleRestrictionTest` y `Unit/WebpTest`; `UserFactory` con `Hash::make` (compat `BCRYPT_ROUNDS`); docs §16 actualizadas
- [x] **Log de auditoría (G6)**: migración `activity_logs` (actor, acción, recurso, IP, UA, metadata JSON) + modelo `ActivityLog` + helper `App\Support\Audit` + hooks en `AuthController` (login success/failed/logout/password changed), `UserController`, `RoleController`, `NewsController`, `GacetaController`, `ContactController`; vista `/admin/auditoria` paginada, acceso solo superadmin vía permiso `audit.view`; 11 tests en `AuditLogTest`
- [x] **CSP con nonce (G7)**: `AddSecurityHeaders` genera nonce por petición y CSP usa `script-src 'self' 'nonce-...'` (sin `'unsafe-inline'`); todos los scripts del proyecto incluyen el nonce; los ~14 handlers `onclick="return confirm(...)"` se migraron a `data-confirm` + listener global en `layouts/admin.blade.php` y `layouts/public.blade.php`; botones de error usan `data-action="reload"` / `data-action="back"`; `style-src` conserva `'unsafe-inline'` por TinyMCE; test en `SecurityHeadersTest`
- [x] **SEO y sitemap (D8)**: meta tags dinámicos en `resources/views/layouts/public.blade.php` (title, description, canonical, og:image/url/type/locale, twitter:card) alimentados por `$seo` desde todos los controladores públicos; título home/404 usa `site_name`; `SitemapController` genera `/sitemap.xml` cacheado con home, noticias, categorías, entes, gacetas y páginas; tests `SeoTest` y `SitemapTest`
- [x] **Cache de consultas públicas (D10)**: `config/portal.php` con TTLs, `App\Support\CacheKeys` con claves de home, nav de noticias, entes, gacetas y sitemap; `Cache::remember` en `HomeController`, `NewsController`, `EnteController`, `GacetaController`; observers para invalidar al crear/editar/borrar posts, categorías, videos, entes, gacetas y páginas; `Admin\VideoController@destacar` usa Eloquent para disparar observers; test `CacheTest`
- [x] **Aprobación de noticias (D12)**: permiso `news.approve` ("Aprobar noticias", por defecto superadmin + admin, 40 permisos / reparto 40·22·5); `Admin\NewsController@resolveStatus` basado en permiso — sin él el estado de publicación **no cambia** (ni se sube ni se baja) y se emite flash `warning` "pendiente de aprobación"; el dropdown Estado se muestra **deshabilitado** sin permiso; badges en el listado admin: 🟡 "Pendiente de aprobación" (borrador), 🔵 "Programada · fecha" (publicado con `published_at` futuro) y 🟢 "Publicado"; aprobación **individual** (botón "Aprobar" + `POST /admin/noticias/{post}/aprobar`) y **masiva** (checkboxes con atributo `form=`, botón "Publicar seleccionadas", `POST /admin/noticias/publicar` — solo borradores pasan a publicados con `published_at=now()`, el resto se omite con conteo en el mensaje); **programación opcional** de fecha/hora vía campo `datetime-local` (habilitado solo con `news.approve`, vacío = publicar inmediato, hora de Caracas) — una programada no es visible públicamente hasta su fecha; scope `Post::live()` (`published_at IS NULL OR <= now()`) aplicado a las 11 consultas públicas (home, listado, detalle, categoría, relacionados, nav, búsqueda, sitemap) y a las últimas noticias del dashboard, con migración backfill de `published_at` y fix de `PostSeeder`; auditoría `news.approved` en cada transición; tests `NewsApprovalTest` (13) + ampliaciones en `EditorCannotPublishTest`/`PermissionTest`; suite `test-noticias.ps1` — **15/17 suites E2E en verde** (`test-entes`/`test-share` con fallos preexistentes). Limitación: al llegar la hora de una programada, los listados cacheados tardan ≤ `PORTAL_CACHE_TTL` (1 h) en reflejarla (el detalle y la búsqueda no tienen caché)
- [x] **Noticias reales y orden por fecha de publicación (D13)**: `PostSeeder` reescrito como array declarativo duplicable con docblock de instrucciones — 4 bloques de ejemplo (plantilla publicada+destacada, **migrada de redes sociales con fecha histórica pasada**, borrador de prueba y programada de prueba), `content` en nowdoc, resolución de categoría por slug y autor por email con fallbacks, purga `withTrashed()` de placeholders `noticia-destacada-*` con `forceDelete()` e idempotencia vía `updateOrCreate(['slug' => ...])`; nuevo scope `Post::recent()` (`published_at` DESC, desempate por `id`) aplicado en las 5 consultas públicas que ordenaban por creación (home destacadas y últimas, listado `/noticias`, categoría, relacionados y búsqueda) para que las noticias migradas queden en su lugar histórico en vez de aparecer como recientes; columna "Fecha" del listado admin = `published_at ?? created_at`; SQL de referencia de `test-categorias.ps1`/`test-dashboard.ps1` alineados al scope `live()` (añaden `deleted_at IS NULL` y `published_at <= NOW()`, antes la programada contaba como publicada); tests `PostSeederTest` (3) y `NewsOrderingTest` (3) — **105 tests / 458 assertions** y 15/17 suites E2E (`test-entes`/`test-share` con fallos preexistentes)
- [x] **Redes sociales multi-cuenta (D14)**: `SocialSettingSeeder` expone `REDES` con las 7 claves como espacio para pegar las URLs reales (una por línea; varias líneas = varias cuentas del mismo tipo) y ahora **rellena solo los valores vacíos** (conserva lo editado en `/admin/rrss`); `RrssController@update` valida **cada línea** como URL http(s) (`filter_var` + `^https?://`, mensaje «La línea N no es una URL válida», `max:4000`) y guarda normalizado (CRLF→LF, sin líneas vacías); los 7 campos del admin pasan de `input url` a **textarea** multi-línea; el footer `_social-links` dibuja **un icono por línea** y el `title`/`aria-label` distingue cuentas (`Facebook — https://…` cuando una red tiene 2+); `test-social.ps1` con snapshot/restauración `HEX`/`UNHEX` (el cliente mysql escapa `\n` y rompía los valores multi-línea) + secciones nuevas de multi-cuenta y de línea inválida — 12/12 secciones; tests `SocialLinksTest` (6) — **111 tests / 486 assertions**
- [x] **Multimedia en noticias: galería + videos + wysiwyg sin imágenes (D15)**: nueva tabla `post_media` (`post_id`, `type image|video`, `path`, `video_id` FK→`videos` nullOnDelete, `position`) con modelo `PostMedia` y relación `Post::postMedia()`; **subidas con año/mes**: `Uploads::storeDated()` → `uploads/YYYY/MM/` aplicado a biblioteca de medios, portada y galería (carpetas semánticas `partners/`, `gacetas/`, `videos/`, `settings/` sin cambio); admin de noticias: etiqueta "Imagen de portada (hero)" + partial `_gallery-field` (input múltiple de imágenes, miniaturas con checkbox `remove_media[]` y **botones ↑/↓** vía `formaction`+`formmethod` sobre el form principal — la ruta `move` acepta POST y PUT porque en edición el `_method=PUT` del form hace spoofing —, checklist de videos existentes del módulo, textarea de URLs YouTube "una por línea" y archivos mp4/webm/ogg); límites **10 imágenes / 3 videos** por noticia validados restando las eliminaciones en la misma operación; los videos nuevos se **crean en el módulo Videos** (`is_featured=false`, título = noticia) para gestionarlos desde allí; `remove_media` exige filas propias del post y **no borra archivos** (paridad con `remove_image`); público `news-show`: con ≥2 ítems renderiza **carrusel Bootstrap** (portada primero + imágenes + `<iframe>` YouTube / `<video>` archivo, CSP `frame-src youtube` ya lo permitía) y con solo portada conserva el `<img class="img-fluid">` actual (LCP intacto); **wysiwyg sin imágenes**: plugin/botón `image` + `images_upload_handler` eliminados de `_editor.blade.php`, `img` y esquema `data:` fuera de `SanitizesHtml::allowedHtml()` (el regex de anclas `data:` se mantiene), texto fijo bajo el editor + flash `warning` "Se eliminaron N imagen(es) del texto…" concatenado con warnings previos (`admin/news/index` ya renderiza `warning`); bug corregido en `move()`: el update vía modelo ignoraba el cambio tras la normalización de posiciones (dirty-check de Eloquent) — se usa query builder; tests `PostMediaTest` (11) + `HtmlSanitizationTest` invertido (imágenes se eliminan) + `test-wysiwyg` actualizado (sin handler, imagen inline eliminada + aviso) + sección 9 de `test-noticias` (portada+galería+2 videos → carrusel de 5 ítems, orden ↑↓, remove, tope de videos) — **122 tests / 545 assertions** y 15/17 suites E2E. Nota superada por D16: las noticias reales ahora las carga `NoticiasPrecargadasSeeder` desde `public/Noticias Precargadas/` (portada + galería incluidas)
- [x] **Noticias precargadas reales (D16)**: carpeta fuente `public/Noticias Precargadas/` (lote de 5 noticias de Instagram del GDC/autoridad: una carpeta por noticia con imágenes `001…NNN` y un `.txt` con el cuerpo); nuevo `NoticiasPrecargadasSeeder` (declarativo e idempotente, también invocado por `DatabaseSeeder`): la fecha sale del sello del nombre de la imagen 001 (`ddmmyyyyhhmm`/`ddmmyyhhmm` validada con `checkdate`) y, si no parsea (los sellos de las noticias 1 y 2 no traen mes), de la línea `Fecha:`/`Fech:` del txt — la línea de Fecha se excluye del cuerpo; el texto se conserva **íntegro** (menciones, hashtags y emojis) partido en `<p>` por párrafos en blanco; la 001 → `featured_image`, las 002+ → filas `post_media` del carrusel D15 (posiciones 1..k) y cada imagen se copia a `uploads/YYYY/MM/` con su fila en `media` y sidecar WebP; mapa declarativo carpeta→slug/título/excerpt/categoría (lote 2 = carpeta nueva + entrada en el array); sin videos (el paquete no incluye URLs); limpieza de BD dev: borrado de los drafts `repro-d15*` y alineación del slug de la plantilla (`…distrito-capital` → `…distrito`) para que `PostSeeder` no duplique; bug de desarrollo: el patrón `Fech?:?` no incluía la letra `a` (casaba "Fech" y anulaba el fallback) → alternación `(?:Fecha|Fech)`; tests `NoticiasPrecargadasSeederTest` (2) — **124 tests / 610 assertions** y E2E `test-noticias`/`test-dashboard` en verde tras el alta (+5 noticias)
- [x] **Verificación residual de vulnerabilidades (G8)**: cerrado en el alcance posible con PHPUnit/E2E — `SearchXssTest` (INJ-03), trazabilidad PRIV-03 por auditoría G6, `UploadExtensionTest` (FILE-04), `CorsConfigurationTest` (CFG-07), `StorageSymlinkTest` (FILE-06); se añaden `SeoTest`, `SitemapTest` y `CacheTest` como cobertura funcional de D8/D10; los casos que requieren DAST/manual/infraestructura (FILE-07, FILE-09, INJ-05, INJ-06, SC-05, LOG-03) quedan en `INFORME-VULNERABILIDADES.md`/`PLAN-VULNERABILIDADES.md` para auditoría externa
- [x] **Limpieza cosmética** (C5): eliminados `.DS_Store`/`._.DS_Store` de `public/images/Logos` (más entradas en `.gitignore`), boilerplate `routes/api.php` (+ bloque `api`/rate-limiter del `RouteServiceProvider` y grupo `api` del `Http\Kernel`) y plantilla `resources/views/welcome.blade.php` sin usar

### 🔴 Pendiente

> 📋 **Seguimiento vivo en [PENDIENTES.md](PENDIENTES.md)** (checklist D activos + A/B/C/E; D2/D5/E4 diferidos en su §F). Al cerrar un ítem, marcarlo ahí y actualizar esta sección.

- [ ] **Contenido del `PartnerSeeder`** — borrador con array vacío, pendiente que se indique el listado de entes

### 🌟 Mejoras futuras (SPECS §10, fuera de alcance)
Newsletter, API REST, comentarios, galería, eventos, versión en inglés.

- **Permisos en caché (rendimiento, solo si hace falta)**: `User::hasPermission()` hace 1 query por llamada; el dashboard suma ~9 llamadas por carga sobre las ~20 que ya hace el sidebar (total ~30 consultas pequeñas e indexadas, <1 ms). Si en algún momento se notara, cargar los slugs del rol una sola vez en sesión y reutilizarlos en layout + dashboard.

### ⏸️ Omitidos / diferidos
- **CRUD de páginas estáticas en el admin** (PENDIENTES D2) — archivado: sin instrucción de que se vaya a necesitar; `/pagina/{slug}` queda tal cual.
- **Front/rediseño del detalle de entes** (`/entes/{slug}`, PENDIENTES D5/E4) — archivado: la vista actual (skeleton funcional) se da por buena hasta nueva instrucción de diseño.
- **Tags en noticias** (SPECS §5.4) — omitido por decisión; si se retoma, opción simple recomendada: columna `posts.tags` (varchar separado por comas) con chips en el detalle, sin página por tag.

### 🟠 Riesgos conocidos para producción (mitigaciones en curso, ver PENDIENTES.md)

Pendientes de decisión antes o después del despliegue (ver §14 para el checklist). **Seguimiento en [PENDIENTES.md](PENDIENTES.md)** (ítems A1–A4):

| # | Riesgo | Dónde | Impacto |
|---|---|---|---|
| 1 | `SESSION_SECURE_COOKIE` corregido (default `true` + clave en `.env.example`) | `config/session.php:171` | Cookies de sesión con flag `Secure` en HTTPS |
| 2 | Laravel 10 (10.50.2) — **EOL desde feb 2026** | `composer.json:10` | Sin parches de seguridad; **aceptado por decisión institucional** (la institución requiere Laravel 10 — PENDIENTES F/A5) |
| 3 | Bloque `"audit": {"ignore": [3 IDs PKSA-*]}` depurado (antes 17) | `composer.json` | 3 advisories de `laravel/framework` aceptados por EOL; el high (CRLF) queda documentado y con controles compensatorios |
| 4 | Sin CI (GitHub Actions descartado) ni PHPStan; Pint sí configurado (`pint.json` + `composer lint`, C2) | repo | No hay CI que ejecute lint/tests automáticamente; decisiones en PENDIENTES F/C1 |
| 5 | Cobertura PHPUnit ampliada (**124 tests**: auth, permisos, contacto, WebP, cambio de contraseña, restricción de roles, sanitización HTML, headers, cifrado PII, rate limit, hash de gacetas, validación de fotos, **log de auditoría**, **CSP nonce**, XSS reflejado, **flujo de aprobación de noticias**, **orden por fecha de publicación**, **seeders** (plantilla y noticias precargadas), **redes sociales multi-línea**, **galería/videos de noticias**, etc.) | `tests/Unit`, `tests/Feature` | El detalle funcional fino vive en las 17 suites E2E de Windows |
| 6 | Suites E2E solo Windows/Laragon | `tests/e2e/*.ps1` | No sirven para validar el servidor de producción |
| 7 | Usuarios seed con password `password` mitigado (cambio obligatorio en primer login) | `UserSeeder`, middleware `EnsurePasswordChanged` | Credenciales predecibles forzadas a rotación en primer acceso; en local/testing los fixtures seed no quedan bloqueados |

---

## 13. Desarrollo Local (Windows / Laragon)

> Esta sección es para **desarrollo**. Para el servidor real, ve §14 (Despliegue en Producción).

Requisitos: Laragon con Apache, MySQL y PHP 8.3; Node.js; Composer.

```powershell
# 1. Navegar al proyecto
cd C:\Users\aegir\Documents\GitHub\Portal2026

# 2. Configurar PATH de sesión (ajustar versión de PHP si difiere)
$env:Path = "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;D:\laragon\bin\composer;D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;" + $env:Path

# 3. Dependencias (solo la primera vez)
composer install
npm ci

# 4. Compilar assets (cuando cambien Sass/JS)
npm run build

# 5. Base de datos (primera vez / tras migrate:fresh)
php artisan migrate --force
php artisan db:seed
php artisan storage:link   # enlace público -> storage/app/public (subidas de medios)

# 6. Arrancar Apache y MySQL desde Laragon (Inicio > Arrancar todo)
#    - Ojo: no correr XAMPP al mismo tiempo (puertos 80/3306 ocupados)
#    - Vhost ya configurado: portal2026.test -> Portal2026/public
#    - Entrada en C:\Windows\System32\drivers\etc\hosts: 127.0.0.1 portal2026.test

# 7. Abrir navegador en:
#    http://portal2026.test
#    http://portal2026.test/admin/login   (admin@gdc.gob.ve / password)

# Alternativa sin vhost:
php artisan serve --port=8000   #  →  http://localhost:8000
```

Tras un reinicio del equipo, solo hace falta: arrancar Laragon (*Inicio > Arrancar todo*) — los vhost, hosts y `storage:link` ya quedan configurados.

> **Sesión segura por entorno:** con `APP_ENV=local` la cookie de sesión se envía sin el flag `Secure` (permite desarrollo sobre HTTP). Si `APP_ENV` no es `local`, `config/session.php` activa `secure=true` por defecto; en producción es **obligatorio HTTPS** y se puede forzar con `SESSION_SECURE_COOKIE=true` en `.env`.

---

## 14. Despliegue en Producción

**Destino:** servidor institucional Linux con salida a internet (Nginx o Apache + PHP + MySQL/MariaDB), con acceso por **FTP** y **SSH**.

### 14.1 Requisitos del servidor

| Componente | Mínimo | Notas |
|---|---|---|
| PHP | 8.1+ (recomendado 8.2/8.3) | Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `bcmath`, `dom`, **`gd` con soporte WebP** (`imagewebp()` — habilita D9 WebP con fallback; sin él el portal funciona igual pero sin convertir imágenes) |
| MySQL / MariaDB | 8.0 / 10+ | BD y usuario dedicados (no `root`) |
| Node.js | 18+ | Solo si se compila en el servidor; si no, compilar localmente y subir los assets (14.3.C) |
| Composer | 2.x | Solo si se instala en el servidor; si no, subir `vendor/` ya instalado en `--no-dev` |
| HTTPS | obligatorio | Certificado institucional o Let's Encrypt |

### 14.2 Estructura del servidor (vhost)

Recomendado: **la raíz del vhost apunta a `public/`** — así `.env`, `storage/`, `vendor/` y `.git/` quedan fuera del alcance HTTP.

```nginx
server {
    server_name portal.gdc.gob.ve;
    root /var/www/portal2026/public;          # solo public/ es accesible por HTTP
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # ajustar versión/puerto
    }

    location ~ /\. { deny all; }               # bloquea .env, .git, etc.
}
```

Equivalente Apache: `DocumentRoot /var/www/portal2026/public` con el `.htaccess` de Laravel habilitado (`AllowOverride All`).

> ⚠️ Si la raíz web fuera la del proyecto completo, el sitio quedaría expuesto a la lectura de `.env` (credenciales BD y APP_KEY). Nunca hacerlo.

### 14.3 Checklist de despliegue (primera vez)

#### A. Código
1. Preferible: `git clone <repo> /var/www/portal2026` por **SSH**.
2. Por FTP: subir todo excluyendo `node_modules/` (se reinstala con `npm ci`) y, si en el servidor hay Composer, también `vendor/`. El `.env` de desarrollo **no se sube** (se crea en el paso D).

#### B. Dependencias PHP
```bash
cd /var/www/portal2026
composer install --no-dev --optimize-autoloader
```
- *Sin Composer en el servidor*: ejecutar `composer install --no-dev` en una copia limpia local (sin `.env` de dev) y subir `vendor/`.

#### C. Assets (obligatorio)
`public/build/` (Vite) y `public/vendor/` (TinyMCE) **no están en git**. Sin ellos el sitio falla con *"Vite manifest not found"* y el editor no carga.

- **Opción 1 (recomendada)** — compilar en el servidor: `npm ci && npm run build`
- **Opción 2** — compilar localmente y subir las carpetas `public/build/` y `public/vendor/` (generadas por `npm run build`)

#### D. `.env` de producción
Crear **in situ** desde `.env.example` (nunca copiar el `.env` de desarrollo). Claves críticas:

| Clave | Valor en producción |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | **`false`** (con `true`, los errores 500 filtran trazas y datos sensibles) |
| `APP_KEY` | la misma de desarrollo, o nueva vía `php artisan key:generate` (una clave nueva invalida sesiones/tokens existentes) |
| `APP_URL` | `https://portal.gdc.gob.ve` (dominio real) |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | BD dedicada de producción |
| `MAIL_*` | SMTP real (el default de `.env.example` es un host de ejemplo que no existe) |
| `SESSION_SECURE_COOKIE` | `true` |

Verificar: `php artisan config:show | grep APP_DEBUG` (debe ser `false`).

#### E. Instalación
```bash
php artisan migrate --force      # migraciones
php artisan db:seed              # solo la primera vez (ver paso F.1)
php artisan storage:link         # ⚠️ requiere SSH — FTP no puede crear el symlink
php artisan webp:generate        # backfill WebP de las imágenes existentes (idempotente)
php artisan optimize             # config:cache + route:cache + view:cache
```

> **Si solo hay FTP (sin SSH)** para `storage:link`: pedir al administrador del servidor que cree el enlace
> `ln -s /var/www/portal2026/storage/app/public /var/www/portal2026/public/storage`,
> o configurar un alias en el vhost: `location /storage/ { alias /var/www/portal2026/storage/app/public/; }`.
> Sin esto, las imágenes subidas (medios, destacadas, logos de entes) no se sirven.

#### F. Pasos de seguridad post-despliegue (obligatorios)
1. **Cambiar o eliminar los usuarios seed**: `admin@gdc.gob.ve`, `admin2@gdc.gob.ve`, `editor@gdc.gob.ve` tienen password **`password`** (§9/§10). Crear el usuario administrador real con contraseña fuerte y borrar o desactivar (`is_active`) los de prueba. Ver §15 para el comando.
2. Confirmar `APP_DEBUG=false` y `APP_ENV=production`.
3. **Cifrar PII heredado**: `php artisan pii:encrypt` (idempotente; `--dry-run` para previsualizar). Re-cifra `contacts.name/email/subject/message` y `users.name` que sigan en texto plano tras la migración `encrypt_pii_columns`.
4. **HTTPS obligatorio** y HSTS fijado en el servidor web (`Strict-Transport-Security: max-age=31536000; includeSubDomains`). La app también lo emite en producción. Si hay proxy/CDN, configurar `TRUSTED_PROXIES` (CSV de IPs o `*`) y `TRUSTED_HOSTS` (CSV de dominios) en `.env`.
5. Ejecutar `composer audit` y revisar los advisories ocultos por el bloque `audit.ignore` de `composer.json`.
6. Permisos: `storage/` y `bootstrap/cache/` deben ser escribibles por el usuario de PHP-FPM/Apache, p. ej. `chown -R www-data:www-data storage bootstrap/cache`.
7. `.env` con permisos restrictivos (`chmod 600 .env`) y fuera de la raíz web.
8. Cargar contenido real: entes (`PartnerSeeder` está vacío), URLs de redes sociales desde `/admin/rrss` y enlaces de "Intranet GDC" (Funcionarios Activos / Jubilados) desde `/admin/configuracion` (vacío = ítem oculto); verificar `/admin/login`.

#### G. Actualizaciones posteriores
```bash
# tras subir una versión nueva de código:
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # o subir public/build + public/vendor
php artisan migrate --force
php artisan webp:generate        # opcional: backfill idempotente de sidecars .webp
php artisan optimize             # si algo falla: php artisan optimize:clear
```

#### H. No se necesita cron ni worker
El proyecto **no tiene tareas programadas** (`app/Console/Kernel.php` sin schedule) y la cola es `sync`. No hay que configurar `schedule:run` ni un proceso de jobs.

#### I. Límites de PHP (`php.ini`)
| Directriz | Valor | Motivo |
|---|---|---|
| `upload_max_filesize` / `post_max_size` | ≥ `21M` | Gacetas aceptan hasta 20 MB (medios: 10 MB) |
| `memory_limit` | ≥ `256M` | Listados/administración con muchas filas y subidas múltiples |
| `max_execution_time` | ≥ `60` | Subidas múltiples de archivos |

#### J. Qué NO funciona en el servidor de producción
- Suites E2E (`tests/e2e/*.ps1`): solo Windows + Laragon (§16).
- PHPUnit: no instalado en producción (`composer install --no-dev`); en desarrollo corre con su propia BD **sqlite en memoria** (`phpunit.xml`, sin tocar MySQL) — §16.

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


# Usuarios por defecto: ya los crea UserSeeder (password: "password")
# Si necesitas uno adicional con tinker (en producción, contraseña fuerte):
php artisan tinker
\App\Models\User::create(['name'=>'Nuevo','email'=>'nuevo@gdc.gob.ve','password'=>bcrypt('password'),'role'=>'admin']);

# Refrescar BD
php artisan migrate:fresh --seed

# Ver rutas
php artisan route:list
```

---

## 16. Tests (suites E2E + PHPUnit)

Suites PowerShell (Windows + Laragon) contra `http://portal2026.test`. Requieren `mysql.exe` y `php.exe` de Laragon (rutas al inicio de cada script).

> ⚠️ **Solo Windows**: los scripts asumen Laragon (rutas `D:\laragon\bin\...` y vhost `portal2026.test`). No corren en Linux/CI ni contra el servidor de producción.

| Suite | Cubre |
|---|---|
| `test-categorias.ps1` | Filtro por categoría en /noticias y /categoria/{slug}; orden y links del footer |
| `test-gacetas.ps1` | CRUD de Gacetas Oficiales + vista pública + permisos |
| `test-social.ps1` | Módulo Redes Sociales (permisos, `rrss.manage` en grupo "Gestión Portal") + formulario multi-línea (7 textareas, validación URL por línea, «línea N»), iconos **solo-footer** con **1 icono por URL** (multi-cuenta: 2 FB = 2 iconos + tooltip `Red — URL`), seeder no pisa editados, snapshot/restauración `HEX`/`UNHEX` multi-línea segura (12 secciones) |
| `test-settings.ps1` | Identidad / Enlaces (`/admin/configuracion`): nombre del sitio (títulos), logo (upload/quitar), enlaces navbar (URL absoluta, ruta válida, `..`, ruta inexistente), ocultar ítem vacío, `site_name` obligatorio, permisos (40/67, `settings.manage` solo superadmin, 3 grupos + 15 módulos incl. Auditoría, doble cabecera en `/admin/roles`), snapshot/restauración |
| `test-users.ps1` | `is_active`/`last_login_at`: bloqueo de login a inactivos, registro de último acceso, guard de auto-desactivación |
| `test-wysiwyg.ps1` | Editor TinyMCE (assets offline), HTML crudo en detalle, compat texto plano, **D15: sin plugin image ni handler de subida + imagen inline eliminada del texto con aviso visible**, endpoint JSON de la biblioteca Medios, "Copiar URL" en medios, permisos del editor |
| `test-contact-info.ps1` | Info de contacto en settings + defaults + permisos |
| `test-contact-messages.ps1` | Bandeja de mensajes (`contacts`) |
| `test-entes.ps1` | CRUD de Entes afiliados + slugs + nav activo + buscador con filtro client-side |
| `test-media.ps1` | Medios + imagen destacada de noticias + biblioteca |
| `test-noticias.ps1` | Flujo de aprobación (D12): badges Pendiente/Programada/Publicado, botón Aprobar individual + auditoría `news.approved`, aprobación masiva (solo borradores, omitidas las no-borrador), editor sin UI de aprobación (sin checkboxes/masivo/Aprobar, 403 en rutas `news.approve`, selects deshabilitados, intento de publicar → draft + flash warning), programación (404 público hasta la fecha, badge Programada, vaciar fecha = publicar ya), editor no adelanta programadas; **D15 multimedia**: crear noticia con portada + 2 imágenes de galería + video YouTube + archivo mp4 → `post_media` con rutas `uploads/YYYY/MM/`, videos en el módulo sin `is_featured`, carrusel de 5 ítems con la portada primero, reorden ↑/↓, `remove_media[]` y tope de 3 videos; limpieza no destructiva |
| `test-videos.ps1` | Módulo Videos: permisos `videos.*` (40/67, solo superadmin), sidebar (sección + dropdown por rol, 403 admin/editor), cargar YouTube (válida/inválida) + subir mp4, destacar/cambiar/"Ninguno" (siempre ≤1 `is_featured`), home refleja la portada, borrado + limpieza |
| `test-partners.ps1` | Upload/replace/quitar logo de entes (incl. sidecars `.webp`) |
| `test-share.ps1` | Botones de compartir + lazy loading |
| `test-webp.ps1` | WebP con fallback (D9): `webp:generate` (backfill + idempotencia), sidecar al subir, GET `.webp` 200 `image/webp`, GIF y PNG corrupto sin conversión (contador `ilegibles`, exit 0), `<picture>` con fallback en `/noticias`, destroy borra original + sidecar, `picture{display:contents}` en el build |
| `test-errors.ps1` | Páginas de error HTTP (incl. 503 real vía `artisan down`) |
| `test-dashboard.ps1` | Dashboard (D1): redirect sin sesión, 9 tarjetas con conteos vs SQL dinámicos, tracking `daily_views` (+1 en `views` lifetime y en la tarjeta tras leer un artículo), gráfico de exactamente 30 barras + total del período, filtrado por rol (editor 3 tarjetas / admin 7 / superadmin 9), exactamente 1 form de logout, smoke de los 3 roles |

Ejecución:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests\e2e\test-categorias.ps1
```

Garantías de las suites:
- **No destructivas**: solo borran las filas/archivos que ellas crean (incluidos los sidecars `.webp`); donde mutan settings (`social_*`, `contact_*`, `site_*`/`nav_*`) hacen **snapshot y restauración** de los valores previos (y del archivo de logo); los asserts no asumen BD vacía (usan conteo base).
- `test-errors.ps1` garantiza `artisan up` con `try/finally`.
- `render_errors.php` renderiza las 12 vistas de error por CLI (se ejecuta desde `tests/e2e/`).

### PHPUnit (`tests/Unit`, `tests/Feature`)

- Ejecución: `./vendor/bin/phpunit` (o `--testsuite Unit,Feature`) — **124 tests / 610 assertions**, en verde (1 skipped cuando no existe el symlink `public/storage`).
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
  - `PermissionTest` — 403/200 por rol, `hasPermission()` con reparto canónico 40/22/5, `PermissionSeeder`.
  - `PiiEncryptionTest` — cifrado de PII heredado via `php artisan pii:encrypt`.
  - `NoticiasPrecargadasSeederTest` — D16: lote de 5 noticias precargadas desde `public/Noticias Precargadas/` — fechas correctas por sello de nombre (con fallback a la línea `Fecha:` del txt, excluida del cuerpo), categorías asignadas, portada + galerías 3/0/2/3/2 en `uploads/2026/10/`, 15 filas `media`, texto íntegro (`@nicolasmaduro`, `#13oct`) en `<p>`, posiciones consecutivas y re-ejecución sin duplicar posts/archivos/filas.
  - `PostMediaTest` — D15: galería de noticias — portada+imágenes en `uploads/YYYY/MM/` y carrusel público (portada primero), orden por posición y botón ↑/↓ (`move`), límites 10 imágenes y 3 videos con mensaje en español, URL YouTube nueva crea registro en el módulo `videos` sin `is_featured`, archivo mp4 en `videos/`, `remove_media` exige filas propias y conserva el archivo en disco, video existente enlazado y solo portada = imagen estática sin carrusel.
  - `PostSeederTest` — D13: `PostSeeder` crea las 4 noticias de ejemplo con sus estados (publicada+destacada, migrada con fecha pasada, borrador, programada), purga las placeholders `noticia-destacada-*` **incluidas filas soft-deleted** y es idempotente al re-ejecutarse.
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
  - `Unit/WebpTest` — reglas de conversión de D9.
- El detalle funcional fino sigue en las suites E2E de PowerShell.
- Estilo: `composer lint` (`pint --test`) debe pasar; `composer lint:fix` aplica Pint (C2).
- Nota: el `UserFactory` usa `Hash::make('password')` (no un hash hardcodeado) para respetar `BCRYPT_ROUNDS` de tests.