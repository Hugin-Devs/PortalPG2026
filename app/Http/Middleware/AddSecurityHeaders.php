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

        // ZAP baseline: retirar X-Powered-By en runtime aunque expose_php
        // esté On (dev). En producción la condición 4 del despliegue fija
        // expose_php=Off como primera línea; esto es defensa en profundidad.
        header_remove('X-Powered-By');

        // ZAP baseline (Big Redirect): las 3xx de Symfony/ Laravel llevan un
        // cuerpo HTML de "refresh" (~378 B, solo repite la URL de destino).
        // Los navegadores solo necesitan la cabecera Location; vaciar el
        // cuerpo elimina la alerta sin efecto funcional.
        if ($response->isRedirection()) {
            $response->setContent('');
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        // CSP (endurecida 2026-10-26, ZAP): sin 'unsafe-inline' en style-src —
        // los <style> inline legítimos reciben el nonce de la petición y los
        // atributos style="…" (TinyMCE, utilidades) se permiten vía
        // style-src-attr (CSP3; los navegadores sin soporte de style-src-attr
        // caen a style-src con nonce y perderían solo los style=" inline").
        // 'unsafe-eval' fue retirado (verificado: ni TinyMCE 8 ni Vite lo
        // usan). script-src mantiene el nonce por petición (G7).
        $csp = "default-src 'self'; "
            ."script-src 'self' 'nonce-{$nonce}'; "
            ."style-src 'self' 'nonce-{$nonce}'; "
            ."style-src-attr 'unsafe-inline'; "
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
