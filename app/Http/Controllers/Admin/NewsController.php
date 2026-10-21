<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Video;
use App\Services\Webp;
use App\Support\Audit;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NewsController extends Controller
{
    /** Límites de galería por noticia (D15). */
    public const GALLERY_IMAGE_MAX = 10;

    public const GALLERY_VIDEO_MAX = 3;

    private const YOUTUBE_URL_REGEX = '~^(https?://)?(www\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/)[A-Za-z0-9_-]{11}(&.*)?$~';

    public function index()
    {
        $posts = Post::with('category')->latest()->paginate(10);

        return view('admin.news.index', compact('posts'));
    }

    public function create()
    {
        $categories = Category::all();

        return view('admin.news.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|max:255',
            'category_id' => 'required|exists:categories,id',
            'excerpt' => 'required|string|max:1000',
            'content' => 'required|string|max:200000',
            'status' => 'nullable|in:draft,published',
            'published_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'attach_video' => 'nullable|array',
            'attach_video.*' => 'integer|exists:videos,id',
            'new_video_urls' => 'nullable|string',
            'new_video_files' => 'nullable|array',
            'new_video_files.*' => 'file|mimes:mp4,webm,ogg|max:102400',
        ]);

        $this->assertGalleryLimits($request);

        $data['slug'] = Str::slug($request->title);
        $data['user_id'] = auth()->id();
        $data['featured_image'] = $this->resolveFeaturedImage($request);
        unset($data['featured_image_file']);

        $data['status'] = $this->resolveStatus($data['status'] ?? null);

        if ($data['status'] === 'published') {
            $data['published_at'] = $request->input('published_at') ?: now();
        } else {
            $data['published_at'] = null;
        }

        $post = Post::create($data);
        Audit::log('news.created', Post::class, $post->id, "Noticia creada: {$post->title} ({$post->status})", [
            'status' => $post->status,
        ]);

        $this->syncGallery($request, $post);
        $this->flashStrippedImagesWarning($request, $post);

        return redirect('/admin/noticias')->with('success', 'Noticia creada correctamente.');
    }

    public function edit(Post $post)
    {
        $categories = Category::all();

        return view('admin.news.edit', compact('post', 'categories'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title' => 'required|max:255',
            'category_id' => 'required|exists:categories,id',
            'excerpt' => 'required|string|max:1000',
            'content' => 'required|string|max:200000',
            'status' => 'nullable|in:draft,published',
            'published_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
            'remove_image' => 'nullable|boolean',
            'remove_media' => 'nullable|array',
            'remove_media.*' => 'integer',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'attach_video' => 'nullable|array',
            'attach_video.*' => 'integer|exists:videos,id',
            'new_video_urls' => 'nullable|string',
            'new_video_files' => 'nullable|array',
            'new_video_files.*' => 'file|mimes:mp4,webm,ogg|max:102400',
        ]);

        $removeIds = array_map('intval', $request->input('remove_media') ?? []);
        if ($removeIds !== []) {
            $owned = $post->postMedia()->whereIn('id', $removeIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($owned) !== count(array_unique($removeIds))) {
                throw ValidationException::withMessages([
                    'remove_media' => 'Selección de galería inválida.',
                ]);
            }
            $removeIds = $owned;
        }
        $data['remove_media'] = $removeIds;

        $this->assertGalleryLimits($request, $post);

        $canApprove = auth()->user()->hasPermission('news.approve');

        $data['slug'] = Str::slug($request->title);
        $data['is_featured'] = $request->has('is_featured');
        $data['status'] = $this->resolveStatus($data['status'] ?? null, $post->status);

        if ($data['status'] === 'published') {
            if ($canApprove) {
                // Campo vacío o fecha pasada = publicar ya; fecha futura = programar.
                $data['published_at'] = $request->input('published_at') ?: now();
            } else {
                // Sin permiso el estado no cambia: conserva la fecha actual
                // (una noticia programada no se adelanta al editarla).
                $data['published_at'] = $post->published_at ?? now();
            }
        } else {
            $data['published_at'] = null;
        }

        if ($request->boolean('remove_image')) {
            $data['featured_image'] = null;
        } elseif ($request->hasFile('featured_image_file')) {
            $data['featured_image'] = $this->storeFeaturedFile($request->file('featured_image_file'));
        } elseif ($request->filled('featured_image')) {
            $data['featured_image'] = $request->input('featured_image');
        } else {
            unset($data['featured_image']);
        }
        unset($data['featured_image_file'], $data['remove_image'], $data['remove_media']);

        $originalStatus = $post->status;
        $post->update($data);

        if ($removeIds !== []) {
            $post->postMedia()->whereIn('id', $removeIds)->get()->each(function (PostMedia $media) {
                // Los archivos NO se borran (paridad con remove_image de portada):
                // pueden seguir referenciados por la biblioteca de medios.
                $media->delete();
            });
        }
        $this->syncGallery($request, $post);
        $this->flashStrippedImagesWarning($request, $post);

        Audit::log('news.updated', Post::class, $post->id, "Noticia actualizada: {$post->title} ({$post->status})", [
            'status' => $post->status,
            'previous_status' => $originalStatus,
            'published_now' => $post->status === 'published' && $originalStatus !== 'published',
            'gallery_media' => $post->postMedia()->count(),
        ]);

        if ($originalStatus !== 'published' && $post->status === 'published') {
            Audit::log('news.approved', Post::class, $post->id, "Noticia aprobada: {$post->title}", [
                'published_at' => $post->published_at?->toDateTimeString(),
                'scheduled' => (bool) $post->published_at?->isFuture(),
            ]);
        }

        return redirect('/admin/noticias')->with('success', 'Noticia actualizada correctamente.');
    }

    public function approve(Post $post)
    {
        if ($post->status !== 'draft') {
            return redirect('/admin/noticias')->with('warning', 'Solo se pueden aprobar noticias en borrador.');
        }

        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        Audit::log('news.approved', Post::class, $post->id, "Noticia aprobada: {$post->title}", [
            'published_at' => $post->published_at?->toDateTimeString(),
            'scheduled' => false,
        ]);

        return redirect('/admin/noticias')->with('success', "Noticia aprobada: {$post->title}");
    }

    public function bulkPublish(Request $request)
    {
        $data = $request->validate([
            'posts' => ['nullable', 'array'],
            'posts.*' => ['integer', Rule::exists('posts', 'id')->whereNull('deleted_at')],
        ]);

        $ids = array_map('intval', $data['posts'] ?? []);

        if ($ids === []) {
            return redirect('/admin/noticias')->with('warning', 'Selecciona al menos una noticia para publicar.');
        }

        $drafts = Post::whereIn('id', $ids)->where('status', 'draft')->get();
        $approved = 0;

        foreach ($drafts as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            Audit::log('news.approved', Post::class, $post->id, "Noticia aprobada (masiva): {$post->title}", [
                'published_at' => $post->published_at?->toDateTimeString(),
                'scheduled' => false,
            ]);
            $approved++;
        }

        $omitted = count($ids) - $approved;

        if ($approved === 0) {
            return redirect('/admin/noticias')->with(
                'warning',
                "Ninguna publicada: {$omitted} seleccionada(s) omitida(s), solo se publican borradores."
            );
        }

        $message = "{$approved} noticia(s) publicada(s).";
        if ($omitted > 0) {
            $message .= " {$omitted} omitida(s), solo se publican borradores.";
        }

        return redirect('/admin/noticias')->with('success', $message);
    }

    public function move(Request $request, Post $post, PostMedia $media)
    {
        $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        abort_unless($media->post_id === $post->id, 404);

        $ordered = $post->postMedia()->get();
        $ordered->each(fn (PostMedia $m, int $index) => $m->position !== $index ? $m->update(['position' => $index]) : null);

        $ids = $ordered->pluck('id')->all();
        $pos = array_search($media->id, $ids, true);

        $swap = $request->input('direction') === 'up' ? $pos - 1 : $pos + 1;
        if ($swap >= 0 && $swap < count($ids)) {
            // Update vía query builder: $media y el vecino pueden estar
            // desactualizados en memoria tras la normalización (dirty-check
            // de Eloquent los ignoraría).
            PostMedia::whereKey($ids[$swap])->update(['position' => $pos]);
            PostMedia::whereKey($media->id)->update(['position' => $swap]);
        }

        return redirect()->back()->with('success', 'Orden de galería actualizado.');
    }

    public function destroy(Post $post)
    {
        Audit::log('news.deleted', Post::class, $post->id, "Noticia eliminada: {$post->title}");
        $post->delete();

        return redirect('/admin/noticias')->with('success', 'Noticia eliminada correctamente.');
    }

    private function mediaImageRule()
    {
        return Rule::exists('media', 'path')->where(
            fn ($q) => $q->where('mime_type', 'like', 'image/%')
        );
    }

    /**
     * Valida los topes de galería: máximo GALLERY_IMAGE_MAX imágenes y
     * GALLERY_VIDEO_MAX videos por noticia (contando lo existente menos lo
     * que se elimina en esta misma operación).
     */
    private function assertGalleryLimits(Request $request, ?Post $post = null): void
    {
        $newImages = count($request->file('gallery_images') ?? []);
        $removeIds = array_map('intval', $request->input('remove_media') ?? []);

        $currentImages = 0;
        $removeImages = 0;
        $currentVideos = 0;
        $removeVideos = 0;

        if ($post) {
            $mediaRows = $post->postMedia()->get();
            $currentImages = $mediaRows->where('type', 'image')->count();
            $currentVideos = $mediaRows->where('type', 'video')->count();
            $removeImages = $mediaRows->whereIn('id', $removeIds)->where('type', 'image')->count();
            $removeVideos = $mediaRows->whereIn('id', $removeIds)->where('type', 'video')->count();
        }

        $totalImages = $currentImages - $removeImages + $newImages;
        if ($totalImages > self::GALLERY_IMAGE_MAX) {
            throw ValidationException::withMessages([
                'gallery_images' => 'La galería admite máximo '.self::GALLERY_IMAGE_MAX.' imágenes por noticia.',
            ]);
        }

        $urls = $this->newVideoUrls($request);
        $newVideos = count($urls) + count($request->file('new_video_files') ?? []);
        $attached = count($request->input('attach_video') ?? []);
        $totalVideos = $currentVideos - $removeVideos + $attached + $newVideos;
        if ($totalVideos > self::GALLERY_VIDEO_MAX) {
            throw ValidationException::withMessages([
                'new_video_urls' => 'Máximo '.self::GALLERY_VIDEO_MAX.' videos por noticia.',
            ]);
        }
    }

    /**
     * Persiste la galería: imágenes nuevas (disco + biblioteca + post_media),
     * videos a enlazar y videos nuevos (que se crean en el módulo de videos
     * para gestionarlos desde allí; is_featured lo decide su módulo).
     */
    private function syncGallery(Request $request, Post $post): void
    {
        $position = (int) ($post->postMedia()->max('position') ?? -1) + 1;

        foreach ($request->file('gallery_images') ?? [] as $file) {
            $path = $this->storeFeaturedFile($file);
            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'image',
                'path' => $path,
                'position' => $position++,
            ]);
        }

        foreach (array_map('intval', $request->input('attach_video') ?? []) as $videoId) {
            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'video',
                'video_id' => $videoId,
                'position' => $position++,
            ]);
        }

        $urls = $this->newVideoUrls($request);
        $files = $request->file('new_video_files') ?? [];
        $total = count($urls) + count($files);
        $counter = 0;

        foreach ($urls as $url) {
            $counter++;
            $video = Video::create([
                'title' => $post->title.($total > 1 ? " ({$counter})" : ''),
                'type' => 'youtube',
                'url' => $url,
                'user_id' => auth()->id(),
            ]);
            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'video',
                'video_id' => $video->id,
                'position' => $position++,
            ]);
        }

        foreach ($files as $file) {
            $counter++;
            $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['mp4', 'webm', 'ogg']);
            $path = $file->storeAs('videos', $filename, 'public');
            $video = Video::create([
                'title' => $post->title.($total > 1 ? " ({$counter})" : ''),
                'type' => 'file',
                'path' => $path,
                'user_id' => auth()->id(),
            ]);
            PostMedia::create([
                'post_id' => $post->id,
                'type' => 'video',
                'video_id' => $video->id,
                'position' => $position++,
            ]);
        }
    }

    /** URLs de YouTube nuevas (una por línea, líneas vacías ignoradas). */
    private function newVideoUrls(Request $request): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $request->input('new_video_urls')) ?: [];
        $urls = array_values(array_filter(array_map('trim', $lines)));

        foreach ($urls as $url) {
            if (! preg_match(self::YOUTUBE_URL_REGEX, $url)) {
                throw ValidationException::withMessages([
                    'new_video_urls' => 'La URL «'.$url.'» no parece un video de YouTube válido.',
                ]);
            }
        }

        return $urls;
    }

    /**
     * Aviso visible (D15): si al guardar se eliminaron imágenes pegadas en el
     * texto, se avisa junto al redirect (admin/news/index ya renderiza
     * session('warning')). Se concatena con un warning previo si existe.
     */
    private function flashStrippedImagesWarning(Request $request, Post $post): void
    {
        $raw = substr_count(strtolower((string) $request->input('content')), '<img');
        $stored = substr_count(strtolower((string) $post->content), '<img');
        $stripped = $raw - $stored;

        if ($stripped <= 0) {
            return;
        }

        $message = "Se eliminaron {$stripped} imagen(es) del texto. Usa la imagen de portada o la galería para mostrar imágenes.";
        $previous = session()->get('warning');

        session()->flash('warning', $previous ? $previous.' '.$message : $message);
    }

    private function resolveStatus(?string $requested, ?string $current = null): string
    {
        if (auth()->user()->hasPermission('news.approve')) {
            return $requested ?? $current ?? 'draft';
        }

        if ($requested === 'published' && $current !== 'published') {
            session()->flash(
                'warning',
                'No tienes permiso para publicar. La noticia se guardó como borrador y queda pendiente de aprobación.'
            );
        }

        // Sin news.approve el estado de publicación no cambia (ni se sube ni se baja).
        return $current ?? 'draft';
    }

    private function resolveFeaturedImage(Request $request): ?string
    {
        if ($request->hasFile('featured_image_file')) {
            return $this->storeFeaturedFile($request->file('featured_image_file'));
        }

        return $request->filled('featured_image') ? $request->input('featured_image') : null;
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
}
