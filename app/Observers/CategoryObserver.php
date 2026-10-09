<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\CacheKeys;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushNews();
        CacheKeys::flushSitemap();
    }

    public function deleted(Category $category): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushNews();
        CacheKeys::flushSitemap();
    }
}
