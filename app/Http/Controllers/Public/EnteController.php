<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EnteController extends Controller
{
    public function index()
    {
        $entes = Cache::remember(CacheKeys::ENTES_INDEX, CacheKeys::ttl(), function () {
            return Partner::where('is_active', true)->orderBy('order')->get();
        });

        $seo = [
            'title' => 'Entes Afiliados',
            'description' => 'Entes afiliados al Gobierno de Distrito Capital.',
            'url' => route('entes.index'),
        ];

        return view('public.entes', compact('entes', 'seo'));
    }

    public function show($slug)
    {
        $ente = Partner::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $seo = [
            'title' => $ente->name,
            'description' => Str::limit(strip_tags($ente->description), 160),
            'image' => $ente->logo ? 'storage/'.$ente->logo : null,
            'url' => route('entes.show', $ente->slug),
        ];

        return view('public.ente-show', compact('ente', 'seo'));
    }
}
