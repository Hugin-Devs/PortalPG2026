<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_is_limited_to_nonexistent_api_routes(): void
    {
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], config('cors.paths'));
        $this->assertSame(['*'], config('cors.allowed_origins'));
    }

    public function test_public_pages_do_not_send_global_cors_headers(): void
    {
        $response = $this->get('/');

        // El middleware HandleCors no añade Access-Control-Allow-Origin cuando
        // la ruta no coincide con `paths` (actualmente solo `api/*` y Sanctum).
        $this->assertFalse(
            $response->headers->has('Access-Control-Allow-Origin'),
            'Las páginas públicas no deben exponer CORS origins globales.'
        );
    }
}
