<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CacheKeys
{
    public const HOME_FEATURED = 'home.featured';

    public const HOME_LATEST = 'home.latest';

    public const HOME_CATEGORIES = 'home.categories';

    public const HOME_MOST_READ = 'home.most_read';

    public const HOME_VIDEOS = 'home.videos';

    public const NEWS_CATEGORIES_NAV = 'news.categories_nav';

    public const ENTES_INDEX = 'entes.index';

    public const GACETAS_INDEX = 'gacetas.index';

    public const SITEMAP = 'sitemap.xml';

    public static function ttl(): int
    {
        return (int) config('portal.cache_ttl', 3600);
    }

    public static function flushHome(): void
    {
        foreach ([
            self::HOME_FEATURED,
            self::HOME_LATEST,
            self::HOME_CATEGORIES,
            self::HOME_MOST_READ,
            self::HOME_VIDEOS,
        ] as $key) {
            Cache::forget($key);
        }
    }

    public static function flushNews(): void
    {
        Cache::forget(self::NEWS_CATEGORIES_NAV);
    }

    /**
     * Invalida solo el ranking "Lo más leído" del home.
     *
     * Se usa al contar una vista (NewsController::show): el incremento no
     * dispara el observer `saved`, y sin este flush la barra lateral pinta
     * números y orden congelados hasta PORTAL_CACHE_TTL (1 h por defecto).
     */
    public static function flushMostRead(): void
    {
        Cache::forget(self::HOME_MOST_READ);
    }

    public static function flushEntes(): void
    {
        Cache::forget(self::ENTES_INDEX);
    }

    public static function flushGacetas(): void
    {
        Cache::forget(self::GACETAS_INDEX);
    }

    public static function flushSitemap(): void
    {
        Cache::forget(self::SITEMAP);
    }
}
