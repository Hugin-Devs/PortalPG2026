# Portal2026 — Portal institucional GDC

Portal web institucional del **Gobierno de Distrito Capital (GDC)**: sitio informativo público (noticias, entes afiliados, gacetas oficiales, contacto) con panel administrativo para la gestión de contenidos.

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 10 (PHP 8.3+), Blade |
| Frontend | Bootstrap 5.3 + Sass, Vite, JavaScript vanilla |
| Base de datos | PostgreSQL 14+ (probado con 18.6) |
| Editor | TinyMCE 8 auto-hospedado (GPL), Font Awesome y fuentes locales (funciona offline) |

Sin Livewire, Vue, React ni jQuery.

## Documentación

Toda la planificación y los entregables viven en **`docs/`**:

- **[docs/DOCUMENTACION.md](docs/DOCUMENTACION.md)** — fuente de verdad del proyecto: esquema de BD, rutas, controladores, vistas, roles y permisos, seeders, **checklist de despliegue en producción (§14)** y tests (§16).
- **[docs/PENDIENTES.md](docs/PENDIENTES.md)** — checklist vivo de trabajo abierto (incl. pre-release R1–R4).
- **[SECURITY.md](SECURITY.md)** (raíz) — riesgos heredados, waivers y **condiciones obligatorias de despliegue**.
- **[docs/INFORME-VULNERABILIDADES.md](docs/INFORME-VULNERABILIDADES.md)** — informe de análisis de vulnerabilidades y estado de remediación.
- **[docs/SPECS.md](docs/SPECS.md)** — requisitos originales (julio 2026). Documento **histórico**: prevalece `docs/DOCUMENTACION.md`.

## Requisitos de desarrollo

PHP 8.3+ (probado con 8.5; PHP 8.1/8.2 EOL, no admitidos), Composer, Node.js 18+, PostgreSQL 14+ con la extensión PHP `pdo_pgsql`. Guía completa en `docs/DOCUMENTACION.md` §13.

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
- Panel: `http://127.0.0.1:8000/admin/login` — usuarios seed y roles en `docs/DOCUMENTACION.md` §9 (password por defecto: `password`).

> **Seguridad:** `SESSION_SECURE_COOKIE` se activa por defecto cuando `APP_ENV` no es `local` (`config/session.php`). En desarrollo HTTP la cookie se envía sin flag `Secure`. **En producción es obligatorio HTTPS**; la clave `SESSION_SECURE_COOKIE` en `.env` permite forzar `true`/`false` de forma explícita.

## Tests

```bash
./vendor/bin/phpunit          # Unit + Feature (189 tests, BD sqlite en memoria — ver docs/DOCUMENTACION.md §16)
composer lint                 # estilo de código (pint --test); composer lint:fix lo aplica
```

Suites E2E en `tests/e2e/` (bash + psql) contra un servidor de desarrollo local; se ejecutan con `bash tests/e2e/run-all.sh` (ver §16 de `docs/DOCUMENTACION.md`).

## Despliegue

Checklist completo para el servidor institucional (Nginx/Apache, FTP + SSH) en **`docs/DOCUMENTACION.md` §14** y las condiciones de seguridad en **[SECURITY.md](SECURITY.md) §1**. Puntos clave: copiar la plantilla **`.env_example_produccion`** a `.env` y completar valores reales (`APP_DEBUG=false`), `npm run build` (los assets no están en git), `php artisan storage:link` (requiere SSH) y abrir **`/install.php`** en el primer arranque (formulario one-shot que crea el superadmin/admin reales, borra los usuarios seed y se auto-elimina).
