<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gaceta extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'fecha',
        'path',
        'original_name',
        'mime_type',
        'size',
        'file_hash',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
