# Portal2026 — Portal institucional GDC

Portal web institucional del **Gobierno de Distrito Capital (GDC)**: sitio informativo público (noticias, entes afiliados, gacetas oficiales, contacto) con panel administrativo para la gestión de contenidos.

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13 (PHP 8.3+), Blade — producción aún en Laravel 10 hasta el despliegue del upgrade (U1) |
| Frontend | Bootstrap 5.3 + Sass, Vite, JavaScript vanilla |
| Base de datos | PostgreSQL 14+ recomendado (probado con 18.6). Producción: **13.23 / LATIN9** con conexión UTF8 |
| Editor | TinyMCE 8 auto-hospedado (GPL), Font Awesome y fuentes locales (funciona offline) |

Sin Livewire, Vue, React ni jQuery.

## Documentación

Toda la planificación y los entregables viven en **`docs/`**:

- **[docs/DOCUMENTACION.md](docs/DOCUMENTACION.md)** — fuente de verdad del proyecto: esquema de BD, rutas, controladores, vistas, roles y permisos, seeders, **checklist de despliegue en producción (§14)** y tests (§16).
- **[docs/PENDIENTES.md](docs/PENDIENTES.md)** — checklist vivo de trabajo abierto (R1–R4 cerrados el 2026-10-25 con el despliegue en producción; abiertos: contenido del cliente D6/E1, los bloqueantes R5 TLS / R6 backup de BD y U1 = despliegue del upgrade a Laravel 13).
- **[SECURITY.md](SECURITY.md)** (raíz) — riesgos heredados, waivers y **condiciones obligatorias de despliegue**.
- **[docs/INFORME-VULNERABILIDADES.md](docs/INFORME-VULNERABILIDADES.md)** — informe de análisis de vulnerabilidades y estado de remediación.
- **[docs/informe-estado-actual.html](docs/informe-estado-actual.html)** — **entregable imprimible** (instantánea del estado actual, tamaño carta); PDF con `scripts/export-informe.ps1`.
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
./vendor/bin/phpunit          # Unit + Feature (291 en verde / 301 totales, BD sqlite en memoria — ver docs/DOCUMENTACION.md §16)
./vendor/bin/phpunit --coverage-text   # cobertura (requiere Xdebug; 97.58% actual)
composer lint                 # estilo de código (pint --test); composer lint:fix lo aplica
```

Suites E2E en `tests/e2e/` (bash + psql) contra un servidor de desarrollo local; se ejecutan con `bash tests/e2e/run-all.sh` (17/17 en verde — ver §16 de `docs/DOCUMENTACION.md`).
Baseline DAST con OWASP ZAP: `scripts/zap-baseline.yaml` (referencias en `docs/zap-baseline-2026-10-25.html` y `docs/zap-baseline-laravel13-2026-10-26.html`).

## Despliegue

**Estado: desplegado en producción el 2026-10-25** en `https://web.gdc.gob.ve` (Debian 12 + Nginx + PHP-FPM 8.3 + PostgreSQL LATIN9), con `origin/main` = `8e06f82` y las **7 condiciones de [SECURITY.md](SECURITY.md) §1 verificadas 7/7**.

Checklist completo del servidor (vhost, `.env`, orden de arranque, actualizaciones posteriores, cron y registro del despliegue real) en **`docs/DOCUMENTACION.md` §14** (el detalle del despliegue está en **§14.L**). Puntos clave:

- Plantilla **`.env_example_produccion`** → `.env` en el servidor (`chmod 600`, `APP_DEBUG=false`, `TRUSTED_HOSTS=<dominio>`, `DB_SSLMODE=require`); **nunca** copiar el `.env` de desarrollo ni regenerar `APP_KEY` después del primer seed.
- Actualización típica: `git fetch && git reset --hard origin/main` → `rm -f public/install.php` → `composer install --no-dev` → `npm ci && npm run build` → `optimize:clear` → `migrate --force` → `optimize` (§14.G). **Nunca** un `db:seed` completo en producción.
- Obligatorios: `storage:link` (el enlace debe apuntar al `storage/` de la misma instalación), cron `* * * * * www-data php .../artisan schedule:run` y las 7 condiciones de SECURITY.md §1.
- Abiertos antes de la salida a internet: **R5 certificado TLS** (el actual venció el 2026-06-19) y **R6 copia de seguridad de la BD**.
