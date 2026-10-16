<?php

namespace App\Models;

use App\Support\SanitizesHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SanitizesHtml, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image',
        'category_id', 'user_id', 'status', 'published_at', 'is_featured', 'views',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getRenderedContentAttribute(): string
    {
        $c = (string) $this->content;

        return preg_match('/<[a-z][^>]*>/i', $c) ? $c : nl2br(e($c));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postMedia()
    {
        return $this->hasMany(PostMedia::class)->orderBy('position')->orderBy('id');
    }
}
