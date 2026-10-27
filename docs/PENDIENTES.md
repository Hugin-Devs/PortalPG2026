# PENDIENTES — Checklist de trabajo

> **Fuente viva del trabajo pendiente.** Ir marcando `- [x]` al completar un ítem.
> El registro histórico de cierres (fechas y notas de todo lo completado) vive en
> [historico-cierres-pendientes.md](historico-cierres-pendientes.md).
> Última actualización: 2026-10-26 (despliegue en producción, cierre de R4 y **migración a Laravel 13.38.0** en rama `Laravel13-Dev`)

**Abiertos: 5** — 2 de contenido del cliente + 2 de bloqueo de salida (TLS y backup de BD) + 1 de release (upgrade).
*Validación local Laravel 13 completada **y re-validada + endurecida en 2026-10-26** (Pint · **291/301 tests · 1325 aserciones · cobertura 97.58%** · gates UTF-8 y LATIN9 301 c/u · E2E 17/17 · **ZAP final 0/0/1/4**, waiver `style-src` cerrado) — `docs/PLAN-LARAVEL13.md` §6.2.*

---

## A. Contenido / decisiones del cliente

- [ ] **D6 — Contenido del `PartnerSeeder`**  
  Array vacío; requiere el listado real de entes del cliente.

- [ ] **E1 — Listado de entes afiliados** (bloquea D6)

## B. Pre-release (bloquean la publicación)

- [ ] **R5 — Certificado TLS del dominio**  
  El certificado actual es auto-firmado con `CN=<IP>` y **venció el 2026-06-19**: el navegador rechaza la conexión a `https://web.gdc.gob.ve` (desde consola funciona con `-k`). Hace falta un certificado institucional o CA interna con SAN del dominio (Let's Encrypt no aplica: DNS interno, no hay puerto 80 público). Detalle en DOCUMENTACION.md §14.L.

- [ ] **R6 — Copia de seguridad de la BD**  
  No hay cliente PostgreSQL (`psql`/`pg_dump`) en el servidor ⇒ no existe dump de `portalweb`. El despliegue del 2026-10-25 se hizo con backup de `.env`, `storage/` y assets pero **sin dump de BD** (aceptado explícitamente). Instalar `postgresql-client` y dejar un `pg_dump` diario + verificación de restauración.

- [ ] **U1 — Despliegue del upgrade a Laravel 13**  
  PR `Laravel13-Dev` → `main` (gates todos en verde: ver `docs/PLAN-LARAVEL13.md` §5.3/§6.2), despliegue con rollback por SHA y smoke de las 7 condiciones (SECURITY.md §1 / DOCUMENTACION §14) repetido tras el upgrade.

### Cerrados (2026-10-25)

- [x] **R4 — Verificar las 7 condiciones de despliegue en el servidor** → **7/7 verificadas el 2026-10-25** durante el despliegue real (DOCUMENTACION.md §14.L): `install.php` ausente, `TRUSTED_HOSTS=web.gdc.gob.ve` / `TRUSTED_PROXIES` vacío, `DB_SSLMODE=require`, php.ini FPM 100M/110M/256M/60 + `expose_php=Off`, bloqueo de scripts en `/storage` (403) + `nosniff` en `/build`,`/storage`,`/images`, cron `/etc/cron.d/portal` con `schedule:run`, HSTS emitido por app y Nginx.
- [x] **R1 — Suite E2E completa** → **17/17 verdes** (incluidos los preexistentes `test-entes`/`test-share`; fixes de entorno en `tests/e2e/lib.sh`: locale, CRLF de psql, wrapper curl rutas MSYS, timezone Caracas).
- [x] **R2 — Baseline DAST** → **OWASP ZAP 2.17.0** contra `127.0.0.1:8000`: 0 altas / 1 media (waiver TinyMCE) / 2 bajas / 4 informativas. Informes en `docs/zap-baseline-2026-10-25.{html,json}`; fixes: cookie XSRF `HttpOnly` + `X-Powered-By` fuera. *DAST autenticado queda como mejora.*
- [x] **R3 — Cobertura** → Xdebug 3.5.3 + **82.22% líneas** (≥80%) en Laravel 10; re-validado en Laravel 13 el 2026-10-26 con **82.25%**; tests nuevos Partner/Contact/Settings + regresiones críticas.

## C. Diferidos / fuera de alcance (no se van a hacer)

- ~~**A5 — Upgrade de Laravel 11/12**~~ **Cerrado 2026-10-26**: el requisito institucional de permanecer en Laravel 10 fue levantado (waiver SC-02); migración completa **10 → 11 → 12 → 13.38.0** en la rama `Laravel13-Dev` según `docs/PLAN-LARAVEL13.md` (Fases 0–3). *Validación completada 2026-10-26 incluyendo ZAP (gates en §6.2); solo queda el despliegue del upgrade (U1, Fase 4).*
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
