# SPECS — Intranet GDC

> Portal institucional informativo del ente GDC.
> Inspirado en: https://mippci.gob.ve
> Fecha: Julio 2026

---

## 1. OBJETIVO

Desarrollar un portal web institucional para **GDC** que sirva como canal oficial de comunicación, difusión de noticias, publicación de comunicados y presentación de servicios. El sitio debe contar con una sección pública para los visitantes y un panel administrativo cerrado para la gestión de contenidos.

---

## 2. STACK TECNOLÓGICO

| Tecnología | Versión | Notas |
|---|---|---|
| PHP | 8.2+ | |
| Laravel | 10.x | Última versión estable de la rama 10 |
| Bootstrap | 5.3+ | Solo componentes visuales, sin funcionalidad JS que interfiera con el backend |
| Base de datos | MySQL 8 / MariaDB 10+ | |
| Motor de plantillas | Blade | No se usa Livewire bajo ninguna circunstancia |
| JavaScript | Vanilla JS + Alpine.js (opcional) | Mínimo indispensable, nada de frameworks pesados |
| Frontend Assets | Vite | Bundler nativo de Laravel 10 |

### Prohibiciones

- ❌ **Livewire** — absolutamente prohibido
- ❌ Vue / React / Svelte en el frontend público
- ❌ jQuery si es reemplazable por vanilla JS

---

## 3. ARQUITECTURA GENERAL

```
intranet-gdc/
├── public/                  # Assets públicos
├── resources/
│   ├── views/
│   │   ├── public/          # Vistas del sitio público
│   │   ├── admin/           # Vistas del panel administrativo
│   │   ├── components/      # Componentes Blade reutilizables
│   │   └── layouts/         # Layouts maestros (public, admin)
│   ├── css/
│   └── js/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Public/      # Controladores del frontend
│   │   │   └── Admin/       # Controladores del backend
│   │   ├── Middleware/       # AdminAuth, etc.
│   │   └── Requests/        # Form requests
│   ├── Models/
│   ├── Services/
│   └── Providers/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
└── routes/
    ├── web.php              # Rutas públicas
    └── admin.php            # Rutas del panel (cargado en RouteServiceProvider)
```

---

## 4. SITIO PÚBLICO — ESTRUCTURA Y PÁGINAS

### 4.1 Layout Principal (`layouts/public.blade.php`)

```
┌─────────────────────────────────────────────────┐
│  HEADER                                         │
│  ┌──────┐  ┌──────────────────┐ ┌───────────┐   │
│  │ LOGO │  │    MENÚ NAV      │ │ REDES SOC │   │
│  │ GDC  │  │  (dropdowns)     │ │ + Admin   │   │
│  └──────┘  └──────────────────┘ └───────────┘   │
├─────────────────────────────────────────────────┤
│  CONTENIDO PRINCIPAL (yield content)             │
│                                                  │
├─────────────────────────────────────────────────┤
│  FOOTER                                          │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ...      │
│  │ VTV  │ │ RNV  │ │ AVN  │ │ ...  │ (entes)  │
│  └──────┘ └──────┘ └──────┘ └──────┘          │
│  Redes sociales + Copyright                     │
└─────────────────────────────────────────────────┘
```

#### Header
- **Logo GDC** (izquierda) — tamaño fijo, proporcional
- **Menú de navegación** con dropdown en "Servicios"
- **Enlace "Admin"** visible (redirecciona a `/admin/login`)
- **Redes sociales** (íconos): Facebook, Instagram, YouTube, X, Threads, Telegram, WhatsApp

#### Footer
- **Logos de entes afiliados** en fila (VTV, RNV, AVN, Correo del Orinoco, TeleSUR, VIVE, Ciudad CCS, VEA, Radio Miraflores, Radio del Sur, TVES, YVKE)
- **Redes sociales** repetidas
- Copyright: `© {año} GDC. Todos los derechos reservados.`

### 4.2 Página de Inicio (`public/home.blade.php`)

#### Sección 1: Hero Slider
- Carrusel de 3-5 noticias destacadas
- Cada slide: imagen de fondo 1080x783px + título + extracto + enlace "Leer más"
- Controles: flechas laterales + indicadores (dots)
- Implementación: Bootstrap Carousel o Swiper.js (sin jQuery)

#### Sección 2: "Infórmate y Comunica"
- Título: **"INFÓRMATE Y COMUNICA"**
- Grid de 3 cards (Economía, Cultura, Deporte) — cada una enlaza a la categoría

#### Sección 3: Comunicados Oficiales
- Título: **"COMUNICADOS OFICIALES"**
- Lista vertical con ícono de sello/escudo
- Enlace a categoría de comunicados

#### Sección 4: Grid de Noticias
- Título: **"ÚLTIMAS NOTICIAS"**
- Grid responsivo (3 columnas en desktop, 2 en tablet, 1 en mobile)
- Cada card: imagen (768x557px) + título + extracto corto + fecha
- Botón "Ver más" para más noticias
- Paginación (10-12 por página)

#### Sidebar (layout de dos columnas)
- **Lo más leído**: lista numerada de 5 artículos con más visitas
- **Videos YouTube**: miniatura + título de últimos 4 videos (embed)

#### Sección 5: Galería de Videos
- Slider/carrusel de thumbnails de YouTube
- Al hacer clic, abre modal o redirige a YouTube

### 4.3 Página "Sobre GDC" (`public/about.blade.php`)
- Contenido institucional: misión, visión, historia
- Imagen institucional
- Editado desde el admin (página estática gestionable)

### 4.4 Página de Servicios (`public/services.blade.php`)
- Listado de servicios ofrecidos por la institución
- Cada servicio: título, descripción, ícono, enlace a detalle
- Posibles servicios: PNI, Visa, SIVERIF, RRHH, Concursos

### 4.5 Página de Noticias (`public/news.blade.php`)
- Listado paginado de artículos
- Filtro por categoría (sidebar o tabs)
- Búsqueda por palabra clave
- Vista: grid de cards

### 4.6 Artículo Individual (`public/news-single.blade.php`)
- Imagen destacada grande
- Título, fecha, categoría
- Contenido HTML (WYSIWYG)
- Botones compartir redes sociales
- Relacionados (misma categoría)
- Contador de visitas

### 4.7 Página de Categoría (`public/category.blade.php`)
- Título de la categoría
- Grid de noticias filtradas por esa categoría
- Paginación

### 4.8 Página de Contacto (`public/contact.blade.php`)
- Formulario: nombre, correo, asunto, mensaje
- Validación del lado del servidor
- Almacenamiento en BD o envío por correo
- Dirección física, teléfono, mapa (Google Maps embed)

---

## 5. PANEL ADMINISTRATIVO

### 5.1 Layout (`layouts/admin.blade.php`)
```
┌─────────────────────────────────────────────────┐
│  TOP BAR: Logo GDC + Usuario + Cerrar sesión    │
├──────────┬──────────────────────────────────────┤
│ SIDEBAR  │  CONTENIDO PRINCIPAL                 │
│          │                                      │
│ Dashboard│  (yield content)                     │
│ Noticias │                                      │
│ Categor. │                                      │
│ Servicios│                                      │
│ Entes    │                                      │
│ Páginas  │                                      │
│ Usuarios │                                      │
│ Medios   │                                      │
│ Config.  │                                      │
│          │                                      │
└──────────┴──────────────────────────────────────┘
```

### 5.2 Autenticación
- Ruta: `/admin/login`
- Login con email + password
- Middleware `admin.auth` que protege todas las rutas admin
- Logout
- Sessions con tiempo de expiración

### 5.3 Roles y Permisos

| Rol | Descripción |
|---|---|
| Superadmin | Acceso total, puede gestionar usuarios y configuración |
| Admin | CRUD completo de contenidos, no puede gestionar usuarios |
| Editor | Crear/editar noticias, no puede publicar ni eliminar |

### 5.4 Módulos del Admin

#### Dashboard
- Tarjetas con conteos: noticias totales, usuarios, visitas del mes
- Últimas noticias publicadas
- Gráfico simple de visitas (opcional, con Chart.js)

#### Gestión de Noticias
- Listado con tabla: título, categoría, autor, estado (borrador/publicado), fecha
- Crear/Editar: título, slug (auto), extracto, contenido (editor WYSIWYG tipo TinyMCE o Trix), imagen destacada, categoría, tags, estado (borrador/publicado), fecha de publicación
- Eliminar (soft delete)
- Upload de imágenes con previsualización

#### Gestión de Categorías
- Listado: nombre, slug, descripción, icono, # de noticias
- Crear/Editar/Eliminar
- Una categoría puede tener categoría padre

#### Gestión de Servicios
- Listado: título, icono, orden, estado (activo/inactivo)
- Crear/Editar: título, descripción, icono (clase FontAwesome o imagen), contenido, enlace externo (opcional)
- Ordenable (drag & drop)

#### Gestión de Entes Afiliados
- Listado: nombre, logo, URL, orden
- Crear/Editar: nombre, logo (upload), URL, orden
- Los logos aparecen en el footer

#### Gestión de Páginas Estáticas
- Para páginas como "Sobre GDC"
- Título, slug, contenido, estado

#### Gestión de Usuarios (solo Superadmin)
- Listado: nombre, email, rol, última conexión
- Crear/Editar: nombre, email, password, rol
- Eliminar

#### Gestor de Medios
- Galería de imágenes subidas al servidor
- Vista en grid con previsualización
- Subir múltiples archivos
- Copiar URL de la imagen

#### Configuración
- Nombre del sitio
- Logo (cambiable desde admin)
- Redes sociales (URLs)
- Correo de contacto

---

## 6. DISEÑO Y LINEAMIENTOS VISUALES

### 6.1 Paleta de Colores

Extraída directamente del CSS del sitio de referencia (Elementor Kit):

| Color | Hex | Uso |
|---|---|---|
| Azul oscuro (primary) | `#273376` | Títulos, header, footer, enlaces, botones outline |
| Rojo/burgundy | `#A70336` | Acentos principales, hover de enlaces, header, footer, badges |
| Amarillo/dorado (accent) | `#FFC907` | Detalles decorativos, hover states, highlights |
| Blanco | `#FFFFFF` | Fondos de cards, texto sobre fondos oscuros |
| Gris oscuro (texto) | `#333333` | Texto del cuerpo |
| Gris secundario | `#54595F` | Texto secundario, meta-info |
| Gris claro | `#AFAFAF` | Bordes suaves, separadores |

### 6.2 Tipografía

Extraída del sitio de referencia:

| Estilo | Fuente | Peso |
|---|---|---|
| Headings (h1-h6) | `Georama MIPPCI` | Bold (700), SemiBold (600) |
| Body text | `Instrument Sans` | Regular (400) |
| Acentos / botones | `Georama MIPPCI` | SemiBold (600) |

> La fuente `Georama MIPPCI` es una variante personalizada. Como alternativa libre puede usarse `Georama` desde Google Fonts. `Instrument Sans` también está disponible en Google Fonts.

### 6.3 Logo
- Proporcionado por el cliente
- Debe mostrarse en header y opcionalmente en el footer
- Variante para fondo claro y oscuro

### 6.4 Responsive
- Mobile-first con Bootstrap 5
- Breakpoints: sm (576px), md (768px), lg (992px), xl (1200px)
- Menú colapsa en hamburguesa en mobile
- Grid de noticias: 1 col en mobile, 2 en tablet, 3 en desktop

---

## 7. BASE DE DATOS

### 7.1 Tablas Principales

#### `users`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | autoincrement |
| name | varchar(255) | |
| email | varchar(255) | unique |
| password | varchar(255) | hasheado |
| role | enum('superadmin','admin','editor') | default 'editor' |
| is_active | boolean | default true |
| last_login_at | timestamp | nullable |
| timestamps | | |

#### `categories`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| slug | varchar(255) | unique |
| description | text | nullable |
| icon | varchar(100) | clase de ícono (FontAwesome) |
| parent_id | bigint FK | nullable, self-referencing |
| order | integer | default 0 |
| is_active | boolean | default true |
| timestamps | | |

#### `posts`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| excerpt | text | resumen corto |
| content | longtext | HTML |
| featured_image | varchar(255) | ruta de imagen |
| category_id | bigint FK | nullable |
| user_id | bigint FK | autor |
| status | enum('draft','published') | default 'draft' |
| published_at | timestamp | nullable |
| is_featured | boolean | para hero slider |
| views | integer | default 0 |
| timestamps | | soft deletes |

#### `services`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | |
| description | text | |
| content | longtext | nullable, HTML |
| icon | varchar(100) | clase de ícono |
| external_url | varchar(255) | nullable |
| order | integer | |
| is_active | boolean | |
| timestamps | | |

#### `partners`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| logo | varchar(255) | ruta |
| url | varchar(255) | |
| order | integer | |
| is_active | boolean | |
| timestamps | | |

#### `pages`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | varchar(255) | |
| slug | varchar(255) | unique |
| content | longtext | HTML |
| is_active | boolean | |
| timestamps | | |

#### `media`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| filename | varchar(255) | |
| original_name | varchar(255) | |
| path | varchar(255) | |
| mime_type | varchar(100) | |
| size | integer | bytes |
| user_id | bigint FK | uploader |
| timestamps | | |

#### `settings`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| key | varchar(255) | unique |
| value | text | |
| timestamps | | |

#### `contacts` (opcional)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) | |
| subject | varchar(255) | |
| message | text | |
| read_at | timestamp | nullable |
| timestamps | | |

---

## 8. RUTAS

### 8.1 Rutas Públicas (`routes/web.php`)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/` | `Public\HomeController@index` | home |
| GET | `/quienes-somos` | `Public\PageController@show` | about |
| GET | `/servicios` | `Public\ServiceController@index` | services |
| GET | `/servicios/{slug}` | `Public\ServiceController@show` | services.show |
| GET | `/noticias` | `Public\NewsController@index` | news.index |
| GET | `/noticias/{slug}` | `Public\NewsController@show` | news.show |
| GET | `/categoria/{slug}` | `Public\NewsController@category` | news.category |
| GET | `/contacto` | `Public\ContactController@index` | contact |
| POST | `/contacto` | `Public\ContactController@store` | contact.store |
| GET | `/buscar` | `Public\NewsController@search` | search |
| GET | `/pagina/{slug}` | `Public\PageController@show` | page.show |

### 8.2 Rutas Admin (`routes/admin.php`)

| Método | URI | Controlador |
|---|---|---|
| GET | `/admin/login` | `Admin\AuthController@login` |
| POST | `/admin/login` | `Admin\AuthController@authenticate` |
| POST | `/admin/logout` | `Admin\AuthController@logout` |
| GET | `/admin` (dashboard) | `Admin\DashboardController@index` |
| GET | `/admin/noticias` | `Admin\NewsController@index` |
| GET | `/admin/noticias/crear` | `Admin\NewsController@create` |
| POST | `/admin/noticias` | `Admin\NewsController@store` |
| GET | `/admin/noticias/{id}/editar` | `Admin\NewsController@edit` |
| PUT | `/admin/noticias/{id}` | `Admin\NewsController@update` |
| DELETE | `/admin/noticias/{id}` | `Admin\NewsController@destroy` |
| ... | CRUD similar para categorías, servicios, usuarios, entes, páginas, medios, configuración | |

---

## 9. CONSIDERACIONES TÉCNICAS

### 9.1 SEO
- Slugs amigables para noticias y categorías
- Meta tags dinámicos (title, description, og:image)
- Sitemap.xml generado automáticamente
- URLs limpias (sin `?id=...`)

### 9.2 Rendimiento
- Imágenes optimizadas (WebP con fallback a JPG/PNG)
- Lazy loading en imágenes del grid de noticias
- Cache de consultas pesadas
- Paginación en lugar de carga infinita

### 9.3 Seguridad
- Autenticación con Laravel Breeze o Fortify (sin Livewire)
- CSRF Protection
- Roles y permisos por middleware
- Validación con Form Requests
- SQL Injection prevenido por Eloquent ORM
- XSS escapado por Blade
- Upload de imágenes con validación de tipo y tamaño

### 9.4 Editor de Contenido
- WYSIWYG para contenido de noticias y páginas
- Opciones: **TinyMCE** (recomendado), CKEditor 5, o Trix
- Sin Livewire — el editor debe funcionar con JavaScript vanilla

---

## 10. POSIBLES MEJORAS FUTURAS (FUERA DEL ALCANCE INICIAL)

- [ ] Notificaciones push
- [ ] Newsletter / suscripción por correo
- [ ] Estadísticas avanzadas con gráficos
- [ ] Versión en inglés
- [ ] API REST para consumo móvil
- [ ] Comentarios en noticias
- [ ] Galería de imágenes/álbumes
- [ ] Eventos y agenda

---

## 11. FLUJO DE TRABAJO RECOMENDADO

```
1. Crear proyecto Laravel 10
2. Configurar Vite con Bootstrap 5 SCSS
3. Definir layout Blade público
4. Definir layout Blade admin
5. Migraciones y seeders
6. CRUD de noticias (admin)
7. CRUD de categorías (admin)
8. Página de inicio pública
9. Página de listado de noticias
10. Página de artículo individual
11. Páginas estáticas (quienes-somos, contacto)
12. Gestión de servicios
13. Gestión de entes afiliados (footer)
14. Autenticación y roles
15. Gestor de medios
16. Configuración del sitio
17. Pruebas y ajustes finales
18. Despliegue
```
