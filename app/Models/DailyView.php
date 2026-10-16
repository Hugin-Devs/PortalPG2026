<?php

namespace App\Models;

class DailyView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'day',
        'post_id',
        'count',
    ];

    protected $casts = [
        'day' => 'date',
        'count' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
