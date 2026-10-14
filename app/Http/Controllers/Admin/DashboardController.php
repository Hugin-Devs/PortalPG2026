<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\DailyView;
use App\Models\Gaceta;
use App\Models\Media;
use App\Models\Partner;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // SUM(col = valor) es aritmética booleana de MySQL; PostgreSQL no
        // acepta SUM(boolean). SUM(CASE WHEN ...) es portable y equivalente.
        $postStats = Post::selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END), 0) AS published,
                COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) AS drafts,
                COALESCE(SUM(views), 0) AS views_total
            ")->first();

        $monthViews = (int) DailyView::where('day', '>=', now()->startOfMonth()->toDateString())->sum('count');

        $userStats = User::selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) AS active')->first();

        $newContacts = Contact::whereNull('read_at')->count();

        $latestPosts = Post::with('category')
            ->live()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $chartRows = DailyView::where('day', '>=', now()->subDays(29)->toDateString())
            ->groupBy('day')
            ->selectRaw('day, SUM(count) AS total')
            ->pluck('total', 'day');

        $chartDays = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $chartDays[$date] = (int) ($chartRows[$date] ?? 0);
        }
        $chartMax = max(1, max($chartDays));
        $chartTotal = array_sum($chartDays);

        return view('admin.dashboard.index', [
            'postStats' => $postStats,
            'monthViews' => $monthViews,
            'viewsTotal' => (int) $postStats->views_total,
            'userStats' => $userStats,
            'newContacts' => $newContacts,
            'counts' => [
                'gacetas' => Gaceta::count(),
                'media' => Media::count(),
                'partners' => Partner::count(),
                'partnersActive' => Partner::where('is_active', true)->count(),
                'videos' => Video::count(),
                'categories' => Category::count(),
            ],
            'latestPosts' => $latestPosts,
            'chartDays' => $chartDays,
            'chartMax' => $chartMax,
            'chartTotal' => $chartTotal,
        ]);
    }
}
