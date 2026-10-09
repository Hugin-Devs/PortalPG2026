<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Rutas a las que se permite el acceso aunque el usuario deba cambiar la contraseña.
     */
    protected array $allowedRoutes = [
        'password.change',
        'password.change.update',
        'admin.logout',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && is_null($user->password_changed_at)) {
            if (! in_array($request->route()->getName(), $this->allowedRoutes, true)) {
                return redirect()->route('password.change')
                    ->with('warning', 'Debes cambiar tu contraseña antes de continuar.');
            }
        }

        return $next($request);
    }
}
