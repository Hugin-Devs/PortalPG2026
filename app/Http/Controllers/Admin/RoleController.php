<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = ['superadmin', 'admin', 'editor'];
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
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
        $permissionIds = $request->input('permissions', []);

        \DB::table('role_permission')->where('role', $role)->delete();

        $data = [];
        foreach ($permissionIds as $permId) {
            $data[] = [
                'role' => $role,
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \DB::table('role_permission')->insert($data);

        return back()->with('success', "Permisos actualizados para el rol \"$role\".");
    }
}
