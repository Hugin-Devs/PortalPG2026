<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // Tope duro por IP como respaldo frente a saturación (DoS/credential
            // stuffing). La protección real de fuerza bruta es el lockout por
            // intentos fallidos en AuthController (config security.login_lockout:
            // attempts fallos -> decay_seconds).
            // Configurable via config('security.rate_limits.login') para respetar
            // `php artisan config:cache`.
            return Limit::perMinute((int) config('security.rate_limits.login'))->by($request->ip());
        });

        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.contact'))->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.search'))->by($request->ip());
        });

        // Cambio de contraseña: único endpoint autenticado que compara
        // credenciales; se limita por usuario+IP para impedir probing de la
        // contraseña actual desde una sesión robada.
        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.password_change'))
                ->by('password-change:'.($request->user()?->id ?? 0).':'.$request->ip());
        });

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
