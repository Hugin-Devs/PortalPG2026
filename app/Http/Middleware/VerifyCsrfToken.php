<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Cookie XSRF-TOKEN con HttpOnly (ZAP baseline: "Cookie No HttpOnly Flag").
     *
     * El framework la crea sin HttpOnly para que JS pueda leerla y reenviarla
     * en el header X-XSRF-TOKEN, pero esta app no hace peticiones XHR/fetch
     * con ese header (todo el admin usa formularios clásicos con @csrf; no
     * hay ni una llamada a window.axios en las vistas). Endurecerla no
     * cambia el flujo CSRF y elimina la lectura de la cookie por script.
     */
    protected function newCookie($request, $config)
    {
        return new Cookie(
            'XSRF-TOKEN',
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            true,
            false,
            $config['same_site'] ?? null
        );
    }
}
