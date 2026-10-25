# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar un ítem.
> El registro histórico de cierres (fechas y notas de todo lo completado) vive en
> [historico-cierres-pendientes.md](historico-cierres-pendientes.md).
> Última actualización: 2026-10-25 (cierre de R1–R3)

**Abiertos: 3** — 2 de contenido del cliente + 1 de pre-release.

---

## A. Contenido / decisiones del cliente

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)

## B. Pre-release (bloquean la publicación)

- [ ] **R4 — Verificar las 7 condiciones de despliegue en el servidor**  
  SECURITY.md §1: `install.php` eliminado del artefacto, `TRUSTED_PROXIES`/`TRUSTED_HOSTS`, `DB_SSLMODE`, php.ini de FPM, bloqueo de scripts en `/storage` **+ `nosniff` en estáticos** (Nginx), cron `schedule:run`, HSTS a nivel de proxy.

### Cerrados (2026-10-25)

- [x] **R1 — Suite E2E completa** → **17/17 verdes** (incluidos los preexistentes `test-entes`/`test-share`; fixes de entorno en `tests/e2e/lib.sh`: locale, CRLF de psql, wrapper curl rutas MSYS, timezone Caracas).
- [x] **R2 — Baseline DAST** → **OWASP ZAP 2.17.0** contra `127.0.0.1:8000`: 0 altas / 1 media (waiver TinyMCE) / 2 bajas / 4 informativas. Informes en `docs/zap-baseline-2026-10-25.{html,json}`; fixes: cookie XSRF `HttpOnly` + `X-Powered-By` fuera. *DAST autenticado queda como mejora.*
- [x] **R3 — Cobertura** → Xdebug 3.5.3 + **82.22% líneas** (≥80%); tests nuevos Partner/Contact/Settings + regresiones críticas.

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
