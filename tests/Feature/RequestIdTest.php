<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_includes_a_request_id_header(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-Id');
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9\-]{8,64}$/',
            (string) $response->headers->get('X-Request-Id')
        );
    }

    public function test_valid_incoming_request_id_is_propagated(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => 'trace-abc-12345'])->get('/');

        $response->assertOk();
        $this->assertSame('trace-abc-12345', $response->headers->get('X-Request-Id'));
    }

    public function test_malicious_incoming_request_id_is_replaced(): void
    {
        // Valor con caracteres fuera del allowlist (espacios, ../, etc.):
        // debe sustituirse por un UUID generado en servidor.
        $response = $this->withHeaders(['X-Request-Id' => '../../etc/passwd !!'])->get('/');

        $response->assertOk();
        $this->assertNotSame('../../etc/passwd !!', $response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9\-]{8,64}$/',
            (string) $response->headers->get('X-Request-Id')
        );
    }
}
