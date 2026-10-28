<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Gaceta;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Video;
use App\Observers\CategoryObserver;
use App\Observers\GacetaObserver;
use App\Observers\PageObserver;
use App\Observers\PartnerObserver;
use App\Observers\PostObserver;
use App\Observers\VideoObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->registerObservers();
        $this->registerRateLimiters();
    }

    /**
     * Observers de contenido (antes en EventServiceProvider).
     */
    private function registerObservers(): void
    {
        Post::observe(PostObserver::class);
        Category::observe(CategoryObserver::class);
        Video::observe(VideoObserver::class);
        Partner::observe(PartnerObserver::class);
        Gaceta::observe(GacetaObserver::class);
        Page::observe(PageObserver::class);
    }

    /**
     * Rate limiters de rutas (antes en RouteServiceProvider).
     */
    private function registerRateLimiters(): void
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
    }
}
