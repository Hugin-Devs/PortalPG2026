<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Video;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        $ttl = CacheKeys::ttl();

        $seo = [
            'title' => 'Inicio',
            'description' => 'Portal institucional del Gobierno de Distrito Capital. Noticias, gacetas oficiales, entes afiliados e información de contacto.',
            'url' => route('home'),
        ];

        $featuredPosts = Cache::remember(CacheKeys::HOME_FEATURED, $ttl, fn () => Post::with('category')
            ->where('is_featured', true)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get());

        $latestPosts = Cache::remember(CacheKeys::HOME_LATEST, $ttl, fn () => Post::with('category')
            ->where('status', 'published')
            ->latest()
            ->take(4)
            ->get());

        $categories = Cache::remember(CacheKeys::HOME_CATEGORIES, $ttl, fn () => Category::where('is_active', true)
            ->orderBy('order')
            ->get());

        $mostRead = Cache::remember(CacheKeys::HOME_MOST_READ, $ttl, fn () => Post::where('status', 'published')
            ->orderBy('views', 'desc')
            ->take(5)
            ->get());

        $videos = Cache::remember(CacheKeys::HOME_VIDEOS, $ttl, fn () => Video::where('is_featured', true)->get());

        return view('public.home', compact(
            'featuredPosts', 'latestPosts', 'categories', 'mostRead', 'videos', 'seo'
        ));
    }
}
