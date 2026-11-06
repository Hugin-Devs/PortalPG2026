<?php

namespace App\Http\Middleware;

use App\Models\DailyVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta visitas únicas al portal (una por sesión de navegador y día).
 *
 * Se registra al final del grupo `web` porque necesita StartSession: la
 * marca de "ya contada hoy" vive en la propia sesión, así un F5 (o abrir
 * otra pestaña con la misma sesión) no infla el contador. Solo cuenta GET
 * exitosos que devuelven HTML de páginas públicas: el panel (/admin), los
 * ficheros y las rutas de mantenimiento quedan fuera, igual que los bots
 * conocidos. El incremento es un upsert atómico sobre `daily_visits`.
 */
class CountPortalVisit
{
    /** User-Agents de rastreadores/previsualizadores (no son visitas humanas). */
    private const BOT_PATTERN = '~bot|crawl|spider|slurp|preview|facebookexternalhit|bingpreview|headlesschrome|python-requests|go-http-client|okhttp~i';

    /** Prefijos de ruta que no son páginas públicas del portal. */
    private const EXCLUDED_PREFIXES = ['admin', 'storage', 'extranet'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCount($request, $response)) {
            return $response;
        }

        $session = $request->session();
        if (! $session->isStarted()) {
            return $response;
        }

        $day = now()->toDateString();
        $flag = 'portal_visited:'.$day;

        if (! $session->has($flag)) {
            $session->put($flag, true);
            DailyVisit::record($day);
        }

        return $response;
    }

    private function shouldCount(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        if ($this->isExcludedPath($request)) {
            return false;
        }

        return ! preg_match(self::BOT_PATTERN, (string) $request->userAgent());
    }

    private function isExcludedPath(Request $request): bool
    {
        // path() no lleva "/" inicial; en la portada devuelve "/" (se normaliza a "").
        $path = trim($request->path(), '/');

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
