<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_on_public_pages(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_security_headers_are_present_on_login_page(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_uses_nonce_and_drops_unsafe_inline_for_scripts(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertMatchesRegularExpression("/nonce-[A-Za-z0-9+\/=]{24}/", $csp);
    }

    public function test_hsts_header_is_emitted_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->get('/')->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );
    }

    public function test_session_and_xsrf_cookies_are_httponly(): void
    {
        $response = $this->get('/');

        $cookies = collect($response->headers->getCookies());
        $session = $cookies->first(fn ($c) => $c->getName() === config('session.cookie'));
        $xsrf = $cookies->first(fn ($c) => $c->getName() === 'XSRF-TOKEN');

        $this->assertNotNull($session, 'Falta la cookie de sesión');
        $this->assertTrue($session->isHttpOnly(), 'La cookie de sesión debe ser HttpOnly');
        $this->assertNotNull($xsrf, 'Falta la cookie XSRF-TOKEN');
        $this->assertTrue($xsrf->isHttpOnly(), 'La cookie XSRF-TOKEN debe ser HttpOnly (ZAP baseline)');
    }

    public function test_x_powered_by_header_is_removed_at_runtime(): void
    {
        $this->get('/')->assertHeaderMissing('X-Powered-By');
    }
}
