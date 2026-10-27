# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar un ítem.
> Última actualización: 2026-10-26.

**Abiertos: 5** — 2 de contenido del cliente + 2 de bloqueo de salida (TLS y backup de BD) + 1 de release (upgrade).
*Estado de la rama `Laravel13-Dev` (2026-10-26): Pint ✅ · **291/301 tests · 1325 aserciones · cobertura 97.58%** · gates UTF-8 y LATIN9 301 c/u · E2E 17/17 · **ZAP final 0/0/1/4**, waiver `style-src` cerrado — ver `docs/PLAN-LARAVEL13.md` §6.2.*

---

## A. Contenido / decisiones del cliente

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)

- [ ] **E5 — Reactivar los enlaces Entes**  
  Ocultos con comentario Blade el 2026-10-27 (`layouts/public.blade.php`: navbar, enlace del footer y bloque "Entes Afiliados") mientras un equipo externo prepara el módulo de entes. Las rutas `/entes` siguen vivas. Reactivar descomentando los 3 bloques y restaurando las aserciones de `test-entes.sh`/`test-categorias.sh`.

## B. Pre-release (bloquean la publicación / el release)

- [ ] **R5 — Certificado TLS del dominio**  
  El certificado actual es auto-firmado con `CN=<IP>` y **venció el 2026-06-19**: el navegador rechaza la conexión a `https://web.gdc.gob.ve` (desde consola funciona con `-k`). Hace falta un certificado institucional o CA interna con SAN del dominio (Let's Encrypt no aplica: DNS interno, no hay puerto 80 público). Detalle en DOCUMENTACION.md §14.L.

- [ ] **R6 — Copia de seguridad de la BD**  
  No hay cliente PostgreSQL (`psql`/`pg_dump`) en el servidor ⇒ no existe dump de `portalweb`. El despliegue del 2026-10-25 se hizo con backup de `.env`, `storage/` y assets pero **sin dump de BD** (aceptado explícitamente). Instalar `postgresql-client` y dejar un `pg_dump` diario + verificación de restauración.

- [ ] **U1 — Despliegue del upgrade a Laravel 13**  
  PR `Laravel13-Dev` → `main` (gates todos en verde: ver `docs/PLAN-LARAVEL13.md` §5.3/§6.2), despliegue con rollback por SHA y smoke de las 7 condiciones (SECURITY.md §1 / DOCUMENTACION §14) repetido tras el upgrade.

## C. Diferidos / fuera de alcance (no se van a hacer)

- **C1 — CI con GitHub Actions** — descartado: no se utilizará GitHub Actions.
- **D2 — CRUD de páginas estáticas en el admin** — archivado: **sin instrucción de que se vaya a necesitar**; `/pagina/{slug}` queda tal cual (vista `page.blade.php` lista, tabla `pages` vacía).
- **D5 — Front de la página de detalle de entes (`/entes/{slug}`)** — archivado: la vista actual es funcional (skeleton); se retoma solo con nueva instrucción de diseño.
- **E4 — Definir diseño del detalle de entes** — archivado junto con D5.
- **Tags en noticias** — omitido por decisión; si se retoma: columna `posts.tags` + chips.
- **Mejoras futuras**: newsletter, API REST, comentarios, galería, eventos, versión en inglés.
