# Plan de migración a Laravel 13 — Portal PG2026

> **Rama:** `Laravel13-Dev` · **Destino:** `laravel/framework ^13.10.1` (>13.10.0, lock fijará 13.38.x)
> **Estado:** en curso (Fase 0) · **Autorización:** waiver institucional SC-02/A5 levantado (institución aprueba el upgrade; cierre documental pendiente en Fase 4)

---

## 0. Contexto y objetivo

| Dato | Valor |
|---|---|
| Origen | Laravel **10.50.2** (EOL 4-feb-2025), PHP `^8.3` |
| Destino | Laravel **≥13.10.0** (constraint `^13.10.1`; última estable verificada: **v13.38.0**) |
| PHP | **`^8.3` sin cambio** (prod PHP-FPM 8.3.35 compatible con Laravel 13) |
| Base de datos | PostgreSQL 13.23, `server_encoding LATIN9`, conexión UTF8 (pipeline LATIN9 propio) |
| Red de seguridad | 237 tests PHPUnit (82% cobertura) + 17 suites E2E + Pint + `composer audit` |
| Ruta oficial | **10 → 11 → 12 → 13**, un major por fase, gates en verde antes de avanzar |

**Por qué >13.10.0:** el parche de **CVE-2026-48019** (inyección CRLF en validación de email, severidad 8.9) está en 12.60.0 / 13.10.0. Exigir `^13.10.1` garantiza versiones estrictamente superiores a 13.10.0 y el `composer.lock` fijará la última estable (13.38.x).

**Fuentes verificadas (2026-10-26):** `laravel/framework` 13.x (composer.json + tags GitHub), guía oficial `laravel.com/docs/13.x/upgrade`, esqueleto `laravel/laravel` 13.x, `laravel/sanctum` 4.x, `spatie/laravel-ignition` main.

---

## 1. Decisiones adoptadas

| # | Decisión | Motivo |
|---|---|---|
| D1 | Waiver SC-02/A5 **ya autorizado** por la institución | El plan no incluye trámite de gobernanza previo; solo cierre documental al final |
| D2 | **Eliminar `laravel/sanctum`** | Cero uso (sin rutas API ni `auth:sanctum`); menos superficie de auditoría; se puede re-añadir como Sanctum 4/5 si hay API futura |
| D3 | Adoptar **`session.serialization = json`** en Laravel 13 | Endurecimiento anti-deserialización ya; los usuarios re-loguean al desplegar (documentado en runbook) |
| D4 | **Conservar la config publicada** estilo L10 en `config/` (18 ficheros) | Menor riesgo que adoptar el skeleton mínimo L11+; solo se ajusten los renames imprescindibles |
| D5 | PHP se queda en **`^8.3`** | Prod ya está en 8.3.35; `SecurityConfigTest` congela `^8.3`; sin coste de runtime |
| D6 | Eliminar `doctrine/dbal` y `guzzlehttp/guzzle` como dependencias directas | 0 usos en `app/`; `->change()` nativo desde L11; el framework trae Guzzle interno |
| D7 | **Sin Laravel Shift/Boost** (herramientas opcionales) | Migración manual guiada por este plan; si se desea acelerar, Boost MCP (`/upgrade-laravel-v13`) es compatible con opencode |

---

## 2. Fase 0 — Preparación ✅ (esta sesión)

- [x] Crear rama `Laravel13-Dev` desde `main`
- [x] Verificar baseline en `main`: Pint ✅, 237 tests ✅, 17/17 E2E ✅, `composer audit` 0 activos
- [x] Escribir este plan en `docs/PLAN-LARAVEL13.md` (commit: `docs: plan de migración a Laravel 13`)

**Gate de salida:** rama creada, plan versionado, baseline documentado.

---

## 3. Fase 1 — Laravel 10 → 11 (riesgo ALTO)

### 3.1 composer.json

| Paquete | De | A | Nota |
|---|---|---|---|
| `laravel/framework` | `^10.0` | `^11.57` | |
| `php` | `^8.3` | `^8.3` | sin cambio |
| `doctrine/dbal` | `^3.10` | **ELIMINAR** | 0 usos; `->change()` nativo en L11 |
| `guzzlehttp/guzzle` | `^7.2` | **ELIMINAR** | 0 usos en `app/` |
| `laravel/sanctum` | `^3.2` | **ELIMINAR** | decisión D2 |
| `laravel/tinker` | `^2.8` | `^2.12` | subirá a `^3.0` en Fase 3 |
| `phpunit/phpunit` | `^10.0` | `^11.5` | schema `phpunit.xml` a 11 |
| `nunomaduro/collision` | `^7.0` | `^8.x` | |
| `spatie/laravel-ignition` | `^2.0` | `^2.x` latest | soporta illuminate `^11` |
| `mews/purifier` | `^3.4` | `^3.4` | soporta hasta `^13` ✅ |
| `laravel/pint`, `laravel/sail`, `fakerphp/faker`, `mockery/mockery` | — | latest | |

### 3.2 Eliminación de Sanctum (D2)
- [ ] Quitar `laravel/sanctum` de `composer.json`
- [ ] Quitar `HasApiTokens` de `app/Models/User.php`
- [ ] Eliminar `config/sanctum.php`
- [ ] Nueva migración `drop_personal_access_tokens` (limpieza de la tabla en prod; la migración histórica `create_personal_access_tokens` se conserva inerte en el repo)
- [ ] Buscar referencias residuales (`sanctum`, `auth:sanctum`, `Sanctum::`) — debe quedar 0

### 3.3 Estructura de arranque (el trabajo grande)
- [ ] Reescribir `bootstrap/app.php` con `Application::configure(basePath:)` + `->withRouting()` (web → `routes/web.php`, commands → `routes/console.php`, channels → `routes/channels.php`) + `->withMiddleware()` + `->withExceptions()` + `->withCommands()` + scheduling
- [ ] **Eliminar** `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`
- [ ] Trasladar a `withMiddleware()`:
  - Globales: `TrimStrings` (propio), `SanitizeLatin9Input` (propio), `ConvertEmptyStringsToNull`, `AddSecurityHeaders` (propio, CSP+nonce), `AssignRequestId` (propio), + los framework (`TrustHosts`, `TrustProxies`, `HandleCors`, `PreventRequestsDuringMaintenance`, `ValidatePostSize`)
  - Aliases propios: `role`, `permission`, `password.changed`
- [ ] Trasladar los 4 rate limiters de `RouteServiceProvider` (`login`, `contact`, `search`, `password-change`) → `bootstrap/app.php` / `AppServiceProvider`
- [ ] Trasladar el schedule (`contacts:prune` 03:17, `audit:prune` 04:23) de `app/Console/Kernel.php` → `routes/console.php`
- [ ] Carga de comandos: `app/Console/Commands` vía `->withCommands()`
- [ ] Eliminar `AuthServiceProvider` (policies vacías) y `EventServiceProvider` (los 6 observers → `AppServiceProvider::boot()` con `Model::observe()`; verificar si el listener default `Registered → SendEmailVerificationNotification` aplica — si no hay flujo de verificación de email, eliminarlo)
- [ ] Corregir `RouteServiceProvider::HOME = '/home'` (ruta inexistente): `RedirectIfAuthenticated` → redirigir a `/admin`
- [ ] Actualizar `public/index.php` al estilo L11+; revalidar `public/install.php` (boostra la app fuera del ciclo HTTP normal y se autodeletea)

### 3.4 Ajustes menores
- [ ] `phpunit.xml`: schema PHPUnit 11; `<env>` names se mantienen (config propia lee `CACHE_DRIVER` etc.)
- [ ] Accessores legacy `getXxxAttribute` (`Video`, `SanitizesHtml`): **no migrar** en esta fase (siguen soportados); anotar como deuda para L13+
- [ ] `$casts` como propiedad (11 modelos): **no migrar** a `casts()` en esta fase

### 3.5 Gates de salida (Fase 1)
- [ ] `composer lint` (Pint) ✅
- [ ] 237+ tests PHPUnit en verde (ajustar lo que rompa PHPUnit 11 — estilo `test_*` es port directo)
- [ ] 17/17 suites E2E en verde (idealmente contra pgsql LATIN9 real, no solo SQLite)
- [ ] `composer audit` 0 activos
- [ ] Smoke test en staging: arranque + login + escritura + cron `schedule:run`
- [ ] Regresión LATIN9 forzada: `Latin9EncodingTest`, `PiiEncryptionTest`, `PgMigrationQueriesTest`

---

## 4. Fase 2 — Laravel 11 → 12 (riesgo BAJO)

- [ ] `laravel/framework` → `^12` (última 12.69.x)
- [ ] Bump de dependencias: Symfony `^7.2`, Carbon `^3.7`, `phpunit` puede quedarse en `^11.5` o subir; collision/ignition/tinker según soporte
- [ ] Revisar guía oficial 11→12 en el momento (pocos breaking changes: firmas de métodos y dependencias)
- [ ] Mantener `^8.3` en PHP

**Gates de salida:** mismos que Fase 1 (Pint + tests + E2E + audit + smoke).

---

## 5. Fase 3 — Laravel 12 → 13 (riesgo MEDIO) — objetivo >13.10.0

### 5.1 composer.json
| Paquete | A | Nota |
|---|---|---|
| `laravel/framework` | **`^13.10.1`** | estrico >13.10.0; lock fijará 13.38.x |
| `laravel/tinker` | `^3.0` | requisito oficial L13 |
| `phpunit/phpunit` | `^12.5` | schema `phpunit.xml` a 12 |
| `nunomaduro/collision` | `^8.6` | skeleton oficial L13 |
| `laravel/pint` | `^1.27` | |
| `mews/purifier` | `^3.4` | ✅ compatible `^13` |
| `spatie/laravel-ignition` | latest | ✅ compatible `^13` |

### 5.2 Cambios de código obligatorios (guía oficial 12→13)
- [ ] **CSRF:** renombrar el middleware propio `app/Http/Middleware/VerifyCsrfToken.php` (override de `newCookie` para el fix `HttpOnly` del XSRF-TOKEN) para basarse en **`PreventRequestForgery`** (nuevo nombre del CSRF middleware, con validación de origen vía `Sec-Fetch-Site`); actualizar referencias/aliases (`preventRequestForgery(...)` en `withMiddleware()`)
- [ ] **E2E curl:** las 17 suites usan curl — validar que envían `Origin`/`Sec-Fetch-Site` cuando haga falta; ajustar `tests/e2e/*.sh` si el middleware rechaza peticiones sin cabeceras de origen
- [ ] `session.serialization = json` en `config/session.php` (decisión D3 — re-login de usuarios al desplegar)
- [ ] `cache.serializable_classes = false` por defecto: auditar qué se cachea (`CacheKeys`, observers que flush-ean); si hay objetos en cache → allowlist explícita o migrar a arrays
- [ ] Prefijos de cache/sesión con guiones: tu `config/cache.php`/`config/session.php` fijan valores explícitos → sin cambio; verificarlo
- [ ] Polyfill `symfony/polyfill-php85`: comprobar que no existen helpers globales propios `array_first()`/`array_last()` (0 esperados; usar `Arr::first()` si acaso)
- [ ] `upsert` exige `uniqueBy` no vacío (0 usos esperados — grep)
- [ ] Nombres de vistas de paginación Bootstrap 3 renombrados: usas `Paginator::useBootstrapFive()` → sin impacto
- [ ] `Container::call` con defaults anulables, `Str` factories reset entre tests, `Js::from` unicode: revisar si algún test depende de esto

### 5.3 Gates de salida (Fase 3)
- [ ] Pint ✅ · 237+ tests ✅ · 17/17 E2E ✅ · `composer audit` 0 ✅
- [ ] Suite LATIN9 + `PiiEncryptionTest` en verde contra pgsql real
- [ ] Smoke en staging: login, admin, upload, contact, search, cron
- [ ] Baseline ZAP repetido (comparar con `docs/zap-baseline-2026-10-25.*`)

---

## 6. Fase 4 — Cierre, hardening y release

- [ ] `SECURITY.md`: cerrar SC-02/A5 (upgrade ejecutado), retirar compensatorios EOL de Laravel 10, actualizar inventario de dependencias con estado de soporte, retirar waivers obsoletos
- [ ] `docs/PENDIENTES.md`: marcar A5/SC-02 como ✅
- [ ] `docs/DOCUMENTACION.md` §14: runbook actualizado (`composer install --no-dev` con lock, `optimize`, nota de re-login por `session.serialization=json`, cron, vhost)
- [ ] `docs/INFORME-VULNERABILIDADES.md`: nota de cierre de SC-02
- [ ] Regenerar SBOM + `composer audit` + `npm audit`
- [ ] Cobertura ≥82% mantenida
- [ ] Despliegue staging (pgsql LATIN9 real) → smoke → producción con rollback por SHA
- [ ] PR `Laravel13-Dev` → `main`

---

## 7. Matriz de riesgos

| Riesgo | Impacto | Mitigación (fase) |
|---|---|---|
| Pipeline LATIN9 (`setAttribute()` override + middleware global + casts `encrypted`) atraviesa puntos internos del ORM que cambian entre majors | Alto | Regresionar `Latin9EncodingTest`/`PiiEncryptionTest`/`PgMigrationQueriesTest` en **cada** salto, contra pgsql real |
| Override de `VerifyCsrfToken::newCookie()` (fix HttpOnly XSRF-TOKEN) | Alto | Verificar API en `PreventRequestForgery` de L13; re-ejecutar test de headers |
| Suites E2E en curl vs validación de origen del CSRF nuevo | Medio | Probar E2E temprano en Fase 3; ajustar cabeceras `Origin`/`Sec-Fetch-Site` |
| Salto PHPUnit 10 → 11 → 12 (schema, deprecaciones) | Medio | Tests estilo `test_*` estáticos = port directo; actualizar schema por fase |
| `public/install.php` (one-shot installer) tras cambiar `bootstrap/app.php` | Medio | Re-probar instalación desde cero en staging tras Fase 1 |
| `SecurityConfigTest` congela `php: ^8.3` | Bajo | D5 mantiene `^8.3` → sin cambio |
| Providers legacy (rate limiters, observers, HOME) mal trasladados | Medio | Checklist §3.3 + tests de rate limiting (`throttle:password-change` etc.) |
| PostgreSQL 13.23 llega a EOL | Bajo (paralelo) | No bloquea Laravel; planificar upgrade de BD aparte |

---

## 8. Criterios de aceptación globales (verificables)

1. `composer.json` exige `laravel/framework: ^13.10.1` y el lock resuelve a ≥13.10.1 (verificable con `composer show laravel/framework`)
2. Ningún componente EOL en `composer audit` (0 advisories activos)
3. 237+ tests PHPUnit en verde + cobertura ≥82%
4. 17/17 suites E2E en verde
5. Pint en verde
6. Smoke test de despliegue documentado y ejecutado en staging con pgsql LATIN9
7. `SECURITY.md` sin waivers Laravel 10 activos; SC-02 cerrado
8. Runbook de despliegue y rollback actualizados en `DOCUMENTACION.md` §14
