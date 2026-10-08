<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'type', 'url', 'path', 'is_featured', 'user_id',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if ($this->type !== 'youtube' || ! $this->url) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/(?:shorts|embed)/)([A-Za-z0-9_-]{11})~', $this->url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return null;
    }

    public function getSourceUrlAttribute(): ?string
    {
        if ($this->type === 'youtube') {
            return $this->embed_url;
        }

        return $this->path ? asset('storage/'.$this->path) : null;
    }
}
