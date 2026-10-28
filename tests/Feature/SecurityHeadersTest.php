<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_csp_uses_nonce_and_drops_unsafe_inline_for_scripts_and_styles(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        // Endurecimiento 2026-10-26 (ZAP): style-src solo con nonce;
        // los atributos style=" se permiten vía style-src-attr.
        $this->assertStringContainsString("style-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);
        $this->assertMatchesRegularExpression("/nonce-[A-Za-z0-9+\/=]{24}/", $csp);
    }

    public function test_redirect_responses_have_empty_body(): void
    {
        // ZAP baseline (Big Redirect): las 3xx no llevan cuerpo HTML.
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
        $this->assertSame('', $response->baseResponse->getContent(), 'La redirección no debe llevar cuerpo');
    }

    public function test_inline_style_tags_carry_the_csp_nonce(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($user)->post('/admin/noticias', [
            'title' => 'Carrusel nonce',
            'category_id' => $category->id,
            'excerpt' => 'x',
            'content' => '<p>cuerpo</p>',
            'status' => 'published',
        ]);
        $post = Post::where('title', 'Carrusel nonce')->first();

        // Se añaden imágenes de galería para activar el <style> del carrusel
        PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/a.png', 'position' => 1]);
        PostMedia::create(['post_id' => $post->id, 'type' => 'image', 'path' => 'uploads/b.png', 'position' => 2]);

        $body = $this->get("/noticias/{$post->slug}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<style nonce="[A-Za-z0-9+\/=]{24}">/', $body);
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
