<?php

namespace App\Observers;

use App\Models\Partner;
use App\Support\CacheKeys;

class PartnerObserver
{
    public function saved(Partner $partner): void
    {
        CacheKeys::flushEntes();
        CacheKeys::flushSitemap();
    }

    public function deleted(Partner $partner): void
    {
        CacheKeys::flushEntes();
        CacheKeys::flushSitemap();
    }
}
