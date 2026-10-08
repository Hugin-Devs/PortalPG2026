<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Category;
use App\Models\Video;

class HomeController extends Controller
{
    public function index()
    {
        $featuredPosts = Post::with('category')
            ->where('is_featured', true)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        $latestPosts = Post::with('category')
            ->where('status', 'published')
            ->latest()
            ->take(4)
            ->get();

        $categories = Category::where('is_active', true)
            ->orderBy('order')
            ->get();

        $mostRead = Post::where('status', 'published')
            ->orderBy('views', 'desc')
            ->take(5)
            ->get();

        $videos = Video::where('is_featured', true)->get();

        return view('public.home', compact(
            'featuredPosts', 'latestPosts', 'categories', 'mostRead', 'videos'
        ));
    }
}
