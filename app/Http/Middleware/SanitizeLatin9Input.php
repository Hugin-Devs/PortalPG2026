<?php

namespace App\Http\Middleware;

use App\Support\Latin9;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Translitera a LATIN9 seguro todo texto que llega en la petición (query,
 * body de formularios y JSON), antes de que cualquier controlador lo use.
 *
 * Es imprescindible también para parámetros que van DIRECTOS a una consulta
 * SQL (p. ej. el término de búsqueda de /buscar con ILIKE): aunque los
 * modelos sanitizan al escribir, un carácter sin equivalente LATIN9 en un
 * WHERE provocaría el error 22021 de PostgreSQL (ver docs/DOCUMENTACION.md §14.K).
 */
class SanitizeLatin9Input
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->clean($request);

        return $next($request);
    }

    private function clean(Request $request): void
    {
        $this->cleanParameterBag($request->query);

        if ($request->isJson()) {
            $this->cleanParameterBag($request->json());
        } elseif ($request->request !== $request->query) {
            $this->cleanParameterBag($request->request);
        }
    }

    /**
     * Sanea recursivamente un ParameterBag (strings y arrays anidados;
     * los archivos y otros tipos se dejan intactos).
     */
    private function cleanParameterBag(ParameterBag $bag): void
    {
        $bag->replace($this->cleanArray($bag->all()));
    }

    private function cleanArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // El trim final elimina espacios que dejan los emojis al
                // quitarse (consistente con TrimStrings, que corre antes).
                $data[$key] = trim(Latin9::sanitize($value));
            } elseif (is_array($value)) {
                $data[$key] = $this->cleanArray($value);
            }
        }

        return $data;
    }
}
