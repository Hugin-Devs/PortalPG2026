<?php

namespace App\Observers;

use App\Models\Video;
use App\Support\CacheKeys;

class VideoObserver
{
    public function saved(Video $video): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushSitemap();
    }

    public function deleted(Video $video): void
    {
        CacheKeys::flushHome();
        CacheKeys::flushSitemap();
    }
}
