<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class Webp
{
    public const CONVERTIBLE = ['jpg', 'jpeg', 'png'];

    public const QUALITY = 82;

    public static function supported(): bool
    {
        return function_exists('imagewebp');
    }

    public static function convertible(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::CONVERTIBLE, true);
    }

    public static function sidecarPath(string $path): ?string
    {
        if (! self::convertible($path)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '.webp', $path);
    }

    public static function decodable(string $path): bool
    {
        if (! self::convertible($path)) {
            return false;
        }

        $content = Storage::disk('public')->get($path);

        if (empty($content)) {
            return false;
        }

        $source = @imagecreatefromstring($content);

        if ($source === false) {
            return false;
        }

        imagedestroy($source);

        return true;
    }

    public static function exists(string $path): bool
    {
        $sidecar = self::sidecarPath($path);

        return $sidecar !== null && Storage::disk('public')->exists($sidecar);
    }

    public static function url(string $path): ?string
    {
        if (! self::exists($path)) {
            return null;
        }

        return asset('storage/'.self::sidecarPath($path));
    }

    public static function convert(string $path, ?int $quality = null): ?string
    {
        $sidecar = self::sidecarPath($path);

        if ($sidecar === null || ! self::supported()) {
            return null;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($sidecar)) {
            return $sidecar;
        }

        $content = $disk->get($path);

        if (empty($content)) {
            return null;
        }

        $source = @imagecreatefromstring($content);

        if ($source === false) {
            return null;
        }

        if (! imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png') {
            imagealphablending($source, false);
            imagesavealpha($source, true);
        }

        $disk->makeDirectory(dirname($sidecar));

        $written = @imagewebp($source, $disk->path($sidecar), $quality ?? self::QUALITY);

        imagedestroy($source);

        if ($written !== true) {
            $disk->delete($sidecar);

            return null;
        }

        return $sidecar;
    }

    public static function delete(string $path): void
    {
        $sidecar = self::sidecarPath($path);

        if ($sidecar !== null) {
            Storage::disk('public')->delete($sidecar);
        }
    }
}
