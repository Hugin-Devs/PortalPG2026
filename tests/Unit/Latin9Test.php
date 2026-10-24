<?php

namespace Tests\Unit;

use App\Support\Latin9;
use PHPUnit\Framework\TestCase;

/**
 * Política de transliteración LATIN9 (docs/DOCUMENTACION.md §14.K): la BD de
 * producción es PostgreSQL ISO-8859-15 y el texto se adapta ANTES de cruzar
 * el cable (el servidor convierte LATIN9 <-> UTF-8).
 */
class Latin9Test extends TestCase
{
    public function test_texto_ya_representable_se_conserva_intacto(): void
    {
        $seguro = 'Ábalos: añadir ñ, €, Œ, š, ž, ¡¿?, Ü y É.';

        $this->assertSame($seguro, Latin9::sanitize($seguro));
        $this->assertTrue(Latin9::isSafe($seguro));
        $this->assertFalse(Latin9::isSafe('Caracas–Roma…'));
    }

    public function test_comillas_tipographicas_rayas_y_puntos_suspensivos_se_transliteran(): void
    {
        $this->assertSame('"directos"', Latin9::sanitize('“directos”'));
        $this->assertSame("'comillas'", Latin9::sanitize('‘comillas’'));
        $this->assertSame('Caracas-Roma...', Latin9::sanitize('Caracas–Roma…'));
        $this->assertSame('a - b', Latin9::sanitize('a — b'));
    }

    public function test_emojis_y_simbolos_astrales_se_eliminan(): void
    {
        $this->assertSame('Hola  mundo', Latin9::sanitize('Hola 😀 mundo'));
        // El helper es puro (no hace trim); el middleware de entrada sí
        // recorta los espacios huérfanos tras quitar el emoji.
        $this->assertSame('Fuego Patrio ', Latin9::sanitize('Fuego Patrio 🔥'));
    }

    public function test_signos_sin_equivalente_en_latin9_se_transliteran(): void
    {
        // ¤ ¨ ´ ¸ y § no existen en ISO-8859-15 (fueron reemplazados por
        // € Ž ž œ Š): el transliterate de iconv los aproxima.
        $out = Latin9::sanitize('sección ¤ ¨ ´ ¸ §');
        $this->assertTrue(Latin9::isSafe($out), "Salió algo no seguro: {$out}");
    }

    public function test_null_y_vacio_se_conservan(): void
    {
        $this->assertNull(Latin9::sanitize(null));
        $this->assertSame('', Latin9::sanitize(''));
        $this->assertTrue(Latin9::isSafe(null));
        $this->assertTrue(Latin9::isSafe(''));
    }

    public function test_sanitizar_es_idempotente(): void
    {
        $entrada = 'Título “único” del Liceo Ábalos… 😀 — 100 %';

        $unaVez = Latin9::sanitize($entrada);
        $this->assertSame($unaVez, Latin9::sanitize($unaVez));
        $this->assertTrue(Latin9::isSafe($unaVez));
    }

    public function test_el_resultado_siempre_es_utf8_valido(): void
    {
        $out = Latin9::sanitize('niño 🚀 “oficial” — 2026…');

        $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
    }
}
