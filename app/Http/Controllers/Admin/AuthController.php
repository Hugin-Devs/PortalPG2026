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

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
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
                RateLimiter::hit($limiterKey, 60);

                return redirect('/admin/login')->withErrors(['email' => 'Credenciales inválidas.']);
            }

            RateLimiter::clear($limiterKey);
            $user->forceFill(['last_login_at' => now()])->save();
            $request->session()->regenerate();
            Audit::log('login.success', User::class, $user->id, "Login exitoso: {$user->email}");

            return redirect()->intended('/admin');
        }

        RateLimiter::hit($limiterKey, 60);
        Audit::log('login.failed', null, null, 'Intento fallido de login', ['email' => $request->input('email')]);

        return redirect('/admin/login')->withErrors(['email' => 'Credenciales inválidas.']);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        Audit::log('logout', User::class, $user?->id, $user ? "Logout: {$user->email}" : 'Logout');

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

        $user->update([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ]);
        Audit::log('password.changed', User::class, $user->id, "Cambio de contraseña: {$user->email}");

        return redirect()->intended('/admin')->with('success', 'Contraseña actualizada correctamente.');
    }
}
