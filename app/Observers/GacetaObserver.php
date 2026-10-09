<?php

namespace App\Observers;

use App\Models\Gaceta;
use App\Support\CacheKeys;

class GacetaObserver
{
    public function saved(Gaceta $gaceta): void
    {
        CacheKeys::flushGacetas();
        CacheKeys::flushSitemap();
    }

    public function deleted(Gaceta $gaceta): void
    {
        CacheKeys::flushGacetas();
        CacheKeys::flushSitemap();
    }
}
