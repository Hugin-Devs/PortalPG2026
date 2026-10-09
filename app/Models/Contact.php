<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'subject', 'message', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'name' => 'encrypted',
        'email' => 'encrypted',
        'subject' => 'encrypted',
        'message' => 'encrypted',
    ];
}
