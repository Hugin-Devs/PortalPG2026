<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\CacheKeys;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::with('category')
            ->where('status', 'published')
            ->latest()
            ->paginate(9);

        $categories = $this->categoriesForNav();

        $seo = [
            'title' => 'Noticias',
            'description' => 'Últimas noticias del Gobierno de Distrito Capital.',
            'url' => route('news.index'),
        ];

        return view('public.news', compact('posts', 'categories', 'seo'));
    }

    public function show($slug)
    {
        $post = Post::with('category')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $post->increment('views');

        $day = now()->toDateString();
        $updated = DB::table('daily_views')
            ->where('day', $day)
            ->where('post_id', $post->id)
            ->increment('count');

        if (! $updated) {
            try {
                DB::table('daily_views')->insert([
                    'day' => $day,
                    'post_id' => $post->id,
                    'count' => 1,
                ]);
            } catch (QueryException $e) {
                DB::table('daily_views')
                    ->where('day', $day)
                    ->where('post_id', $post->id)
                    ->increment('count');
            }
        }

        $related = Post::where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        $seo = [
            'title' => $post->title,
            'description' => Str::limit(strip_tags($post->excerpt), 160),
            'image' => $post->featured_image ? 'storage/'.$post->featured_image : null,
            'url' => route('news.show', $post->slug),
            'type' => 'article',
        ];

        return view('public.news-show', compact('post', 'related', 'seo'));
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

        $seo = [
            'title' => 'Noticias: '.$category->name,
            'description' => 'Noticias de la categoría '.$category->name.' en el portal del Gobierno de Distrito Capital.',
            'url' => route('news.category', $category->slug),
        ];

        return view('public.news-category', compact('posts', 'category', 'categories', 'seo'));
    }

    private function categoriesForNav()
    {
        return Cache::remember(CacheKeys::NEWS_CATEGORIES_NAV, CacheKeys::ttl(), function () {
            return Category::where('is_active', true)
                ->withCount(['posts as published_count' => function ($q) {
                    $q->where('status', 'published');
                }])
                ->having('published_count', '>', 0)
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        });
    }
}
