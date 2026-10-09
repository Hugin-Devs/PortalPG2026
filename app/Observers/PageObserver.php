<?php

namespace App\Observers;

use App\Models\Page;
use App\Support\CacheKeys;

class PageObserver
{
    public function saved(Page $page): void
    {
        CacheKeys::flushSitemap();
    }

    public function deleted(Page $page): void
    {
        CacheKeys::flushSitemap();
    }
}
