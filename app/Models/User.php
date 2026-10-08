<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function hasPermission($permissionSlug): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        return \DB::table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('role_permission.role', $this->role)
            ->where('permissions.slug', $permissionSlug)
            ->exists();
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        return \DB::table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('role_permission.role', $this->role)
            ->whereIn('permissions.slug', $permissionSlugs)
            ->exists();
    }
}
