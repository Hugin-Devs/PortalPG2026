<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Services\InstagramImport;
use App\Services\Webp;
use App\Support\Audit;
use App\Support\Uploads;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsImportController extends Controller
{
    /** Máx. imágenes del post: 1 posible portada + GALLERY_IMAGE_MAX en galería. */
    public const IMAGE_URLS_MAX = 1 + NewsController::GALLERY_IMAGE_MAX;

    public function __construct(private readonly InstagramImport $instagram) {}

    public function show()
    {
        $categories = Category::all();

        return view('admin.news.import', compact('categories'));
    }

    public function store(Request $request)
    {
        $canonical = InstagramImport::normalizePostUrl((string) $request->input('source_url'));

        if ($canonical === null) {
            throw ValidationException::withMessages([
                'source_url' => 'La URL no parece un post de Instagram válido (usa /p/, /reel/ o /tv/).',
            ]);
        }

        if (Post::where('source_url', $canonical)->exists()) {
            return redirect('/admin/noticias')->with(
                'warning',
                'Esta publicación de Instagram ya fue importada.'
            );
        }

        $this->normalizeImageUrlsInput($request);

        $extractFailed = false;

        if (! $request->filled('title') || ! $request->filled('content')) {
            $meta = $this->instagram->fetchMeta($canonical);

            if ($meta !== null) {
                $merge = [];

                if (! $request->filled('title') && ! empty($meta['title'])) {
                    $merge['title'] = Str::limit($meta['title'], 255, '');
                }

                if (! $request->filled('content') && ! empty($meta['content'])) {
                    $merge['content'] = $meta['content'];
                }

                // og:image → image_urls (galería/candidata a portada), nunca hero automático.
                if (! $request->filled('image_urls') && ! empty($meta['image_url'])) {
                    $merge['image_urls'] = [$meta['image_url']];
                }

                if (! $request->filled('published_at') && ! empty($meta['published_at'])) {
                    $merge['published_at'] = $this->toDatetimeLocal($meta['published_at']);
                }

                if ($merge !== []) {
                    $request->merge($merge);
                }
            } else {
                $extractFailed = true;
            }
        }

        if (! $request->filled('excerpt') && $request->filled('content')) {
            $plain = trim(strip_tags((string) $request->input('content')));
            $request->merge(['excerpt' => Str::limit($plain, 1000, '')]);
        }

        if ($extractFailed) {
            session()->flash(
                'warning',
                'No se pudo extraer automáticamente desde Instagram (login wall). Completa los campos a mano o usa el bookmarklet.'
            );
        }

        $allowImageRule = function ($attribute, $value, $fail) {
            if (! InstagramImport::isAllowedImageUrl((string) $value)) {
                $fail('La URL de la imagen no es de un host permitido de Instagram.');
            }
        };

        $data = $request->validate([
            'source_url' => 'required|string|max:500',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'excerpt' => 'required|string|max:1000',
            'content' => 'required|string|max:200000',
            'image_urls' => ['nullable', 'array', 'max:'.self::IMAGE_URLS_MAX],
            'image_urls.*' => ['string', 'max:2000', $allowImageRule],
            'featured_url' => ['nullable', 'string', 'max:2000', $allowImageRule, function ($attribute, $value, $fail) use ($request) {
                if ($value === null || $value === '') {
                    return;
                }
                $imageUrls = array_values(array_filter((array) $request->input('image_urls', []), 'is_string'));
                if (! in_array($value, $imageUrls, true)) {
                    $fail('La imagen elegida como portada debe estar entre las detectadas.');
                }
            }],
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'published_at' => 'nullable|date',
        ]);

        $warnings = [];

        if (session()->has('warning')) {
            $warnings[] = (string) session('warning');
        }

        $imageUrls = array_values(array_filter((array) ($data['image_urls'] ?? []), 'is_string'));

        [$featuredImage, $galleryUrls] = $this->resolveFeaturedAndGallery($request, $imageUrls, $warnings);

        $slug = Str::slug($data['title']);
        if ($slug === '' || Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = ($slug !== '' ? $slug.'-' : 'noticia-').Str::lower(Str::random(6));
        }

        try {
            $post = Post::create([
                'title' => $data['title'],
                'slug' => $slug,
                'excerpt' => $data['excerpt'],
                'content' => $data['content'],
                'category_id' => $data['category_id'],
                'user_id' => auth()->id(),
                'status' => 'draft',
                'published_at' => $data['published_at'] ?? null,
                'featured_image' => $featuredImage,
                'source' => 'instagram',
                'source_url' => $canonical,
            ]);
        } catch (QueryException $e) {
            // Carrera contra el índice único parcial de source_url.
            if (str_contains($e->getMessage(), 'source_url')) {
                return redirect('/admin/noticias')->with(
                    'warning',
                    'Esta publicación de Instagram ya fue importada.'
                );
            }

            throw $e;
        }

        $galleryCount = $this->importGallery($post, $galleryUrls, $warnings);

        Audit::log('news.imported', Post::class, $post->id, "Noticia importada desde Instagram: {$post->title}", [
            'source_url' => $post->source_url,
            'has_featured_image' => (bool) $featuredImage,
            'featured_from' => $request->hasFile('featured_image_file')
                ? 'upload'
                : (($request->input('featured_url') ?? '') !== '' ? 'selected' : null),
            'gallery_count' => $galleryCount,
            'published_at' => $post->published_at?->toDateTimeString(),
        ]);

        $message = 'Noticia importada como borrador. Revisa y aprueba cuando corresponda.';

        $redirect = redirect("/admin/noticias/{$post->id}/editar")->with('success', $message);

        if ($warnings !== []) {
            $redirect->with('warning', implode(' ', array_unique($warnings)));
        }

        return $redirect;
    }

    /**
     * El form envía image_urls como string JSON (hidden del bookmarklet).
     * Lo convierte a array antes de la validación.
     */
    private function normalizeImageUrlsInput(Request $request): void
    {
        $raw = $request->input('image_urls');

        if (! is_string($raw) || $raw === '') {
            if ($raw !== null && ! is_array($raw)) {
                $request->merge(['image_urls' => []]);
            }

            return;
        }

        $decoded = json_decode($raw, true);

        $request->merge([
            'image_urls' => is_array($decoded)
                ? array_values(array_filter($decoded, 'is_string'))
                : [],
        ]);
    }

    /**
     * ISO 8601 (UTC de IG) → datetime-local de Caracas (Y-m-d\TH:i)
     * para el input del form. Devuelve null si no se puede parsear.
     */
    private function toDatetimeLocal(string $iso): ?string
    {
        try {
            return Carbon::parse($iso)
                ->setTimezone(config('app.timezone', 'America/Caracas'))
                ->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Portada (opcional, elegida por el editor):
     *   1. upload (featured_image_file) → manda; TODAS image_urls → galería
     *   2. featured_url (radio entre image_urls) → descarga esa; resto → galería
     *   3. nada → featured_image null; TODAS image_urls → galería
     * El bookmarklet NO fija portada automática.
     *
     * @param  list<string>  $imageUrls
     * @param  list<string>  $warnings
     * @return array{0: ?string, 1: list<string>} [featured_path, gallery_urls]
     */
    private function resolveFeaturedAndGallery(Request $request, array $imageUrls, array &$warnings): array
    {
        if ($request->hasFile('featured_image_file')) {
            return [$this->storeFeaturedFile($request->file('featured_image_file')), $imageUrls];
        }

        $featuredUrl = (string) $request->input('featured_url', '');

        if ($featuredUrl !== '' && in_array($featuredUrl, $imageUrls, true)) {
            $downloaded = $this->instagram->downloadImage($featuredUrl);

            if ($downloaded !== null) {
                $gallery = array_values(array_filter($imageUrls, fn ($u) => $u !== $featuredUrl));

                return [$this->storeDownloadedImage($downloaded), $gallery];
            }

            $warnings[] = 'No se pudo descargar la imagen elegida como portada; se importa en galería si es posible.';
        }

        return [null, $imageUrls];
    }

    /**
     * Descarga y persiste las imágenes del carrusel en post_media.
     * Fallo individual → warning + continúa. Tope GALLERY_IMAGE_MAX.
     *
     * @param  list<string>  $galleryUrls
     * @param  list<string>  $warnings
     * @return int imágenes importadas a la galería
     */
    private function importGallery(Post $post, array $galleryUrls, array &$warnings): int
    {
        $position = 0;
        $imported = 0;

        foreach ($galleryUrls as $url) {
            if ($position >= NewsController::GALLERY_IMAGE_MAX) {
                $warnings[] = 'Carrusel con más de '.NewsController::GALLERY_IMAGE_MAX.' imágenes: solo se importaron las primeras. Añade el resto a mano si hace falta.';

                break;
            }

            $downloaded = $this->instagram->downloadImage($url);

            if ($downloaded === null) {
                $warnings[] = 'No se pudo descargar una imagen del carrusel de Instagram; súbela a la galería manualmente.';

                continue;
            }

            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'image',
                'path' => $this->storeDownloadedImage($downloaded),
                'position' => $position++,
            ]);
            $imported++;
        }

        return $imported;
    }

    private function storeFeaturedFile($file): string
    {
        $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $path = Uploads::storeDated($file, $filename);
        Webp::convert($path);

        Media::create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'user_id' => auth()->id(),
        ]);

        return $path;
    }

    /**
     * @param  array{binary: string, mime: string, ext: string}  $downloaded
     */
    private function storeDownloadedImage(array $downloaded): string
    {
        $filename = time().'_'.uniqid().'.'.$downloaded['ext'];
        $path = 'uploads/'.date('Y/m').'/'.$filename;

        \Storage::disk('public')->put($path, $downloaded['binary']);
        Webp::convert($path);

        Media::create([
            'filename' => $filename,
            'original_name' => 'instagram-'.$filename,
            'path' => $path,
            'mime_type' => $downloaded['mime'],
            'size' => strlen($downloaded['binary']),
            'user_id' => auth()->id(),
        ]);

        return $path;
    }
}
