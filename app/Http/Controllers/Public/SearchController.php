<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');

        // MySQL (utf8mb4_unicode_ci) y SQLite LIKE ya distinguen mayúsculas
        // por defecto; en PostgreSQL LIKE es sensible a mayúsculas, así que
        // allí se usa ILIKE para preservar el comportamiento original.
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $posts = Post::with('category', 'postMedia.video')
            ->live()
            ->where(function ($query) use ($q, $operator) {
                $query->where('title', $operator, "%{$q}%")
                    ->orWhere('content', $operator, "%{$q}%")
                    ->orWhere('excerpt', $operator, "%{$q}%");
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
