<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Límites de tasa (rate limiting)
    |--------------------------------------------------------------------------
    |
    | Se definen en un archivo de configuración —no en middleware ni providers—
    | para que respeten `php artisan config:cache`. Laravel no carga `.env`
    | cuando la configuración está cacheada, por lo que `env()` fuera de
    | `config/` devolvería los valores por defecto y haría inútiles las
    | variables del `.env`.
    |
    */

    'rate_limits' => [
        'login' => (int) env('LOGIN_RATE_LIMIT', 60),
        'contact' => (int) env('CONTACT_RATE_LIMIT', 10),
        'search' => (int) env('SEARCH_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxies y hosts de confianza
    |--------------------------------------------------------------------------
    |
    | Cadenas separadas por comas. Vacío = sin proxies/hosts adicionales.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    'trusted_hosts' => env('TRUSTED_HOSTS', ''),

    /*
    |--------------------------------------------------------------------------
    | Auditoría de actividad (G6)
    |--------------------------------------------------------------------------
    */

    'audit_enabled' => (bool) env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Retención de mensajes de contacto (GDPR Art. 5(1)(e))
    |--------------------------------------------------------------------------
    |
    | Días que se conserva un mensaje en contacts antes de que
    | `php artisan contacts:prune` (programado a diario) lo elimine.
    |
    */

    'contact_retention_days' => (int) env('CONTACT_RETENTION_DAYS', 365),
];
