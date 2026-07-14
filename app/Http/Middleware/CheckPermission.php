<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (!auth()->check()) {
            return redirect('/admin/login');
        }

        $role = auth()->user()->role;

        // Superadmin siempre tiene acceso a todo
        if ($role === 'superadmin') {
            return $next($request);
        }

        $hasPermission = \DB::table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('role_permission.role', $role)
            ->where('permissions.slug', $permission)
            ->exists();

        if (!$hasPermission) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
