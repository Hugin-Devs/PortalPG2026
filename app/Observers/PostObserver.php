<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\CacheKeys;

class PostObserver
{
    public function saved(Post $post): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushNews();
        CacheKeys::flushSitemap();
    }

    public function deleted(Post $post): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushNews();
        CacheKeys::flushSitemap();
    }
}
