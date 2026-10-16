<?php

namespace App\Models;

class PostMedia extends Model
{
    protected $fillable = [
        'post_id', 'type', 'path', 'video_id', 'position',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
