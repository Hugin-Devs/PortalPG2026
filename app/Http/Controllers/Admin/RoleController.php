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
        $role = $request->input('role');
        $permissionIds = $request->input('permissions', '');

        $ids = $permissionIds ? explode(',', $permissionIds) : [];

        \DB::table('role_permission')->where('role', $role)->delete();

        $data = [];
        foreach ($ids as $permId) {
            $data[] = [
                'role' => $role,
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($data)) {
            \DB::table('role_permission')->insert($data);
        }

        Audit::log('role.updated', null, null, "Permisos actualizados para rol: {$role}", [
            'role' => $role,
            'permission_count' => count($ids),
        ]);

        return back()->with('success', "Permisos actualizados para el rol \"$role\".");
    }
}
