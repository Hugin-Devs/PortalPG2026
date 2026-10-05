<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRenderedContentAttribute(): string
    {
        $c = (string) $this->content;

        return preg_match('/<[a-z][^>]*>/i', $c) ? $c : nl2br(e($c));
    }
}
