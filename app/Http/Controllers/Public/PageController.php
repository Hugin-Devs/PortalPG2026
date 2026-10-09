<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function show($slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $seo = [
            'title' => $page->title,
            'description' => Str::limit(strip_tags($page->content), 160),
            'url' => route('page.show', $page->slug),
        ];

        return view('public.page', compact('page', 'seo'));
    }
}
