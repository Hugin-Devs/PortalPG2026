<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::with('category')
            ->where('status', 'published')
            ->latest()
            ->paginate(9);

        $categories = $this->categoriesForNav();

        return view('public.news', compact('posts', 'categories'));
    }

    public function show($slug)
    {
        $post = Post::with('category')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $post->increment('views');
        DB::statement(
            'INSERT INTO daily_views (day, post_id, count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE count = count + 1',
            [now()->toDateString(), $post->id]
        );

        $related = Post::where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        return view('public.news-show', compact('post', 'related'));
    }

    public function category($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $posts = Post::with('category')
            ->where('category_id', $category->id)
            ->where('status', 'published')
            ->latest()
            ->paginate(9);

        $categories = $this->categoriesForNav();

        return view('public.news-category', compact('posts', 'category', 'categories'));
    }

    private function categoriesForNav()
    {
        return Category::where('is_active', true)
            ->withCount(['posts as published_count' => function ($q) {
                $q->where('status', 'published');
            }])
            ->having('published_count', '>', 0)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }
}
