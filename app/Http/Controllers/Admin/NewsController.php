<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Services\Webp;
use App\Support\Audit;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
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
            'excerpt' => 'required',
            'content' => 'required',
            'status' => 'nullable|in:draft,published',
            'published_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
        ]);

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
            'excerpt' => 'required',
            'content' => 'required',
            'status' => 'nullable|in:draft,published',
            'published_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
            'remove_image' => 'nullable|boolean',
        ]);

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
        unset($data['featured_image_file'], $data['remove_image']);

        $originalStatus = $post->status;
        $post->update($data);
        Audit::log('news.updated', Post::class, $post->id, "Noticia actualizada: {$post->title} ({$post->status})", [
            'status' => $post->status,
            'previous_status' => $originalStatus,
            'published_now' => $post->status === 'published' && $originalStatus !== 'published',
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
        $path = $file->storeAs('uploads', $filename, 'public');
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
