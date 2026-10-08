<?php

namespace Tests\Unit;

use App\Services\Webp;
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
}
