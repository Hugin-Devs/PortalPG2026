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
intranet-gdc/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── NewsController.php
│   │   │   └── Public/
│   │   │       ├── HomeController.php
│   │   │       ├── NewsController.php
│   │   │       ├── PageController.php
│   │   │       ├── ServiceController.php
│   │   │       └── ContactController.php
│   │   └── ...
│   └── Models/
│       ├── Category.php
│       ├── Media.php
│       ├── Page.php
│       ├── Partner.php
│       ├── Post.php
│       ├── Service.php
│       ├── Setting.php
│       └── User.php
├── config/
├── database/
│   ├── migrations/
│   │   ├── 2014_10_12_000000_create_users_table.php
│   │   ├── 2014_10_12_100000_create_password_reset_tokens_table.php
│   │   ├── 2019_08_19_000000_create_failed_jobs_table.php
│   │   ├── 2019_12_14_000001_create_personal_access_tokens_table.php
│   │   ├── 2026_07_07_171525_create_categories_table.php
│   │   ├── 2026_07_07_171526_create_posts_table.php
│   │   ├── 2026_07_07_171527_create_partners_table.php
│   │   ├── 2026_07_07_171527_create_services_table.php
│   │   ├── 2026_07_07_171528_create_media_table.php
│   │   ├── 2026_07_07_171528_create_pages_table.php
│   │   └── 2026_07_07_171529_create_settings_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── CategorySeeder.php
│       └── PostSeeder.php
├── public/
│   ├── build/          # Assets compilados por Vite
│   ├── images/         # Imágenes estáticas (logo, etc.)
│   └── ...
├── resources/
│   ├── css/
│   │   └── app.scss    # Estilos personalizados + Bootstrap
│   ├── images/
│   │   └── logo.png
│   ├── js/
│   │   └── app.js      # Bootstrap JS + inicializaciones
│   └── views/
│       ├── layouts/
│       │   ├── public.blade.php
│       │   └── admin.blade.php
│       ├── public/
│       │   ├── home.blade.php
│       │   └── news.blade.php
│       └── admin/
│           ├── auth/
│           │   └── login.blade.php
│           ├── dashboard/
│           │   └── index.blade.php
│           └── news/
│               ├── index.blade.php
│               └── create.blade.php
├── routes/
│   └── web.php
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
| GET | `/contacto` | `Public\ContactController@index` | contact.index |
| POST | `/contacto` | `Public\ContactController@store` | contact.store |
| GET | `/pagina/{slug}` | `Public\PageController@show` | page.show |

### 5.2 Admin

| Método | URI | Middleware | Controlador |
|---|---|---|---|
| GET | `/admin/login` | web | `Admin\AuthController@showLogin` |
| POST | `/admin/login` | web | `Admin\AuthController@login` |
| GET | `/admin` | auth | `Admin\DashboardController@index` |
| POST | `/admin/logout` | auth | `Admin\AuthController@logout` |
| GET | `/admin/noticias` | auth | `Admin\NewsController@index` |
| GET | `/admin/noticias/crear` | auth | `Admin\NewsController@create` |
| POST | `/admin/noticias` | auth | `Admin\NewsController@store` |

---

## 6. Controladores

### 6.1 Públicos

| Controlador | Métodos | Estado |
|---|---|---|
| `Public\HomeController` | `index()` | ✅ Funcional. Retorna vista con `$featuredPosts`, `$latestPosts`, `$categories` |
| `Public\NewsController` | `index()` | ✅ Vista básica (protegida con layout) |
| | `show($slug)` | 🔴 Pendiente |
| | `category($slug)` | 🔴 Pendiente |
| | `search()` | 🔴 Pendiente |
| `Public\ServiceController` | `index()` | 🔴 Pendiente |
| | `show($slug)` | 🔴 Pendiente |
| `Public\ContactController` | `index()` | 🔴 Pendiente |
| | `store()` | 🔴 Pendiente |
| `Public\PageController` | `show($slug)` | 🔴 Pendiente |

### 6.2 Admin

| Controlador | Métodos | Estado |
|---|---|---|
| `Admin\AuthController` | `showLogin()` | ✅ Login independiente (sin sidebar) |
| | `login()` | ✅ Autenticación con email/password |
| | `logout()` | ✅ Cierra sesión y redirige a login |
| `Admin\DashboardController` | `index()` | ✅ Dashboard básico con bienvenida |
| `Admin\NewsController` | `index()` | ✅ Listado paginado de noticias |
| | `create()` | ✅ Formulario de creación con categorías |
| | `store()` | ✅ Guarda noticia con slug automático |
| | `edit($id)` | 🔴 Pendiente |
| | `update($id)` | 🔴 Pendiente |
| | `destroy($id)` | 🔴 Pendiente |

---

## 7. Vistas

### 7.1 Layouts

| Archivo | Descripción |
|---|---|
| `layouts/public.blade.php` | Layout público: header con logo + navbar + footer |
| `layouts/admin.blade.php` | Layout admin: sidebar + topbar + main |

### 7.2 Públicas

| Archivo | Secciones | Estado |
|---|---|---|
| `public/home.blade.php` | Hero slider, "Infórmate y Comunica", Grid de noticias | ✅ |
| `public/news.blade.php` | Listado de noticias | Esqueleto (pendiente lógica) |

### 7.3 Admin

| Archivo | Descripción | Estado |
|---|---|---|
| `admin/auth/login.blade.php` | Login independiente con logo + formulario + enlace "Volver al inicio" | ✅ |
| `admin/dashboard/index.blade.php` | Dashboard con bienvenida al usuario | ✅ |
| `admin/news/index.blade.php` | Tabla de noticias con acciones | ✅ |
| `admin/news/create.blade.php` | Formulario de creación de noticia | ✅ |

---

## 8. Diseño y Paleta de Colores

### Colores

| Color | Hex | Uso |
|---|---|---|
| Azul oscuro (primary) | `#273376` | Títulos, header, footer, enlaces, botones |
| Rojo/burgundy | `#A70336` | Acentos, hover, badges |
| Amarillo/dorado | `#FFC907` | Detalles decorativos, alertas |
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

### Usuario por defecto (creado con tinker)
```php
User::create([
    'name' => 'Admin',
    'email' => 'admin@gdc.gob.ve',
    'password' => bcrypt('password'),
    'role' => 'superadmin'
]);
```

---

## 10. Seeders

### CategorySeeder
Crea 5 categorías:
- Nacionales, Política, Economía, Cultura, Deporte

### PostSeeder
Crea 5 posts de prueba (3 destacados, 2 normales).
Requiere que exista al menos 1 categoría y 1 usuario.

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
- [x] Proyecto Laravel 10 creado
- [x] Bootstrap 5.3 configurado con Vite + Sass
- [x] Layouts público y admin
- [x] Migraciones de todas las tablas
- [x] Modelos Eloquent
- [x] Seeders (categorías y posts)
- [x] Sistema de autenticación admin
- [x] Login independiente (sin sidebar)
- [x] Dashboard básico
- [x] CRUD de noticias (index, create, store)
- [x] Home pública con slider + categorías + grid
- [x] Logo institucional en navbar
- [x] Base de datos MySQL

### 🔴 Pendiente
- [ ] Editar/Eliminar noticias en admin
- [ ] Página individual de noticia (`/noticias/{slug}`)
- [ ] Página de categoría (`/categoria/{slug}`)
- [ ] Página de servicios (`/servicios`)
- [ ] Página de contacto (`/contacto`)
- [ ] Gestión de usuarios en admin
- [ ] Gestión de categorías en admin
- [ ] Gestión de servicios en admin
- [ ] Gestión de entes afiliados (partners)
- [ ] Gestión de páginas estáticas
- [ ] Gestor de medios
- [ ] Página "Quiénes somos"
- [ ] Sidebar "Lo más leído" en home
- [ ] Slider de videos YouTube
- [ ] Footer con logos de entes afiliados
- [ ] Roles y permisos (middleware)
- [ ] Búsqueda de noticias
- [ ] Sitemap

---

## 13. Cómo Iniciar el Proyecto

```powershell
# 1. Navegar al proyecto
cd C:\Users\aegir\Documents\GitHub\Portal2026

# 2. Configurar PATH (si no está en variables de entorno)
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\nodejs\node-v22;C:\laragon\bin\npm;" + $env:Path

# 3. Compilar assets
npm run build

# 4. Migrar base de datos y poblar
php artisan migrate --force
php artisan db:seed

# 5. Iniciar servidor
php artisan serve --port=8000

# 6. Abrir navegador en:
#    http://localhost:8000
#    http://localhost:8000/admin/login
```

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

# Crear usuario admin (ejecutar en tinker)
php artisan tinker
\User::create(['name'=>'Admin','email'=>'admin@gdc.gob.ve','password'=>bcrypt('password'),'role'=>'superadmin']);

# Refrescar BD
php artisan migrate:fresh --seed

# Ver rutas
php artisan route:list
```
