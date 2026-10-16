<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Services\Webp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class NoticiasPrecargadasSeeder extends Seeder
{
    /**
     * Carpeta fuente (relativa a public/) con los lotes de noticias precargadas.
     */
    public const SOURCE_DIR = 'Noticias Precargadas';

    /**
     * Lote de noticias reales (contenido de Instagram del GDC / autoridad).
     *
     * Seeder POR DEFECTO de las noticias: es el único que invoca
     * DatabaseSeeder para contenido de posts (PostSeeder quedó como
     * plantilla manual, no corre en `php artisan db:seed`).
     *
     * ── ESTRUCTURA ESPERADA DE CADA CARPETA ────────────────────────────────
     * public/Noticias Precargadas/Noticia N/
     *   ├── 001 <sello>.jpg    ← portada (featured_image)
     *   ├── 002 <sello>.jpg    ← galería (post_media, carrusel D15)
     *   ├── ...
     *   └── contenido....txt   ← cuerpo de la noticia; la última línea puede
     *                            ser "Fecha: dd/mm/yyyy hh:mm" (metadato,
     *                            se excluye del cuerpo y se usa como
     *                            fallback si el sello del nombre no parsea)
     *
     * ── CÓMO AGREGAR EL LOTE 2 ────────────────────────────────────────────
     * 1. Copia la carpeta "Noticia 6" (etc.) dentro de public/Noticias Precargadas/.
     * 2. Agrega su entrada al array $noticias con slug ÚNICO, título, excerpt
     *    y categoría (slug de CategorySeeder).
     * 3. Re-ejecuta: php artisan db:seed --class=NoticiasPrecargadasSeeder
     *    (idempotente: las noticias ya sembradas con portada se omiten).
     *
     * Reglas: la fecha sale del sello del nombre de la imagen 001
     * (ddmmyyyyhhmm o ddmmyyhhmm, validada) y, si no parsea, de la línea
     * "Fecha:" del txt. Las imágenes se copian a uploads/YYYY/MM/ y se
     * registran en la tabla media. El texto del txt se conserva íntegro
     * (menciones y hashtags); solo se elimina la línea de Fecha. Los
     * emojis se eliminan al guardar: la BD de producción es LATIN9 y no
     * puede representarlos (DOCUMENTACION.md §14.K).
     *
     * Al sembrar se purgan las noticias de prueba 'noticia-destacada-*'
     * (borrado físico, incluidas filas soft-deleted: libera el slug).
     */
    private const NOTICIAS = [
        'Noticia 1' => [
            'slug' => 'caracas-recibio-el-fuego-patrio-en-los-iv-juegos-deportivos-nacionales-comunales',
            'title' => 'Caracas recibió el Fuego Patrio en los IV Juegos Deportivos Nacionales Comunales',
            'excerpt' => 'La antorcha partió desde la autopista Valle-Coche hasta la Plaza Bolívar de Antímano, acompañados por atletas de distintas disciplinas, en el marco de los IV Juegos Deportivos Nacionales Comunales 2026.',
            'category' => 'deporte',
        ],
        'Noticia 2' => [
            'slug' => 'trabajadores-de-caracas-marchan-a-la-asamblea-en-respaldo-a-la-mesa-de-dialogo',
            'title' => 'Trabajadores de Caracas marchan a la Asamblea en respaldo a la mesa de diálogo',
            'excerpt' => 'El pueblo trabajador se desbordó por las calles de Caracas en una multitudinaria caminata hasta las puertas de la Asamblea Nacional para entregar un documento histórico de respaldo a la mesa de diálogo.',
            'category' => 'politica',
        ],
        'Noticia 3' => [
            'slug' => 'vuelos-directos-caracas-roma-suscriben-memorando-con-ita-airways-para-marzo-de-2027',
            'title' => 'Vuelos directos Caracas–Roma: suscriben memorando con ITA Airways para marzo de 2027',
            'excerpt' => 'Se suscribió un Memorando de Entendimiento con ITA Airways para restablecer los vuelos directos entre ambas capitales a partir del 30 de marzo de 2027.',
            'category' => 'nacionales',
        ],
        'Noticia 4' => [
            'slug' => 'punto-de-salud-del-campamento-ce-andres-bello-opera-en-servicio-continuo',
            'title' => 'Punto de salud del Campamento C.E. Andrés Bello opera en servicio continuo',
            'excerpt' => 'El equipo multidisciplinario brinda atención de enfermería, evaluaciones de medicina general y entrega de medicamentos de forma gratuita en el Campamento Transitorio C.E. Andrés Bello.',
            'category' => 'comunal',
        ],
        'Noticia 5' => [
            'slug' => 'liceo-abalos-acogio-taller-de-prevencion-de-embarazo-a-temprana-edad',
            'title' => 'Liceo Ábalos acogió taller de prevención de embarazo a temprana edad',
            'excerpt' => 'En el Auditorio del Liceo Ábalos de la parroquia El Valle se llevó a cabo el taller "Prevención de Embarazo a Temprana Edad y en la Adolescencia", dirigido a madres, representantes y la comunidad.',
            'category' => 'educacion',
        ],
    ];

    public function run(): void
    {
        Post::withTrashed()
            ->where('slug', 'like', 'noticia-destacada-%')
            ->forceDelete();

        $user = User::where('email', 'admin@gdc.gob.ve')->first()
            ?? User::firstOrCreate([
                'email' => 'admin@gdc.gob.ve',
            ], [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]);

        foreach (self::NOTICIAS as $folder => $data) {
            $source = public_path(self::SOURCE_DIR.'/'.$folder);

            if (! is_dir($source)) {
                $this->command?->warn("NoticiasPrecargadas: carpeta no encontrada ({$source}).");

                continue;
            }

            [$body, $txtDate] = $this->readTxt($source);
            $images = $this->images($source);

            if ($images === []) {
                $this->command?->warn("NoticiasPrecargadas: sin imágenes en {$folder}.");

                continue;
            }

            $publishedAt = $this->dateFromFilename(basename($images[0])) ?? $txtDate;

            if ($publishedAt === null) {
                $this->command?->error("NoticiasPrecargadas: sin fecha utilizable en {$folder}.");

                continue;
            }

            $publishedAt = $publishedAt instanceof Carbon ? $publishedAt : Carbon::parse($publishedAt);

            $categoria = Category::where('slug', $data['category'])->first()
                ?? Category::first();

            $existing = Post::where('slug', $data['slug'])->first();

            if ($existing !== null && $existing->featured_image !== null) {
                continue;
            }

            $post = Post::updateOrCreate(['slug' => $data['slug']], [
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'content' => $this->toHtml($body),
                'category_id' => $categoria->id,
                'user_id' => $user->id,
                'status' => 'published',
                'published_at' => $publishedAt,
                'is_featured' => false,
                'views' => $existing->views ?? 0,
            ]);

            $this->attachMedia($post, $images, $user, $publishedAt);
        }
    }

    /**
     * Copia las imágenes a uploads/YYYY/MM/, registra cada una en la tabla
     * media y arma la galería (002+ en post_media). La 001 es la portada.
     */
    private function attachMedia(Post $post, array $images, User $user, Carbon $publishedAt): void
    {
        $disk = Storage::disk('public');
        $ym = $publishedAt->format('Y/m');

        $post->postMedia()->where('type', 'image')->delete();

        $featuredPath = null;
        $position = 0;

        foreach ($images as $i => $src) {
            $seq = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $filename = $post->slug.'-'.$seq.'.'.$ext;
            $path = "uploads/{$ym}/{$filename}";

            $disk->put($path, File::get($src));
            Webp::convert($path);

            Media::create([
                'filename' => $filename,
                'original_name' => basename($src),
                'path' => $path,
                'mime_type' => File::mimeType($src) ?: 'application/octet-stream',
                'size' => filesize($src),
                'user_id' => $user->id,
            ]);

            if ($i === 0) {
                $featuredPath = $path;

                continue;
            }

            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'image',
                'path' => $path,
                'position' => ++$position,
            ]);
        }

        $post->forceFill(['featured_image' => $featuredPath])->save();
    }

    /**
     * Lee el txt de la carpeta: devuelve [cuerpo, fecha] donde la línea
     * "Fecha: dd/mm/yyyy hh:mm" (o "Fech:") se excluye del cuerpo.
     *
     * @return array{0: string, 1: string|null}
     */
    private function readTxt(string $dir): array
    {
        $files = glob($dir.'/*.txt') ?: [];
        sort($files);

        if ($files === []) {
            return ['', null];
        }

        $lines = preg_split('/\R/', file_get_contents($files[0]));
        $body = [];
        $date = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:Fecha|Fech):?\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})\s*$/u', $line, $m)) {
                $date = sprintf('%04d-%02d-%02d %02d:%02d:00', $m[3], $m[2], $m[1], $m[4], $m[5]);

                continue;
            }

            $body[] = $line;
        }

        return [implode("\n", $body), $date];
    }

    /**
     * Imágenes de la carpeta ordenadas por número de secuencia (001, 002…).
     *
     * @return array<int, string>
     */
    private function images(string $dir): array
    {
        $files = [];

        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $files = array_merge($files, glob($dir.'/*.'.$ext) ?: []);
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Extrae dd/mm/yyyy hh:mm del sello del nombre de archivo
     * ("001 1310261300.webp" → ddmmyyhhmm; "001 131020261630.jpg" →
     * ddmmyyyyhhmm). Devuelve null si el sello no es una fecha válida
     * (p. ej. los sellos 10 dígitos de las noticias 1 y 2, sin mes).
     */
    private function dateFromFilename(string $name): ?Carbon
    {
        if (! preg_match('/^\d{3}\D+(\d{6,12})\./', $name, $m)) {
            return null;
        }

        $stamp = $m[1];

        if (strlen($stamp) === 12) {
            $d = substr($stamp, 0, 2);
            $mo = substr($stamp, 2, 2);
            $y = substr($stamp, 4, 4);
            $h = substr($stamp, 8, 2);
            $mi = substr($stamp, 10, 2);
        } elseif (strlen($stamp) === 10) {
            $d = substr($stamp, 0, 2);
            $mo = substr($stamp, 2, 2);
            $y = '20'.substr($stamp, 4, 2);
            $h = substr($stamp, 6, 2);
            $mi = substr($stamp, 8, 2);
        } else {
            return null;
        }

        if (! checkdate((int) $mo, (int) $d, (int) $y) || (int) $h > 23 || (int) $mi > 59) {
            return null;
        }

        return Carbon::create((int) $y, (int) $mo, (int) $d, (int) $h, (int) $mi);
    }

    /**
     * Convierte los párrafos del txt en <p>…</p> sin tocar el texto
     * (menciones, hashtags y emojis se conservan tal cual).
     */
    private function toHtml(string $body): string
    {
        $html = '';

        foreach (preg_split('/\R\s*\R/', $body) as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            $html .= '<p>'.$block.'</p>';
        }

        return $html;
    }
}
