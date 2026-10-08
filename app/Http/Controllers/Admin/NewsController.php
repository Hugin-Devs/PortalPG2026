<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Services\Webp;
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
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
        ]);

        $data['slug'] = Str::slug($request->title);
        $data['user_id'] = auth()->id();
        $data['featured_image'] = $this->resolveFeaturedImage($request);
        unset($data['featured_image_file']);

        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = now();
        }

        Post::create($data);

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
            'is_featured' => 'nullable|boolean',
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'featured_image' => ['nullable', 'string', $this->mediaImageRule()],
            'remove_image' => 'nullable|boolean',
        ]);

        $data['slug'] = Str::slug($request->title);
        $data['is_featured'] = $request->has('is_featured');

        if (($data['status'] ?? 'draft') === 'published' && ! $post->published_at) {
            $data['published_at'] = now();
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

        $post->update($data);

        return redirect('/admin/noticias')->with('success', 'Noticia actualizada correctamente.');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect('/admin/noticias')->with('success', 'Noticia eliminada correctamente.');
    }

    private function mediaImageRule()
    {
        return Rule::exists('media', 'path')->where(
            fn ($q) => $q->where('mime_type', 'like', 'image/%')
        );
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
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
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
