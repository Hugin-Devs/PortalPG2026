<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        ]);

        $data['slug'] = Str::slug($request->title);
        $data['user_id'] = auth()->id();

        if ($data['status'] ?? 'draft' === 'published') {
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
        ]);

        $data['slug'] = Str::slug($request->title);
        $data['is_featured'] = $request->has('is_featured');

        if (($data['status'] ?? 'draft') === 'published' && !$post->published_at) {
            $data['published_at'] = now();
        }

        $post->update($data);
        return redirect('/admin/noticias')->with('success', 'Noticia actualizada correctamente.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect('/admin/noticias')->with('success', 'Noticia eliminada correctamente.');
    }
}
