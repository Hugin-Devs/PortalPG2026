<?php

namespace App\Models;

use App\Support\SanitizesHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends Model
{
    use HasFactory, SanitizesHtml;

    protected $fillable = [
        'title', 'slug', 'content', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
