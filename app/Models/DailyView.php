<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
