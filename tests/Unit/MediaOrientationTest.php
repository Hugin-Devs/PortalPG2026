<?php

namespace Tests\Unit;

use App\Support\MediaOrientation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

class MediaOrientationTest extends TestCase
{
    use BuildsMediaFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::flush();
    }

    public function test_is_portrait_requires_a_readable_portrait_dimension(): void
    {
        $this->assertTrue(MediaOrientation::isPortrait(['w' => 400, 'h' => 700]));
        $this->assertFalse(MediaOrientation::isPortrait(['w' => 700, 'h' => 400]));
        $this->assertFalse(MediaOrientation::isPortrait(['w' => 500, 'h' => 500]), 'Lo cuadrado cuenta como landscape');
        $this->assertFalse(MediaOrientation::isPortrait(null));
    }

    public function test_image_reads_dimensions_and_caches_them_by_mtime(): void
    {
        Storage::disk('public')->put('uploads/t/portrait.png', $this->png(400, 700));

        $this->assertSame(['w' => 400, 'h' => 700], MediaOrientation::image('uploads/t/portrait.png'));
        $this->assertTrue(MediaOrientation::isPortrait(MediaOrientation::image('uploads/t/portrait.png')));

        $mtime = filemtime(Storage::disk('public')->path('uploads/t/portrait.png'));
        $this->assertTrue(
            Cache::has('media.dims:uploads/t/portrait.png:'.$mtime),
            'Las dimensiones deben quedar cacheadas por ruta + mtime'
        );
    }

    public function test_image_returns_null_for_missing_or_corrupt_files(): void
    {
        $this->assertNull(MediaOrientation::image('uploads/que-no-existe.png'));
        $this->assertNull(MediaOrientation::image(null));

        Storage::disk('public')->put('uploads/t/corrupto.png', 'esto no es una imagen');
        $this->assertNull(MediaOrientation::image('uploads/t/corrupto.png'));
    }

    public function test_video_reads_mp4_track_dimensions(): void
    {
        Storage::disk('public')->put('videos/reel.mp4', $this->mp4(1080, 1920));
        Storage::disk('public')->put('videos/apaisado.mp4', $this->mp4(1920, 1080));

        $this->assertSame(['w' => 1080, 'h' => 1920], MediaOrientation::video('videos/reel.mp4'));
        $this->assertTrue(MediaOrientation::isPortrait(MediaOrientation::video('videos/reel.mp4')));

        $this->assertSame(['w' => 1920, 'h' => 1080], MediaOrientation::video('videos/apaisado.mp4'));
        $this->assertFalse(MediaOrientation::isPortrait(MediaOrientation::video('videos/apaisado.mp4')));
    }

    public function test_video_returns_null_for_missing_or_unreadable_containers(): void
    {
        $this->assertNull(MediaOrientation::video('videos/que-no-existe.mp4'));
        $this->assertNull(MediaOrientation::video(null));

        Storage::disk('public')->put('videos/roto.mp4', str_repeat('x', 64));
        $this->assertNull(MediaOrientation::video('videos/roto.mp4'), 'Un contenedor ilegible no debe romper el layout');
    }
}
