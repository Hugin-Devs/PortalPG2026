<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::with('category', 'postMedia.video')
            ->live()
            ->recent()
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
        $post = Post::with('category', 'postMedia.video')
            ->where('slug', $slug)
            ->live()
            ->firstOrFail();

        $post->increment('views');

        // El ranking del home viene de un caché con TTL largo
        // (CacheKeys::HOME_MOST_READ) y el incremento no pasa por el
        // observer `saved`, así que hay que invalidarlo a mano para que
        // "Lo más leído" no pinte números ni orden desfasados.
        CacheKeys::flushMostRead();

        $day = now()->toDateString();
        // Upsert atómico sobre el índice único (day, post_id): en PostgreSQL
        // compila a ON CONFLICT ... DO UPDATE y elimina la carrera
        // insert/increment del enfoque anterior.
        DB::table('daily_views')->upsert(
            [['day' => $day, 'post_id' => $post->id, 'count' => 1]],
            ['day', 'post_id'],
            ['count' => DB::raw('daily_views.count + 1')]
        );

        $related = Post::where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->live()
            ->recent()
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

        $posts = Post::with('category', 'postMedia.video')
            ->where('category_id', $category->id)
            ->live()
            ->recent()
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
                    $q->live();
                }])
                ->whereHas('posts', function ($q) {
                    $q->live();
                })
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        });
    }
}
