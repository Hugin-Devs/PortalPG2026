<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Partner;

class EnteController extends Controller
{
    public function index()
    {
        $entes = Partner::where('is_active', true)->orderBy('order')->get();
        return view('public.entes', compact('entes'));
    }

    public function show($slug)
    {
        $ente = Partner::where('slug', $slug)->where('is_active', true)->firstOrFail();
        return view('public.ente-show', compact('ente'));
    }
}
