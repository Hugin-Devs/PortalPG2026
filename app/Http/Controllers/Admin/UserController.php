<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => $this->roleValidationRule(),
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->has('is_active');

        $user = User::create($data);
        Audit::log('user.created', User::class, $user->id, "Usuario creado: {$user->email} ({$user->role})");

        return redirect('/admin/usuarios')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id() && ! $request->has('is_active')) {
            return back()->with('error', 'No puedes desactivarte a ti mismo.');
        }

        $data = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|min:8|confirmed',
            'role' => $this->roleValidationRule($user),
        ]);

        if ($data['password'] ?? null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->has('is_active');

        $user->update($data);
        Audit::log('user.updated', User::class, $user->id, "Usuario actualizado: {$user->email} ({$user->role})");

        return redirect('/admin/usuarios')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }
        if ($user->posts()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un usuario con noticias asociadas.');
        }
        Audit::log('user.deleted', User::class, $user->id, "Usuario eliminado: {$user->email}");
        $user->delete();

        return redirect('/admin/usuarios')->with('success', 'Usuario eliminado correctamente.');
    }

    private function roleValidationRule(?User $user = null): array
    {
        return [
            'required',
            'in:superadmin,admin,editor',
            function ($attribute, $value, $fail) use ($user) {
                if ($value === 'superadmin' && auth()->user()?->role !== 'superadmin') {
                    $fail('No tienes permiso para asignar el rol superadmin.');
                }

                // No degradar al último superadmin: dejaría el sistema sin administrador total.
                if ($user && $user->role === 'superadmin' && $value !== 'superadmin'
                    && User::where('role', 'superadmin')->count() <= 1) {
                    $fail('No se puede degradar al último superadministrador.');
                }
            },
        ];
    }
}
