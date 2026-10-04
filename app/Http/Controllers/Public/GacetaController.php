<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gaceta;

class GacetaController extends Controller
{
    public function index()
    {
        $gacetas = Gaceta::orderBy('fecha', 'desc')
            ->orderByRaw('CAST(numero AS UNSIGNED) DESC, id DESC')
            ->paginate(12);

        return view('public.gacetas', compact('gacetas'));
    }
}
