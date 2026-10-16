<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename', 'original_name', 'path', 'mime_type', 'size', 'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
