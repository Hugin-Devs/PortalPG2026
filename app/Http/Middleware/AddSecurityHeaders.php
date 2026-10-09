<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce único por petición para permitir scripts inline legítimos sin
        // necesidad de 'unsafe-inline' en script-src (G7). Se expone a las
        // vistas vía request()->attributes->get('csp_nonce').
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        // CSP: 'unsafe-eval' fue retirado (verificado: ni TinyMCE 8 ni Vite lo
        // usan). 'unsafe-inline' ya no es necesario en script-src gracias al
        // nonce por petición. style-src mantiene 'unsafe-inline' por TinyMCE.
        $csp = "default-src 'self'; "
            ."script-src 'self' 'nonce-{$nonce}'; "
            ."style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: blob:; "
            ."font-src 'self'; "
            .'frame-src https://www.youtube.com https://www.google.com; '
            ."frame-ancestors 'self'; "
            ."form-action 'self'; "
            ."connect-src 'self'; "
            ."object-src 'none'; "
            ."base-uri 'self';";
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->secure() || config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
