<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

/**
 * Regresión (2026-10-28): con el salto a Laravel 13 / Symfony 7.4,
 * `Request::setTrustedHosts()` compila los patrones como regex CRUDOS. La
 * app devolvía globos de Laravel 10 (`*.gdc.gob.ve`) que no compilan
 * ("quantifier does not follow a repeatable item") y cualquier petición cuyo
 * Host no casara con el primer patrón (IP, `www.…`, escáneres) terminaba en
 * 500 con ErrorException en el log (58 respuestas 500 en la hora 13 del
 * 2026-10-28 en producción).
 */
class TrustHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        SymfonyRequest::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_every_trusted_host_pattern_is_a_valid_anchored_regexp(): void
    {
        $hosts = (new TrustHosts($this->app))->hosts();

        $this->assertNotEmpty($hosts);

        foreach ($hosts as $pattern) {
            $this->assertMatchesRegularExpression('/^\^.*\$$/', $pattern, "Debe ir anclado (^…$): {$pattern}");
            $this->assertNotFalse(
                preg_match('{'.$pattern.'}i', 'probe'),
                "Regex inválida (provocaría 500 en producción): {$pattern}"
            );
        }
    }

    public function test_canonical_and_local_hosts_are_accepted(): void
    {
        SymfonyRequest::setTrustedHosts((new TrustHosts($this->app))->hosts());

        foreach (['web.gdc.gob.ve', 'www.gdc.gob.ve', 'localhost', '127.0.0.1'] as $host) {
            $request = SymfonyRequest::create('https://'.$host.'/', 'GET', [], [], [], ['HTTP_HOST' => $host]);

            $this->assertSame($host, $request->getHost(), "El host {$host} debía ser confiable");
        }
    }

    public function test_unknown_hosts_are_rejected_instead_of_crashing(): void
    {
        SymfonyRequest::setTrustedHosts((new TrustHosts($this->app))->hosts());

        $request = SymfonyRequest::create('http://172.16.0.14/', 'GET', [], [], [], ['HTTP_HOST' => '172.16.0.14']);

        try {
            // Con el bug anterior esta línea lanzaba ErrorException por la regex
            // inválida (500); el comportamiento correcto es el rechazo limpio.
            $host = $request->getHost();
            $this->assertNotSame('172.16.0.14', $host, 'Un Host desconocido no debe darse por bueno');
        } catch (SuspiciousOperationException) {
            $this->assertTrue(true, 'Rechazo limpio de Host desconocido');
        }
    }
}
