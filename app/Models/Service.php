<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'content', 'icon', 'external_url', 'order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
