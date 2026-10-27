<?php

namespace Tests\Unit;

use App\Services\Webp;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebpTest extends TestCase
{
    public function test_convertible_only_accepts_jpg_jpeg_and_png(): void
    {
        $this->assertTrue(Webp::convertible('uploads/x.jpg'));
        $this->assertTrue(Webp::convertible('uploads/x.JPG'));
        $this->assertTrue(Webp::convertible('uploads/x.jpeg'));
        $this->assertTrue(Webp::convertible('partners/sub/x.png'));

        $this->assertFalse(Webp::convertible('gacetas/x.pdf'));
        $this->assertFalse(Webp::convertible('uploads/x.gif'));
        $this->assertFalse(Webp::convertible('uploads/x.webp'));
        $this->assertFalse(Webp::convertible('uploads/x.svg'));
        $this->assertFalse(Webp::convertible('uploads/noextension'));
    }

    public function test_sidecar_path_swaps_the_extension_for_webp(): void
    {
        $this->assertSame('uploads/x.webp', Webp::sidecarPath('uploads/x.jpg'));
        $this->assertSame('uploads/x.webp', Webp::sidecarPath('uploads/x.jpeg'));
        $this->assertSame('uploads/x.webp', Webp::sidecarPath('uploads/x.png'));
        $this->assertSame('uploads/sub/x.webp', Webp::sidecarPath('uploads/sub/x.PNG'));
        $this->assertSame('settings/logo.webp', Webp::sidecarPath('settings/logo.png'));
    }

    public function test_sidecar_path_is_null_for_non_convertible_files(): void
    {
        $this->assertNull(Webp::sidecarPath('gacetas/x.pdf'));
        $this->assertNull(Webp::sidecarPath('uploads/x.webp'));
        $this->assertNull(Webp::sidecarPath('uploads/x.gif'));
        $this->assertNull(Webp::sidecarPath('uploads/noextension'));
    }

    public function test_supported_reflects_gd_webp_availability(): void
    {
        $this->assertSame(function_exists('imagewebp'), Webp::supported());
        $this->assertTrue(Webp::supported(), 'El entorno de desarrollo debe tener GD con imagewebp');
    }

    public function test_decodable_rejects_non_convertible_empty_and_corrupt_files(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');

        $disk->put('gacetas/doc.pdf', 'x');
        $disk->put('uploads/vacio.png', '');
        $disk->put('uploads/corrupto.png', 'esto no es una imagen');

        $this->assertFalse(Webp::decodable('gacetas/doc.pdf'), 'no convertible');
        $this->assertFalse(Webp::decodable('uploads/vacio.png'), 'contenido vacío');
        $this->assertFalse(Webp::decodable('uploads/corrupto.png'), 'bytes no decodificables');
    }

    public function test_convert_short_circuits_and_handles_empty_or_corrupt_sources(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');

        // Sidecar ya existe → devuelve el path sin reconvertir
        $img = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($img);
        $disk->put('uploads/y.png', ob_get_clean());
        imagedestroy($img);
        $disk->put('uploads/y.webp', 'preexistente');
        $this->assertSame('uploads/y.webp', Webp::convert('uploads/y.png'));

        // Fuentes vacías y corruptas → null
        $disk->put('uploads/vacio.png', '');
        $this->assertNull(Webp::convert('uploads/vacio.png'));

        $disk->put('uploads/corrupto.png', 'basura');
        $this->assertNull(Webp::convert('uploads/corrupto.png'));

        // No convertible → null
        $this->assertNull(Webp::convert('gacetas/x.pdf'));
    }

    public function test_convert_palette_png_and_custom_quality(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');

        // Imagen de paleta (no truecolor) para ejercitar imagepalettetotruecolor
        $pal = imagecreate(3, 3);
        imagecolorallocate($pal, 255, 0, 0);
        ob_start();
        imagepng($pal);
        $disk->put('uploads/paleta.png', ob_get_clean());
        imagedestroy($pal);

        $sidecar = Webp::convert('uploads/paleta.png', 40);
        $this->assertSame('uploads/paleta.webp', $sidecar);
        Storage::disk('public')->assertExists($sidecar);
    }
}
