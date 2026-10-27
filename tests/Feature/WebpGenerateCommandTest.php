<?php

namespace Tests\Feature;

use App\Services\Webp;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebpGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var Filesystem */
    private $disk;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->disk = Storage::disk('public');
    }

    private function png(string $path): void
    {
        $img = imagecreatetruecolor(4, 4);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 10, 10));
        $this->disk->makeDirectory(dirname($path));
        ob_start();
        imagepng($img);
        $this->disk->put($path, ob_get_clean());
        imagedestroy($img);
    }

    public function test_backfill_converts_existing_skips_and_ilegibles(): void
    {
        // Convertible sin sidecar → convertido
        $this->png('uploads/foto.png');
        // Convertible con sidecar ya presente → ya existentes
        $this->png('partners/logo.png');
        $this->disk->put('partners/logo.webp', 'previo');
        // No convertible → omitido
        $this->disk->put('gacetas/doc.pdf', 'pdf');
        // Convertible pero ilegible → ilegible
        $this->disk->put('settings/corrupto.png', 'no soy una imagen');

        $this->withoutMockingConsoleOutput();
        $exit = Artisan::call('webp:generate');
        $output = Artisan::output();

        $this->assertSame(0, $exit, "Salida: {$output}");
        $this->assertStringContainsString(
            'WebP: 1 convertidos, 1 ya existentes, 2 omitidos, 1 ilegibles, 0 fallidos',
            $output
        );
        $this->disk->assertExists('uploads/foto.webp');
    }

    public function test_quality_option_is_clamped_and_dirs_can_be_missing(): void
    {
        // Sin directorios → todo omitido, exit 0 (calidad 200 se trunca a 100)
        $this->artisan('webp:generate', ['--quality' => 200])->assertSuccessful();

        $this->png('uploads/q.png');
        $this->artisan('webp:generate', ['--quality' => '50'])->assertSuccessful();
        $this->disk->assertExists('uploads/q.webp');
    }

    public function test_fails_when_gd_has_no_webp_support(): void
    {
        if (Webp::supported()) {
            $this->markTestSkipped('El entorno tiene imagewebp; la rama de fallo solo aplica sin GD WebP');
        }

        $this->artisan('webp:generate')->assertFailed();
    }
}
