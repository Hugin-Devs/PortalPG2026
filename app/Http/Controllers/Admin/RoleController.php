<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = ['superadmin', 'admin', 'editor'];
        // Orden en 3 niveles, igual que el sidebar:
        // 1) categoría (group) → 2) módulo (module) → 3) acción (slug: ver/crear/editar/gestionar/eliminar)
        // La ordenación se hace en PHP (portable entre motores) en lugar de
        // FIELD()/SUBSTRING_INDEX(), que son exclusivos de MySQL/MariaDB.
        $groupOrder = ['Gestión Noticias', 'Gestión Portal', 'Configuración Portal'];
        $moduleOrder = [
            'Noticias', 'Medios', 'Videos', 'Gacetas',
            'Categorías', 'Entes Afiliados', 'Sobre Nosotros', 'Contacto',
            'Mensajes', 'Estructura Org.', 'Redes Sociales',
            'Identidad / Enlaces', 'Usuarios', 'Roles y Permisos',
        ];
        $actionOrder = ['view', 'create', 'upload', 'edit', 'manage', 'delete'];

        $rankOf = function (string $value, array $list): int {
            $index = array_search($value, $list, true);

            return $index === false ? PHP_INT_MAX : $index;
        };

        $permissions = Permission::query()
            ->get()
            ->sortBy([
                fn ($a, $b) => $rankOf($a->group, $groupOrder) <=> $rankOf($b->group, $groupOrder),
                fn ($a, $b) => $rankOf($a->module, $moduleOrder) <=> $rankOf($b->module, $moduleOrder),
                fn ($a, $b) => $rankOf(Str::afterLast($a->slug, '.'), $actionOrder)
                    <=> $rankOf(Str::afterLast($b->slug, '.'), $actionOrder),
                fn ($a, $b) => $a->id <=> $b->id,
            ], SORT_REGULAR)
            ->values();
        $rolePermissions = [];

        foreach ($roles as $role) {
            $rolePermissions[$role] = \DB::table('role_permission')
                ->where('role', $role)
                ->pluck('permission_id')
                ->toArray();
        }

        return view('admin.roles.index', compact('roles', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'in:superadmin,admin,editor'],
            'permissions' => ['nullable', 'string', 'max:2000'],
        ]);

        $role = $data['role'];
        $actor = $request->user();

        // Solo un superadmin puede alterar los permisos del rol superadmin
        // (que de todos modos bypasa la comprobación en CheckPermission).
        if ($role === 'superadmin' && $actor?->role !== 'superadmin') {
            abort(403, 'Solo un superadministrador puede modificar el rol superadmin.');
        }

        $ids = collect(explode(',', (string) ($data['permissions'] ?? '')))
            ->filter(fn ($id) => $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Intersección con permisos existentes: un ID desconocido o duplicado
        // es error de validación ANTES de tocar la tabla (evita el DELETE
        // autocomiteado que dejaba el rol sin permisos).
        $validIds = Permission::whereIn('id', $ids)->pluck('id');
        if ($validIds->count() !== $ids->count()) {
            return back()->withErrors([
                'permissions' => 'La lista de permisos contiene identificadores no válidos.',
            ])->withInput();
        }

        // Anti-autoescalada: un actor no superadmin solo puede otorgar
        // permisos que su propio rol ya posee.
        if ($actor?->role !== 'superadmin') {
            $owned = \DB::table('role_permission')
                ->where('role', $actor->role)
                ->pluck('permission_id');
            if (collect($validIds)->diff($owned)->isNotEmpty()) {
                return back()->withErrors([
                    'permissions' => 'No puedes otorgar permisos que tu rol no posee.',
                ])->withInput();
            }
        }

        \DB::transaction(function () use ($role, $validIds) {
            \DB::table('role_permission')->where('role', $role)->delete();

            $rows = $validIds->map(fn ($id) => [
                'role' => $role,
                'permission_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($rows)) {
                \DB::table('role_permission')->insert($rows);
            }
        });

        Audit::log('role.updated', null, null, "Permisos actualizados para rol: {$role}", [
            'role' => $role,
            'permission_count' => count($validIds),
        ]);

        return back()->with('success', "Permisos actualizados para el rol \"$role\".");
    }
}
