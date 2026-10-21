<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna un request-id correlacionable de extremo a extremo.
 *
 * - Si el cliente (o el proxy frontal) envía `X-Request-Id` con formato
 *   seguro, se respeta; si no, se genera un UUID.
 * - Se expone en la respuesta (`X-Request-Id`) y en los atributos de la
 *   petición (`request_id`) para que logging/auditoría puedan usarlo.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $provided)
            ? $provided
            : (string) Str::uuid();

        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
