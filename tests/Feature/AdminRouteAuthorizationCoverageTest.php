<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M3: toda ruta bajo /admin debe declarar autorización explícita.
 * Regresión contra rutas futuras añadidas sin `permission:*` (deny-by-default).
 */
class AdminRouteAuthorizationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas bajo /admin que, por diseño, no llevan `permission:*`:
     * guest (login), cambio de contraseña obligatorio, dashboard y logout.
     */
    private const JUSTIFIED = [
        'GET|HEAD admin/login',
        'POST admin/login',
        'GET|HEAD admin/cambiar-contrasena',
        'POST admin/cambiar-contrasena',
        'GET|HEAD admin',
        'POST admin/logout',
    ];

    public function test_every_admin_route_declares_permission_middleware(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin')) {
                continue;
            }

            $key = implode('|', $route->methods()).' '.$uri;
            if (in_array($key, self::JUSTIFIED, true)) {
                continue;
            }

            $hasPermission = collect($route->middleware())
                ->contains(fn ($m) => str_starts_with((string) $m, 'permission:'));

            if (! $hasPermission) {
                $violations[] = $key;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Estas rutas /admin no declaran middleware permission:*: '.implode(', ', $violations)
        );
    }

    public function test_justified_routes_still_exist(): void
    {
        $uris = collect(Route::getRoutes())->map->uri()->all();

        foreach (['admin/login', 'admin/cambiar-contrasena', 'admin/logout'] as $uri) {
            $this->assertContains($uri, $uris);
        }
    }
}
