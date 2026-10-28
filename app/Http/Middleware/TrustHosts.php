<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Hosts confiables como REGEXPS anclados (`^…$`), que es el contrato de
     * `Request::setTrustedHosts()` desde Laravel 11 / Symfony 7.
     *
     * ⚠️ Antes devolvía globos estilo Laravel 10 (`*.gdc.gob.ve`); Symfony 7.4
     * los compila como regex crudos (`{*.gdc.gob.ve}i` → "quantifier does not
     * follow a repeatable item") y toda petición cuyo Host no casara con el
     * primer patrón (acceso por IP, `www.…`, escáneres) devolvía **500**.
     *
     * @return array<int, string>
     */
    public function hosts(): array
    {
        $extra = array_filter(array_map('trim', explode(',', (string) config('security.trusted_hosts'))));

        return array_values(array_filter(array_merge(
            // ^(.+\.)?<host de APP_URL>$ — subdominios incluidos.
            [$this->allSubdomainsOfApplicationUrl()],
            [
                '^localhost$',
                '^(.+\.)?localhost$',
                '^127\.0\.0\.1$',
                '^portal2026\.test$',
                '^(.+\.)?portal2026\.test$',
                '^gdc\.gob\.ve$',
                '^(.+\.)?gdc\.gob\.ve$',
            ],
            array_map(fn (string $host) => $this->hostPattern($host), $extra)
        )));
    }

    /**
     * `www.gdc.gob.ve` → `^www\.gdc\.gob\.ve$` (host exacto);
     * `*.gob.ve` → `^(.+\.)?gob\.ve$` (subdominios).
     */
    protected function hostPattern(string $host): ?string
    {
        if ($host === '') {
            return null;
        }

        if (str_starts_with($host, '*.')) {
            return '^(.+\.)?'.preg_quote(substr($host, 2), '/').'$';
        }

        return '^'.preg_quote($host, '/').'$';
    }
}
