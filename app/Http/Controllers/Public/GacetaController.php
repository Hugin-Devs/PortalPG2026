<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gaceta;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GacetaController extends Controller
{
    public function index()
    {
        $gacetas = Cache::remember(CacheKeys::GACETAS_INDEX, CacheKeys::ttl(), function () {
            return Gaceta::orderBy('fecha', 'desc')
                ->orderByRaw('CAST(numero AS UNSIGNED) DESC, id DESC')
                ->paginate(12);
        });

        $seo = [
            'title' => 'Gacetas Oficiales',
            'description' => 'Gacetas Oficiales del Gobierno de Distrito Capital.',
            'url' => route('gacetas.index'),
        ];

        return view('public.gacetas', compact('gacetas', 'seo'));
    }
}
