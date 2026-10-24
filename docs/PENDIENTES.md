# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar un ítem.
> El registro histórico de cierres (fechas y notas de todo lo completado) vive en
> [historico-cierres-pendientes.md](historico-cierres-pendientes.md).
> Última actualización: 2026-10-23 (reorganización a `docs/`)

**Abiertos: 6** — 2 de contenido del cliente + 4 de pre-release.

---

## A. Contenido / decisiones del cliente

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)

## B. Pre-release (bloquean la publicación)

- [ ] **R1 — Ejecutar la suite E2E completa**  
  `bash tests/e2e/run-all.sh` (17 suites, requiere PostgreSQL local). Hay **2 fallos preexistentes documentados** (`test-entes`, `test-share`) que deben arreglarse o justificarse antes del release.

- [ ] **R2 — Baseline DAST contra staging**  
  OWASP ZAP/Burp contra staging con credenciales reales; waiver "Sin SAST/DAST automatizados" registrado en SECURITY.md §2.

- [ ] **R3 — Medir cobertura de código**  
  Driver Xdebug/PCOV + `php artisan test --coverage`; objetivo ≥80% en lógica de negocio (waiver "Cobertura no medida" en SECURITY.md §2).

- [ ] **R4 — Verificar las 7 condiciones de despliegue en el servidor**  
  SECURITY.md §1: `install.php` eliminado del artefacto, `TRUSTED_PROXIES`/`TRUSTED_HOSTS`, `DB_SSLMODE`, php.ini de FPM, bloqueo de scripts en `/storage` (Nginx), cron `schedule:run`, HSTS a nivel de proxy.

## C. Diferidos / fuera de alcance (no se van a hacer)

- **A5 — Upgrade de Laravel 11/12** — descartado: es **requisito de la institución** que el portal sea Laravel 10 (pese a EOL feb 2026); se mantiene en 10.x. *Revisar con el presupuesto de mantenimiento (SECURITY.md §2, 2026-11-21).*
- **C1 — CI con GitHub Actions** — descartado: no se utilizará GitHub Actions.
- **D2 — CRUD de páginas estáticas en el admin** — archivado: **sin instrucción de que se vaya a necesitar**; `/pagina/{slug}` queda tal cual (vista `page.blade.php` lista, tabla `pages` vacía).
- **D5 — Front de la página de detalle de entes (`/entes/{slug}`)** — archivado: la vista actual es funcional (skeleton); se retoma solo con nueva instrucción de diseño.
- **E4 — Definir diseño del detalle de entes** — archivado junto con D5.
- **Tags en noticias** — omitido por decisión (SPECS §5.4); si se retoma: columna `posts.tags` + chips.
- **Mejoras futuras** (SPECS §10): newsletter, API REST, comentarios, galería, eventos, versión en inglés.

## D. Hallazgos del análisis de vulnerabilidades 2026

> G1–G8 **cerrados** (2026-10-08 … 2026-10-23). Detalle de remediación en
> [INFORME-VULNERABILIDADES.md](INFORME-VULNERABILIDADES.md) y fechas de cierre en
> [historico-cierres-pendientes.md](historico-cierres-pendientes.md).

---

## Registro de cierre

El registro de cierres se trasladó el 2026-10-23 a
[historico-cierres-pendientes.md](historico-cierres-pendientes.md) para que
este checklist contenga solo trabajo abierto. Al cerrar un ítem de este
fichero: marcarlo `[x]` y añadir fecha + nota breve al histórico.
