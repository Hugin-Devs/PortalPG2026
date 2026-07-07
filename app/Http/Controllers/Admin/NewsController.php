<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index()
    {
        $posts = \App\Models\Post::latest()->paginate(10);
        return view('admin.news.index', compact('posts'));
    }

    public function create()
    {
        $categories = \App\Models\Category::all();
        return view('admin.news.create', compact('categories'));
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'title' => 'required',
            'excerpt' => 'required',
            'content' => 'required',
            'category_id' => 'required',
        ]);
        
        $data['slug'] = \Illuminate\Support\Str::slug($request->title);
        $data['user_id'] = auth()->id();
        
        \App\Models\Post::create($data);
        return redirect('/admin/noticias')->with('success', 'Noticia creada.');
    }
}
