<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'group', 'description'];

    public function roles()
    {
        return $this->belongsToMany(User::class, 'role_permission', 'permission_id', 'role', 'id', 'role');
    }
}
