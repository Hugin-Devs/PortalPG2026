# Portal2026 — Portal institucional GDC

Portal web institucional del **Gobierno de Distrito Capital (GDC)**: sitio informativo público (noticias, entes afiliados, gacetas oficiales, contacto) con panel administrativo para la gestión de contenidos.

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 10 (PHP 8.1+), Blade |
| Frontend | Bootstrap 5.3 + Sass, Vite, JavaScript vanilla |
| Base de datos | MySQL 8 / MariaDB 10+ |
| Editor | TinyMCE 8 auto-hospedado (GPL), Font Awesome y fuentes locales (funciona offline) |

Sin Livewire, Vue, React ni jQuery.

## Documentación

- **[DOCUMENTACION.md](DOCUMENTACION.md)** — fuente de verdad del proyecto: esquema de BD, rutas, controladores, vistas, roles y permisos, seeders, estado del desarrollo, **checklist de despliegue en producción (§14)** y tests (§16).
- **[SPECS.md](SPECS.md)** — requisitos originales (julio 2026). Documento **histórico**: puede diferir de lo implementado; prevalece `DOCUMENTACION.md`.

## Requisitos de desarrollo

PHP 8.1+ (probado con 8.3), Composer, Node.js 18+, MySQL 8 / MariaDB. En Windows se puede usar Laragon (guía completa en `DOCUMENTACION.md` §13).

## Inicio rápido

```bash
composer install
npm ci
cp .env.example .env          # luego: php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan storage:link
npm run build                 # genera public/build + public/vendor/tinymce
php artisan serve
```

- Sitio: `http://127.0.0.1:8000`
- Panel: `http://127.0.0.1:8000/admin/login` — usuarios seed y roles en `DOCUMENTACION.md` §9 (password por defecto: `password`).

## Tests

```bash
./vendor/bin/phpunit          # Unit + Feature (cobertura mínima, ver §16)
```

Suites E2E en `tests/e2e/` (PowerShell): solo Windows + Laragon, se ejecutan contra `http://portal2026.test` (ver §16).

## Despliegue

Checklist completo para el servidor institucional (Nginx/Apache, FTP + SSH) en **`DOCUMENTACION.md` §14**. Puntos clave: `.env` de producción con `APP_DEBUG=false`, `npm run build` (los assets no están en git), `php artisan storage:link` (requiere SSH) y cambiar las credenciales seed tras el primer arranque.
