<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $limiterKey = 'login:'.$request->ip();
        // Límite por cuenta (hash, sin PII en caché): un ataque de stuffing
        // rotando IPs no debe poder concentrar intentos contra una cuenta.
        $accountKey = 'login:account:'.hash('sha256', strtolower($credentials['email']));

        $maxAttempts = (int) config('security.login_lockout.attempts', 5);
        $decaySeconds = (int) config('security.login_lockout.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts) || RateLimiter::tooManyAttempts($accountKey, $maxAttempts)) {
            return redirect('/admin/login')->withErrors([
                'email' => 'Demasiados intentos fallidos. Vuelve a intentarlo en '.RateLimiter::availableIn($limiterKey).' segundos.',
            ]);
        }

        if (auth()->attempt($credentials)) {
            $user = auth()->user();

            if (! $user->is_active) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($limiterKey, $decaySeconds);
                RateLimiter::hit($accountKey, $decaySeconds);

                return redirect('/admin/login')->withErrors(['email' => 'Credenciales inválidas.']);
            }

            RateLimiter::clear($limiterKey);
            RateLimiter::clear($accountKey);
            $user->forceFill(['last_login_at' => now()])->save();
            $request->session()->regenerate();
            Audit::log('login.success', User::class, $user->id, "Login exitoso: usuario #{$user->id}");

            return redirect()->intended('/admin');
        }

        RateLimiter::hit($limiterKey, $decaySeconds);
        RateLimiter::hit($accountKey, $decaySeconds);
        Audit::log('login.failed', null, null, 'Intento fallido de login');

        return redirect('/admin/login')->withErrors(['email' => 'Credenciales inválidas.']);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        Audit::log('logout', User::class, $user?->id, $user ? "Logout: usuario #{$user->id}" : 'Logout');

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }

    public function showChangePassword()
    {
        return view('admin.auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'La nueva contraseña debe ser diferente a la actual.',
        ]);

        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ])->save();
        Audit::log('password.changed', User::class, $user->id, "Cambio de contraseña: usuario #{$user->id}");

        return redirect()->intended('/admin')->with('success', 'Contraseña actualizada correctamente.');
    }
}
