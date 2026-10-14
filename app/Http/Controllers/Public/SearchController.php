<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        $posts = Post::with('category')
            ->live()
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%");
            })
            ->recent()
            ->paginate(10);

        $seo = [
            'title' => 'Buscar: '.$q,
            'description' => 'Resultados de búsqueda en el portal del Gobierno de Distrito Capital.',
            'url' => route('search', ['q' => $q]),
        ];

        return view('public.search', compact('posts', 'q', 'seo'));
    }
}
