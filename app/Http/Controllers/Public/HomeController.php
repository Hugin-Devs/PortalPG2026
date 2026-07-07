<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredPosts = \App\Models\Post::where('is_featured', true)->where('status', 'published')->latest()->take(3)->get();
        $latestPosts = \App\Models\Post::where('status', 'published')->latest()->take(6)->get();
        $categories = \App\Models\Category::all();

        return view('public.home', compact('featuredPosts', 'latestPosts', 'categories'));
    }}
