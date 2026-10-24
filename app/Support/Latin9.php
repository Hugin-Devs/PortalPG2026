<?php

namespace App\Support;

/**
 * Adaptación de texto a la codificación LATIN9 (ISO-8859-15) de la base de
 * datos de producción (ver docs/DOCUMENTACION.md §14.K).
 *
 * La conexión PostgreSQL usa client_encoding=UTF8 y el servidor convierte
 * LATIN9 <-> UTF-8 automáticamente, pero cualquier carácter sin equivalente
 * en LATIN9 (emoji, comillas tipográficas, puntos suspensivos, rayas, etc.)
 * provoca el error 22021 de PostgreSQL — incluso en parámetros de consulta.
 *
 * Por eso el texto se translitera ANTES de cruzar el cable: los caracteres
 * representables se conservan tal cual (á, é, ñ, €, Œ, Š, ž...), los
 * aproximables se sustituyen por su forma ASCII ("..." → "...", “ ” → ")
 * y los no representables (emojis, símbolos astrales) se eliminan.
 */
class Latin9
{
    /**
     * Devuelve una versión del texto segura para una BD LATIN9, en UTF-8.
     *
     * Idempotente: sanitizar dos veces da el mismo resultado que una vez.
     */
    public static function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Fast-path: si todo el texto ya es representable en LATIN9 (la
        // mayoría del español normal), no se toca nada. iconv puro falla
        // si algún carácter no tiene equivalente exacto.
        if (@iconv('UTF-8', 'ISO-8859-15', $value) !== false) {
            return $value;
        }

        // Eliminar emojis y símbolos astrales antes del transliterate:
        // el //TRANSLIT de glibc los convertiría en cosas como ":-D".
        $cleaned = preg_replace('/[\x{10000}-\x{10FFFF}\p{So}\p{Cs}]/u', '', $value);
        if ($cleaned !== null) {
            $value = $cleaned;
        }

        // UTF-8 -> ISO-8859-15 transliterando lo aproximable e ignorando
        // lo que quede (TRANSLIT + IGNORE cubre el 100 % de los casos).
        $latin9 = @iconv('UTF-8', 'ISO-8859-15//TRANSLIT//IGNORE', $value);

        if ($latin9 === false) {
            // Fallback (iconv sin soporte de TRANSLIT): mbstring sustituye
            // los caracteres no convertibles por '?'.
            $latin9 = mb_convert_encoding($value, 'ISO-8859-15', 'UTF-8');
        }

        // Devolver UTF-8: la conexión corre con client_encoding=UTF8 y es
        // PostgreSQL quien convierte a LATIN9 al almacenar.
        $utf8 = @iconv('ISO-8859-15', 'UTF-8', $latin9);

        return $utf8 === false ? $value : $utf8;
    }

    /**
     * Indica si el texto es 100 % representable en LATIN9 (sin transliterar).
     */
    public static function isSafe(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return @iconv('UTF-8', 'ISO-8859-15', $value) !== false;
    }
}
