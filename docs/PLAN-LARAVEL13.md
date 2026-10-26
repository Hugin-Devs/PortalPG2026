# Plan de migración a Laravel 13 — Portal PG2026

> **Rama:** `Laravel13-Dev` · **Destino:** `laravel/framework ^13.10.1` (>13.10.0, lock fijó **13.38.0**)
> **Estado:** Fases 0–3 completadas (2026-10-26). Pendiente Fase 4 (cierre documental + E2E/staging en servidor) · **Autorización:** waiver institucional SC-02/A5 levantado (institución aprueba el upgrade; cierre documental pendiente en Fase 4)

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

## 2. Fase 0 — Preparación ✅ (2026-10-26)

- [x] Crear rama `Laravel13-Dev` desde `main`
- [x] Verificar baseline en `main`: Pint ✅, 237 tests ✅, 17/17 E2E ✅, `composer audit` 0 activos
- [x] Escribir este plan en `docs/PLAN-LARAVEL13.md` (commit: `docs: plan de migración a Laravel 13`)

**Gate de salida:** rama creada, plan versionado, baseline documentado.

---

## 3. Fase 1 — Laravel 10 → 11 (riesgo ALTO) ✅ (2026-10-26, commit `f9df9d6`)

### 3.1 composer.json

| Paquete | De | A | Nota |
|---|---|---|---|
| `laravel/framework` | `^10.0` | **`^11.57`** | lock resolvió **v11.57.0** ✅ |
| `php` | `^8.3` | `^8.3` | sin cambio ✅ |
| `doctrine/dbal` | `^3.10` | **ELIMINAR** | ✅ hecho |
| `guzzlehttp/guzzle` | `^7.2` | **ELIMINAR** | ✅ (sigue como dependencia transitiva del framework) |
| `laravel/sanctum` | `^3.2` | **ELIMINAR** | ✅ decisión D2 |
| `laravel/tinker` | `^2.8` | `^2.12` | resolvió 2.x compatible; subirá a `^3.0` en Fase 3 |
| `phpunit/phpunit` | `^10.0` | **`^11.5`** | lock: 11.5.57 ✅ |
| `nunomaduro/collision` | `^7.0` | **`^8.0`** | lock: 8.9.5 ✅ |
| `spatie/laravel-ignition` | `^2.0` | `^2.x` latest | lock: 2.12.1 ✅ |
| `mews/purifier` | `^3.4` | `^3.4` | ✅ compatible |

### 3.2 Eliminación de Sanctum (D2) ✅
- [x] Quitar `laravel/sanctum` de `composer.json`
- [x] Quitar `HasApiTokens` de `app/Models/User.php`
- [x] Eliminar `config/sanctum.php` + ruta `sanctum/csrf-cookie` de `config/cors.php` (test actualizado)
- [x] Nueva migración `2026_10_26_100000_drop_personal_access_tokens_table` (up/down)
- [x] Búsqueda de referencias residuales → 0

### 3.3 Estructura de arranque ✅
- [x] `bootstrap/app.php` reescrito con `Application::configure()` + `withRouting` + `withMiddleware` + `withExceptions`
- [x] Eliminados `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`
- [x] Stack global trasladado (11 middleware, orden L10 preservado + `InvokeDeferredCallbacks`); `ValidateCsrfToken` → `VerifyCsrfToken` propio (fix HttpOnly XSRF); aliases `auth`/`guest`/`role`/`permission`/`password.changed`
- [x] 4 rate limiters → `AppServiceProvider::registerRateLimiters()`
- [x] Schedule (`contacts:prune` 03:17, `audit:prune` 04:23) → `routes/console.php` (verificado con `schedule:list`)
- [x] Comandos: autodescubrimiento de `app/Console/Commands` (via `withCommands()` por defecto)
- [x] Eliminados `AuthServiceProvider`, `EventServiceProvider`, `RouteServiceProvider`; 6 observers → `AppServiceProvider::registerObservers()`; `config/app.php` actualizado
- [x] `HOME` corregido: `RedirectIfAuthenticated` → `/admin` (ruta `/home` inexistente)
- [x] `public/index.php` estilo L11 (`handleRequest`); `public/install.php` sin cambios (patrón contract-compatible verificado)

### 3.4 Ajustes menores ✅
- [x] `phpunit.xml`: schema PHPUnit **11.5**
- [x] `@dataProvider` doc-comment → atributo `#[DataProvider]` en `OrgMemberPhotoTest` (pre-Fase 3)
- [x] Eliminado `App\Http\Middleware\EncryptCookies` (idéntico al framework)
- [x] Accessores legacy y `$casts`: no migrados (deuda anotada para Fase 3)

### 3.5 Gates de salida (Fase 1)
- [x] `composer lint` (Pint) ✅
- [x] **228 tests PHPUnit en verde** (1032 aserciones, 0 fallos); 3 skips entorno (pgsql gate ×1, symlink Windows ×1 + 1 relacionado); 6 notices `iconv` preexistentes en `Latin9Test` (Windows)
- [x] `composer audit` **0 activos** (5 waivers ya conocidos — se cierran en Fase 3 con ≥13.10.1)
- [x] Smoke local: `artisan about` → Laravel 11.57.0; `route:list` (≈70 rutas); `schedule:list` OK
- [ ] **17/17 suites E2E** — ⏳ pendiente: requieren `psql` + `artisan serve` (no disponibles en Windows; ejecutar en servidor/staging LATIN9)
- [ ] Smoke en staging con pgsql LATIN9 real (junto con las E2E)

> **Nota:** se creó `.env` local desde `.env.example` (no versionado) para poder correr la suite en esta máquina.

---

## 4. Fase 2 — Laravel 11 → 12 (riesgo BAJO) ✅ (2026-10-26, commit `42edfe3`)

- [x] `laravel/framework` → **`^12.0`** (lock: **v12.70.1**)
- [x] Revisión de guía oficial 11→12 contra el código: sin uso de `HasUuids`, `mergeIfMissing`, `Concurrency`, Blueprint manual ni `local` disk implícito; todas las reglas `image` llevan `mimes` explícito sin SVG → **0 breaking changes aplicables**
- [x] Carbon 3 ya presente (3.15.1) · Symfony 7.4 · PHPUnit se queda en `^11.5` ✅
- [x] **`audit.ignore` eliminado de `composer.json`**: 12.70.1 está fuera del rango de los 5 advisories (12.60.0/12.61.1/12.68.0/12.69.0) → `composer audit` reporta **0 advisories, 0 waivers** (los waivers EOL de Laravel 10 quedan formalmente obsoletos)

**Gates de salida:** Pint ✅ · **228 tests / 1032 aserciones en verde** ✅ · `composer audit --locked` **0 activos, 0 ignorados** ✅ · `schedule:list` OK ✅ · `artisan about` → 12.70.1 ✅
**Pendiente (compartido con Fase 1):** E2E 17/17 + smoke LATIN9 en servidor/staging.

---

## 5. Fase 3 — Laravel 12 → 13 (riesgo MEDIO) ✅ (2026-10-26, commit `3d151ea`) — **objetivo cumplido**

### 5.1 composer.json ✅
| Paquete | A | Resultado |
|---|---|---|
| `laravel/framework` | **`^13.10.1`** | lock: **v13.38.0** (estrictamente >13.10.0 ✓, incluye parches de CVE-2026-48019 y siguientes) |
| `laravel/tinker` | `^3.0` | lock: 3.0.2 ✅ |
| `phpunit/phpunit` | `^12.5` | lock: 12.5.40 (schema `phpunit.xml` a 12.5) ✅ |
| `nunomaduro/collision` | `^8.6` | lock: 8.9.5 ✅ |
| `mews/purifier` / `spatie/laravel-ignition` | sin cambio | compatibles `^13` ✅ |

### 5.2 Cambios de código (guía oficial 12→13) ✅
- [x] **CSRF**: `App\Http\Middleware\VerifyCsrfToken` → **`App\Http\Middleware\PreventRequestForgery`** (nueva base de L13, override de `newCookie` con HttpOnly preservado + `partitioned`); `bootstrap/app.php` reemplaza `PreventRequestForgery` del framework por la subclase propia
- [x] **Validación de origen L13**: verificado contra el código fuente — `Sec-Fetch-Site: same-origin` pasa sin token; **sin la cabecera (curl/E2E) cae a `tokensMatch` clásico** → las 17 suites E2E que envían `_token` siguen válidas (a confirmar en servidor)
- [x] `session.serialization = 'json'` en `config/session.php` (decisión D3; re-login al desplegar)
- [x] `cache.serializable_classes` = **allowlist explícita** en `config/cache.php` (no `false`: la app cachea Eloquent Collections/paginadores con modelos y Carbon). Clases: Eloquent Collection, LengthAwarePaginator, Carbon, DateTime, DateTimeZone, Post, Category, Video, Partner, Gaceta. Produce `__PHP_Incomplete_Class` silencioso si falta una → **test nuevo** `CacheTest::test_cache_serializable_classes_allowlist_roundtrips_cached_payloads` pasa los payloads reales por el store `file` (el de producción) y exige roundtrip íntegro
- [x] Prefijos de cache/sesión: la config publicada fija `CACHE_PREFIX` y `SESSION_COOKIE` explícitos → sin cambio de nombres
- [x] Polyfill-php85: sin helpers globales propios `array_first`/`array_last` (autoload sin `files`) ✓
- [x] `upsert` en `NewsController::daily_views` con `uniqueBy = ['day','post_id']` no vacío → cumple la validación nueva de L13 ✓
- [x] Paginación Bootstrap 5 (solo cambian vistas default→bootstrap-3, no usadas) ✓ · Sin rutas con `domain` ✓ · Sin `JobAttempted`/`QueueBusy` listeners ✓ · Sin `Container::call` con defaults de clase ✓
- [x] **Fix derivado de `session.serialization=json`**: `RateLimitTest::lockout_threshold_comes_from_config` leía `session('errors')->first()` tras `save()` (estado array); sus tests gemelos pasaban porque `assertSessionHasErrors` reinicia la sesión y re-marshaliza. Se añadió `assertSessionHasErrors('email')` (misma idiomática que AuthTest:47/RateLimitTest:46). Solo afecta a lecturas de tests post-request; la app/renderizado Blade ocurre durante la petición (sin impacto)

### 5.3 Gates de salida (Fase 3)
- [x] Pint ✅ · **229 tests / 1046 aserciones en verde** (228 previos + 1 nuevo allowlist) ✅
- [x] `composer audit --locked` → **0 advisories, 0 waivers** sobre 13.38.0 ✅
- [x] Smoke: `artisan about` → 13.38.0 · `route:list` (81 líneas) · `schedule:list` OK ✅
- [x] `SecurityHeadersTest::test_session_and_xsrf_cookies_are_httponly` en verde → el override `newCookie` funciona sobre `PreventRequestForgery` ✅
- [ ] **17/17 suites E2E** — ⏳ pendiente en servidor (curl sin `Sec-Fetch-Site` → valida token; verificar en runtime real)
- [ ] Smoke en staging con pgsql LATIN9 real + regresión LATIN9 (`Latin9EncodingTest`, `PiiEncryptionTest`, `PgMigrationQueriesTest`)
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
