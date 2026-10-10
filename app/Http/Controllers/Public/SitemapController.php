<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Gaceta;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', config('portal.sitemap_cache_ttl', 3600), function () {
            $urls = $this->collectUrls();

            return view('public.sitemap', compact('urls'))->render();
        });

        return response($xml, 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function collectUrls(): array
    {
        $urls = [];

        $add = function (string $loc, string $priority = '0.5', string $changefreq = 'weekly', ?string $lastmod = null) use (&$urls): void {
            $urls[] = [
                'loc' => $loc,
                'priority' => $priority,
                'changefreq' => $changefreq,
                'lastmod' => $lastmod ?? now()->toDateString(),
            ];
        };

        $add(route('home'), '1.0', 'daily');
        $add(route('news.index'), '0.9', 'daily');
        $add(route('entes.index'), '0.7', 'weekly');
        $add(route('about.index'), '0.6', 'monthly');
        $add(route('contact.index'), '0.6', 'monthly');
        $add(route('gacetas.index'), '0.8', 'weekly');

        Post::live()
            ->select('id', 'slug', 'updated_at')
            ->latest('updated_at')
            ->chunkById(100, function ($posts) use ($add) {
                foreach ($posts as $post) {
                    $add(route('news.show', $post->slug), '0.8', 'weekly', $post->updated_at->toDateString());
                }
            });

        Category::where('is_active', true)
            ->select('id', 'slug', 'updated_at')
            ->chunkById(100, function ($categories) use ($add) {
                foreach ($categories as $category) {
                    $add(route('news.category', $category->slug), '0.7', 'weekly', $category->updated_at->toDateString());
                }
            });

        Partner::where('is_active', true)
            ->select('id', 'slug', 'updated_at')
            ->chunkById(100, function ($partners) use ($add) {
                foreach ($partners as $partner) {
                    $add(route('entes.show', $partner->slug), '0.6', 'monthly', $partner->updated_at->toDateString());
                }
            });

        Gaceta::select('id', 'fecha', 'updated_at')
            ->chunkById(100, function ($gacetas) use ($add) {
                foreach ($gacetas as $gaceta) {
                    $add(route('gacetas.index'), '0.6', 'monthly', $gaceta->updated_at->toDateString());
                }
            });

        Page::where('is_active', true)
            ->select('id', 'slug', 'updated_at')
            ->chunkById(100, function ($pages) use ($add) {
                foreach ($pages as $page) {
                    $add(route('page.show', $page->slug), '0.5', 'monthly', $page->updated_at->toDateString());
                }
            });

        return $urls;
    }
}
