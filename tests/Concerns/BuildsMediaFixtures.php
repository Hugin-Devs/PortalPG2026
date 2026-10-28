<?php

namespace Tests\Concerns;

/**
 * Generadores mínimos de medios de prueba (sin fixtures binarias en el repo).
 */
trait BuildsMediaFixtures
{
    /**
     * PNG generado con GD en la dimensión pedida.
     */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * Contenedor MP4 mínimo (ftyp + moov > trak > tkhd) con el ancho/alto en
     * punto fijo 16.16 al final del tkhd, como cualquier remuxer.
     */
    private function mp4(int $width, int $height): string
    {
        $box = fn (string $type, string $payload): string => pack('N', strlen($payload) + 8).$type.$payload;

        $tkhd = $box('tkhd', str_repeat("\x00", 76).pack('N', $width * 65536).pack('N', $height * 65536));
        $trak = $box('trak', $tkhd);
        $moov = $box('moov', $trak);
        $ftyp = $box('ftyp', 'isom'.pack('N', 512).'isomiso2');

        return $ftyp.$moov;
    }
}
