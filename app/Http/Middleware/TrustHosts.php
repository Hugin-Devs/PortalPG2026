<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * Incluye el host de APP_URL, los entornos de desarrollo y los dominios
     * institucionales, mas una lista opcional via TRUSTED_HOSTS (CSV).
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $extra = array_map('trim', explode(',', (string) config('security.trusted_hosts')));

        return array_values(array_filter(array_merge(
            [$this->allSubdomainsOfApplicationUrl()],
            [
                'localhost',
                '*.localhost',
                '127.0.0.1',
                'portal2026.test',
                '*.portal2026.test',
                'gdc.gob.ve',
                '*.gdc.gob.ve',
            ],
            $extra
        )));
    }
}
