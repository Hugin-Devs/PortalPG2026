# Portal GDC — Documentación del Sistema

## 1. Descripción General

Portal institucional del **Gobierno de Distrito Capital (GDC)**.
Sitio web informativo con panel administrativo para gestión de contenidos.
Inspirado en: https://mippci.gob.ve

---

## 2. Stack Tecnológico

| Componente | Versión |
|---|---|
| PHP | 8.2+ |
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
│   │   │   │                     # Partner, Media, OrgMember, About, Role, Service
│   │   │   └── Public/           # Home, News, Service, About, Contact, Search, Page
│   │   └── Middleware/
│   │       ├── CheckPermission.php   # alias 'permission' (superadmin tiene bypass)
│   │       └── CheckRole.php         # alias 'role'
│   └── Models/                   # Category, Media, OrgMember, Page, Partner,
│                                 # Permission, Post, Service, Setting, User
├── database/
│   ├── migrations/               # 14 migraciones (incl. org_members, permissions,
│   │                             #  role_permission con unique(role, permission_id))
│   └── seeders/                  # Database, Category, Post, User, Permission
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
│       │                         # services, services-show, about, contact,
│       │                         # search, page
│       └── admin/                # auth, dashboard, news, categories, users,
│                                 # partners, media, org-members, about, roles,
│                                 # services (vistas listas, rutas deshabilitadas)
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

### 4.4 services
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| description | text | nullable |
| content | longtext | nullable |
| icon | varchar(255) | nullable |
| external_url | varchar(255) | nullable |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.5 partners (Entes Afiliados)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| logo | varchar(255) | nullable |
| url | varchar(255) | nullable |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.6 pages
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| content | longtext | nullable |
| is_active | boolean | default true |
| timestamps | | |

### 4.7 media
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

### 4.8 settings
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| key | varchar(255) | unique |
| value | text | nullable |
| timestamps | | |

### 4.9 org_members (Estructura Organizativa)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| position | varchar(255) | cargo |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

### 4.10 permissions
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | nombre legible |
| slug | varchar(255) | unique — ej. `news.create` |
| group | varchar(255) | agrupación en la UI de roles |
| timestamps | | |

### 4.11 role_permission (rol ↔ permiso)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| role | varchar(255) | `superadmin` / `admin` / `editor` — único junto con `permission_id` |
| permission_id | bigint FK | permissions, cascadeOnDelete |
| timestamps | | |

---

## 5. Rutas

### 5.1 Públicas

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/` | `Public\HomeController@index` | home |
| GET | `/noticias` | `Public\NewsController@index` | news.index |
| GET | `/noticias/{slug}` | `Public\NewsController@show` | news.show |
| GET | `/categoria/{slug}` | `Public\NewsController@category` | news.category |
| GET | `/servicios` | `Public\ServiceController@index` | services.index |
| GET | `/servicios/{slug}` | `Public\ServiceController@show` | services.show |
| GET | `/sobre-nosotros` | `Public\AboutController@index` | about.index |
| GET | `/contacto` | `Public\ContactController@index` | contact.index |
| POST | `/contacto` | `Public\ContactController@store` | contact.store |
| GET | `/buscar` | `Public\SearchController@index` | search |
| GET | `/pagina/{slug}` | `Public\PageController@show` | page.show |

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
| GET/PUT | `/admin/nosotros` | `about.edit` | `Admin\AboutController` |
| GET | `/admin/estructura` (+ crear/editar/PUT/DELETE) | `org.*` | `Admin\OrgMemberController` |
| GET/POST | `/admin/roles` | `roles.manage` | `Admin\RoleController` |

> **Nota:** las rutas del CRUD de **servicios** en el admin están **comentadas deliberadamente** (`routes/web.php`), junto con su enlace en el sidebar. El módulo existe a medias (controlador y vistas) pero su alcance no está definido; se omitió a propósito.

---

## 6. Controladores

### 6.1 Públicos (`app/Http/Controllers/Public/`)

| Controlador | Métodos | Estado |
|---|---|---|
| `HomeController` | `index()` | ✅ Destacados + últimos + categorías + "más leído" + `$videos` (placeholder) |
| `NewsController` | `index()` | ✅ Listado paginado |
| | `show($slug)` | ✅ Detalle + incrementa `views` + relacionados |
| | `category($slug)` | ✅ Filtrado por categoría |
| `ServiceController` | `index()` / `show($slug)` | ✅ Listado y detalle |
| `AboutController` | `index()` | ✅ Página institucional |
| `ContactController` | `index()` | ✅ Formulario |
| | `store()` | ⚠️ Valida y confirma con mensaje; **no persiste** (no hay tabla `contacts`) |
| `PageController` | `show($slug)` | ✅ Páginas estáticas |
| `SearchController` | `index()` | ✅ Búsqueda por título/contenido/extracto |

### 6.2 Admin (`app/Http/Controllers/Admin/`)

| Controlador | Métodos | Estado |
|---|---|---|
| `AuthController` | `showLogin` / `login` / `logout` | ✅ |
| `DashboardController` | `index()` | ✅ |
| `NewsController` | CRUD completo (`index, create, store, edit, update, destroy`) | ✅ Slug automático, soft deletes |
| `CategoryController` | CRUD completo | ✅ |
| `UserController` | CRUD completo | ✅ Asignación de roles |
| `PartnerController` | CRUD completo | ✅ Entes afiliados (footer) |
| `MediaController` | `index, store, destroy` | ✅ Sube a disco `public/uploads`, requiere `storage:link` |
| `OrgMemberController` | CRUD completo | ✅ Estructura organizativa |
| `AboutController` | `index, update` | ✅ Edición de "Sobre Nosotros" |
| `RoleController` | `index, update` | ✅ Matriz rol×permiso (un formulario JS por rol) |
| `ServiceController` | CRUD completo | ⏸️ **Omitido** — rutas comentadas, alcance no definido |

---

## 7. Vistas

### 7.1 Layouts

| Archivo | Descripción |
|---|---|
| `layouts/public.blade.php` | Layout público: header con logo + navbar + footer |
| `layouts/admin.blade.php` | Layout admin: sidebar + topbar + main |

### 7.2 Públicas (`resources/views/public/`)

| Archivo | Secciones | Estado |
|---|---|---|
| `home.blade.php` | Hero slider, "Infórmate y Comunica", grid de noticias, "Lo más leído", slider de videos | ✅ |
| `news.blade.php` | Listado paginado de noticias | ✅ |
| `news-show.blade.php` | Detalle de noticia + relacionados | ✅ |
| `news-category.blade.php` | Noticias por categoría | ✅ |
| `services.blade.php` / `services-show.blade.php` | Listado y detalle de servicios/entes | ✅ |
| `about.blade.php` | "Sobre Nosotros" | ✅ |
| `contact.blade.php` | Formulario de contacto + datos de contacto | ✅ |
| `search.blade.php` | Resultados de búsqueda | ✅ |
| `page.blade.php` | Páginas estáticas | ✅ |

### 7.3 Admin (`resources/views/admin/`)

| Módulo | Archivos | Estado |
|---|---|---|
| Auth | `auth/login.blade.php` | ✅ Login independiente |
| Dashboard | `dashboard/index.blade.php` | ✅ |
| Noticias | `news/{index,create,edit}` | ✅ |
| Categorías | `categories/{index,create,edit}` | ✅ |
| Usuarios | `users/{index,create,edit}` | ✅ |
| Entes afiliados | `partners/{index,create,edit}` | ✅ |
| Medios | `media/index.blade.php` | ✅ Grid + subida |
| Estructura org. | `org-members/{index,create,edit}` | ✅ |
| Sobre Nosotros | `about/index.blade.php` | ✅ Edición inline |
| Roles y permisos | `roles/index.blade.php` | ✅ Matriz con envío JS |
| Servicios | `services/{index,create,edit}` | ⏸️ Vistas listas, rutas deshabilitadas |

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

- **Tabla**: `permissions` (25 permisos con `slug` tipo `news.create`) + `role_permission` (rol ↔ permiso, con índice único).
- **Middleware**: alias `permission` registrado en `app/Http/Kernel.php` → `App\Http\Middleware\CheckPermission`. Aplicado ruta a ruta en `routes/web.php` (ej. `->middleware('permission:news.edit')`). El rol `superadmin` tiene bypass.
- **Consulta en vista**: `auth()->user()->hasPermission('slug')` (`app/Models/User.php`) — las vistas del admin ocultan botones/menús sin permiso.
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
| `PostSeeder` | 5 posts de prueba (3 destacados, 2 normales) — requiere ≥1 categoría y ≥1 usuario |
| `UserSeeder` | 3 usuarios: superadmin, admin, editor (password `password`) — `firstOrCreate` |
| `PermissionSeeder` | 25 permisos + asignaciones a los 3 roles |

**Idempotentes**: los 4 seeders se pueden re-ejecutar sin duplicar filas ni fallar (`firstOrCreate` / `updateOrCreate` / `upsert` contra el índice único `role+permission_id`).

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

### Modo producción
```powershell
npm run build
php artisan serve
```

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
- [x] Modelos Eloquent (10) con relaciones y slugs
- [x] Seeders: categorías, posts, usuarios (3 roles), permisos (25) — `UserSeeder`/`PermissionSeeder` idempotentes
- [x] Autenticación admin (login independiente + logout)
- [x] Roles y permisos granulares: middleware `permission:*`, `User::hasPermission()`, ocultación en UI, módulo `/admin/roles`
- [x] CRUD admin: noticias, categorías, usuarios, entes afiliados, medios, estructura organizativa, "Sobre Nosotros"
- [x] Home pública: hero slider de destacados, categorías, grid de noticias, "Lo más leído"
- [x] Páginas públicas: listado/detalle de noticias, por categoría, servicios, sobre nosotros, contacto, búsqueda, páginas estáticas
- [x] Contador de visitas (`views`) en detalle de noticia
- [x] Footer con entes afiliados desde BD
- [x] Gestor de medios (subida a `public/uploads` vía disco `public`)
- [x] `storage:link` configurado
- [x] Paleta y tipografía institucional (`#273376`, Georama / Instrument Sans)

### 🔴 Pendiente
- [ ] **Módulo de servicios en el admin** — omitido a propósito: rutas comentadas (`routes/web.php`) y alcance por definir (¿qué son "los servicios"?)
- [ ] Contacto: persistencia de mensajes (hoy valida y confirma; no existe tabla `contacts`)
- [ ] Videos YouTube en home: `$videos` llega vacío del `HomeController` (fuente de datos sin definir)
- [ ] Editor WYSIWYG para contenido (hoy es `<textarea>` con HTML a mano — SPECS §9.4 pide TinyMCE/Trix)
- [ ] SEO: sitemap.xml, meta tags dinámicos (og:image, description)
- [ ] Imágenes WebP con fallback + lazy loading
- [ ] Cache de consultas pesadas

### 🌟 Mejoras futuras (SPECS §10, fuera de alcance)
Newsletter, API REST, comentarios, galería, eventos, versión en inglés.

---

## 13. Cómo Iniciar el Proyecto

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

---

## 14. Comandos Útiles

```powershell
# Limpiar caché
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear

# Reconstruir assets
npm run build

# Usuarios por defecto: ya los crea UserSeeder (password: "password")
# Si necesitas uno adicional con tinker:
php artisan tinker
\App\Models\User::create(['name'=>'Nuevo','email'=>'nuevo@gdc.gob.ve','password'=>bcrypt('password'),'role'=>'admin']);

# Refrescar BD
php artisan migrate:fresh --seed

# Ver rutas
php artisan route:list
```
