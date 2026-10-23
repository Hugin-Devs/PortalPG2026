<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
        try {
            $data = $request->validate([
                'name' => 'required|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'role' => $this->roleValidationRule(),
            ]);
        } catch (ValidationException $e) {
            $this->auditValidationFailure($e);

            throw $e;
        }

        $data['password'] = Hash::make($data['password']);
        $isactive = $request->has('is_active');
        $role = $data['role'];
        unset($data['role']);

        $user = User::create($data);
        $user->forceFill(['role' => $role, 'is_active' => $isactive])->save();
        Audit::log('user.created', User::class, $user->id, "Usuario creado ({$role})");

        return redirect('/admin/usuarios')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id() && ! $request->has('is_active')) {
            return back()->with('error', 'No puedes desactivarte a ti mismo.');
        }

        try {
            $data = $request->validate([
                'name' => 'required|max:255',
                'email' => 'required|email|unique:users,email,'.$user->id,
                'password' => 'nullable|min:8|confirmed',
                'role' => $this->roleValidationRule($user),
            ]);
        } catch (ValidationException $e) {
            $this->auditValidationFailure($e, $user->id);

            throw $e;
        }

        if ($data['password'] ?? null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->has('is_active');
        $role = $data['role'];
        $isactive = $data['is_active'];
        unset($data['role'], $data['is_active']);

        $user->update($data);
        $user->forceFill(['role' => $role, 'is_active' => $isactive])->save();
        Audit::log('user.updated', User::class, $user->id, "Usuario actualizado ({$role})");

        return redirect('/admin/usuarios')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }
        if ($user->posts()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un usuario con noticias asociadas.');
        }
        Audit::log('user.deleted', User::class, $user->id, 'Usuario eliminado');
        $user->delete();

        return redirect('/admin/usuarios')->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Registra la validación fallida en la gestión de usuarios con solo los
     * nombres de los campos (nunca los valores: contendrían PII o credenciales).
     */
    private function auditValidationFailure(ValidationException $e, ?int $targetUserId = null): void
    {
        Audit::log('user.validation_failed', $targetUserId ? User::class : null, $targetUserId, 'Validación fallida en gestión de usuarios', [
            'fields' => array_keys($e->errors()),
        ]);
    }

    /**
     * Jerarquía de roles: un principal solo puede modificar usuarios de un
     * rango estrictamente inferior al suyo. Un superadmin es el único que
     * puede tocar cuentas superadmin (anti-takeover H3).
     */
    private function authorizeTarget(User $user): void
    {
        $actor = auth()->user();

        // Autoedición de perfil permitida (nombre/email); el cambio de
        // contraseña y la desactivación propia ya tienen sus propias guardas.
        if ($user->id === $actor?->id) {
            return;
        }

        if ($user->role === 'superadmin' && $actor?->role !== 'superadmin') {
            abort(403, 'Solo un superadministrador puede modificar cuentas superadmin.');
        }

        if ($actor?->role !== 'superadmin'
            && self::roleRank($user->role) >= self::roleRank($actor->role)) {
            abort(403, 'Solo puedes modificar usuarios de un rango inferior al tuyo.');
        }
    }

    private static function roleRank(?string $role): int
    {
        return match ($role) {
            'superadmin' => 3,
            'admin' => 2,
            'editor' => 1,
            default => 0,
        };
    }

    private function roleValidationRule(?User $user = null): array
    {
        return [
            'required',
            'in:superadmin,admin,editor',
            function ($attribute, $value, $fail) use ($user) {
                $actor = auth()->user();

                if ($value === 'superadmin' && $actor?->role !== 'superadmin') {
                    $fail('No tienes permiso para asignar el rol superadmin.');
                }

                // Jerarquía de asignación: solo se otorgan rangos estrictamente
                // inferiores al del actor (un admin delega editor, no admin).
                if ($actor?->role !== 'superadmin'
                    && self::roleRank($value) >= self::roleRank($actor->role)) {
                    $fail('Solo puedes asignar roles de un rango inferior al tuyo.');
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
