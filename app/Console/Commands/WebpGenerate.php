<?php

namespace App\Console\Commands;

use App\Services\Webp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WebpGenerate extends Command
{
    protected $signature = 'webp:generate {--quality=82 : Calidad WebP (0-100)}';

    protected $description = 'Genera versiones WebP de las imagenes JPG/PNG del almacenamiento (backfill)';

    public function handle(): int
    {
        if (! Webp::supported()) {
            $this->error('GD sin soporte WebP (funcion imagewebp no disponible).');

            return self::FAILURE;
        }

        $quality = max(0, min(100, (int) $this->option('quality')));
        $disk = Storage::disk('public');
        $converted = 0;
        $existing = 0;
        $skipped = 0;
        $unreadable = 0;
        $failed = 0;

        foreach (['uploads', 'partners', 'gacetas', 'settings'] as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }

            foreach ($disk->allFiles($dir) as $file) {
                if (! Webp::convertible($file)) {
                    $skipped++;

                    continue;
                }

                if (Webp::exists($file)) {
                    $existing++;

                    continue;
                }

                if (! Webp::decodable($file)) {
                    $unreadable++;
                    $this->warn('Ilegible: '.$file);

                    continue;
                }

                if (Webp::convert($file, $quality) !== null) {
                    $converted++;
                } else {
                    $failed++;
                    $this->warn('Fallo: '.$file);
                }
            }
        }

        $this->info("WebP: {$converted} convertidos, {$existing} ya existentes, {$skipped} omitidos, {$unreadable} ilegibles, {$failed} fallidos");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
