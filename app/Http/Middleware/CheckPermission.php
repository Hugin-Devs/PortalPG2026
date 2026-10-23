<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        $user = auth()->user();
        $role = $user->role;

        // Superadmin siempre tiene acceso a todo
        if ($role === 'superadmin') {
            return $next($request);
        }

        $hasPermission = \DB::table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('role_permission.role', $role)
            ->where('permissions.slug', $permission)
            ->exists();

        if (! $hasPermission) {
            // Denegación de autorización registrada: intentos de acceso a
            // recursos sin permiso son señal de escalada o enumeración.
            // Sin PII en la descripción (convención del proyecto: solo IDs).
            Audit::log('permission.denied', null, null, "Acceso denegado: usuario #{$user->id} (rol {$role}) intentó {$permission} en {$request->path()}", [
                'permission' => $permission,
                'role' => $role,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
