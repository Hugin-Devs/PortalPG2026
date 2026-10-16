<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrgMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'position', 'photo', 'description', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
