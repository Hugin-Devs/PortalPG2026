<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Orientación (portrait/landscape) de los medios adjuntos de una noticia.
 *
 * La BD no guarda dimensiones, así que se derivan del fichero en el disco
 * "public" y se cachean por ruta + mtime (ver NEWS: layout "revista" del
 * detalle, resources/views/public/news-show.blade.php).
 */
class MediaOrientation
{
    /** Vida de la caché de dimensiones por fichero (7 días). */
    public const DIMS_TTL = 604800;

    /**
     * Dimensiones de una imagen [w, h] o null si no se pueden leer.
     */
    public static function image(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $dims = self::remember($path, function () use ($path) {
            $absolute = self::absolutePath($path);
            $info = $absolute !== null ? @getimagesize($absolute) : false;

            if ($info === false || $info[0] < 1 || $info[1] < 1) {
                return ['w' => 0, 'h' => 0];
            }

            return ['w' => (int) $info[0], 'h' => (int) $info[1]];
        });

        return self::valid($dims) ? $dims : null;
    }

    /**
     * Dimensiones de un vídeo local (mp4/m4v/mov) leyendo el tkhd del moov,
     * o null si el contenedor no se puede leer. Los embeds de YouTube no
     * llegan aquí: se asumen landscape (16:9).
     */
    public static function video(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $dims = self::remember($path, function () use ($path) {
            $absolute = self::absolutePath($path);
            $size = $absolute !== null ? self::mp4Dimensions($absolute) : null;

            return $size ?? ['w' => 0, 'h' => 0];
        });

        return self::valid($dims) ? $dims : null;
    }

    /**
     * Portrait estricto: alto > ancho. Lo cuadrado y lo ilegible cuenta como
     * landscape, de modo que cualquier elemento no vertical mantiene el
     * layout clásico del detalle.
     */
    public static function isPortrait(?array $dims): bool
    {
        return $dims !== null && $dims['h'] > $dims['w'];
    }

    protected static function valid(?array $dims): bool
    {
        return is_array($dims) && ($dims['w'] ?? 0) > 0 && ($dims['h'] ?? 0) > 0;
    }

    protected static function absolutePath(string $path): ?string
    {
        $absolute = Storage::disk('public')->path($path);

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * Cachea por fichero. Se guarda [w,h] con 0 para "desconocido" (en vez
     * de null) porque Cache::remember re-ejecuta el closure ante valores nulos
     * y re-parsearía el mismo fichero en cada petición.
     */
    protected static function remember(string $path, Closure $callback): array
    {
        $absolute = Storage::disk('public')->path($path);
        $mtime = is_file($absolute) ? (int) @filemtime($absolute) : 0;
        $key = 'media.dims:'.$path.':'.$mtime;

        return Cache::remember($key, self::DIMS_TTL, $callback);
    }

    /**
     * Lee el ancho/alto de la pista de vídeo más grande recorriendo las
     * cajas MP4 (moov > trak > tkhd). Maneja moov al final del fichero y
     * tamaños de caja de 32/64 bits.
     *
     * @return array{w:int,h:int}|null
     */
    protected static function mp4Dimensions(string $file): ?array
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $length = (int) @filesize($file);
            $moov = self::findBox($handle, 0, $length, 'moov');

            if ($moov === null) {
                return null;
            }

            $best = null;
            $offset = $moov[0];

            while ($offset < $moov[1]) {
                $box = self::readBox($handle, $offset, $moov[1]);

                if ($box === null) {
                    break;
                }

                if ($box[0] === 'trak') {
                    $tkhd = self::findBox($handle, $box[1], $box[2], 'tkhd');
                    $dims = $tkhd === null ? null : self::readTkhd($handle, $tkhd[0], $tkhd[1]);

                    if ($dims !== null && ($best === null || $dims['w'] * $dims['h'] > $best['w'] * $best['h'])) {
                        $best = $dims;
                    }
                }

                $offset = $box[2];
            }

            return $best;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{0:string,1:int,2:int}|null [tipo, inicio de payload, fin de caja]
     */
    protected static function readBox($handle, int $offset, int $limit): ?array
    {
        if ($limit - $offset < 8) {
            return null;
        }

        if (fseek($handle, $offset) !== 0) {
            return null;
        }

        $header = fread($handle, 8);

        if ($header === false || strlen($header) !== 8) {
            return null;
        }

        $unpacked = unpack('Nsize', substr($header, 0, 4));
        $size = $unpacked['size'];
        $type = substr($header, 4, 4);
        $headerSize = 8;

        if ($size === 1) {
            $large = fread($handle, 8);

            if ($large === false || strlen($large) !== 8) {
                return null;
            }

            $parts = unpack('Nhigh/Nlow', $large);
            $size = ($parts['high'] << 32) | $parts['low'];
            $headerSize = 16;
        } elseif ($size === 0) {
            $size = $limit - $offset;
        }

        if ($size < $headerSize || $offset + $size > $limit) {
            return null;
        }

        return [$type, $offset + $headerSize, $offset + $size];
    }

    /**
     * @return array{0:int,1:int}|null [inicio, fin] del payload de la caja
     */
    protected static function findBox($handle, int $start, int $end, string $type): ?array
    {
        $offset = $start;

        while ($offset < $end) {
            $box = self::readBox($handle, $offset, $end);

            if ($box === null) {
                return null;
            }

            if ($box[0] === $type) {
                return [$box[1], $box[2]];
            }

            $offset = $box[2];
        }

        return null;
    }

    /**
     * tkhd v0 (payload 84 bytes) y v1 (96 bytes) terminan en ancho/alto en
     * punto fijo 16.16; se queda con la pista de mayor superficie.
     *
     * @return array{w:int,h:int}|null
     */
    protected static function readTkhd($handle, int $start, int $end): ?array
    {
        $size = $end - $start;

        // version+flags (4) + tiempos + reserved + matrix (>= 68) + w/h (8)
        if ($size < 76) {
            return null;
        }

        if (fseek($handle, $end - 8) !== 0) {
            return null;
        }

        $buffer = fread($handle, 8);

        if ($buffer === false || strlen($buffer) !== 8) {
            return null;
        }

        $parts = unpack('Nw/Nh', $buffer);
        $w = (int) ($parts['w'] / 65536);
        $h = (int) ($parts['h'] / 65536);

        if ($w < 1 || $h < 1) {
            return null;
        }

        return ['w' => $w, 'h' => $h];
    }
}
