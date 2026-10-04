<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Noticias
            ['name' => 'Ver noticias', 'slug' => 'news.view', 'group' => 'Noticias'],
            ['name' => 'Crear noticias', 'slug' => 'news.create', 'group' => 'Noticias'],
            ['name' => 'Editar noticias', 'slug' => 'news.edit', 'group' => 'Noticias'],
            ['name' => 'Eliminar noticias', 'slug' => 'news.delete', 'group' => 'Noticias'],
            // Categorías
            ['name' => 'Ver categorías', 'slug' => 'categories.view', 'group' => 'Categorías'],
            ['name' => 'Crear categorías', 'slug' => 'categories.create', 'group' => 'Categorías'],
            ['name' => 'Editar categorías', 'slug' => 'categories.edit', 'group' => 'Categorías'],
            ['name' => 'Eliminar categorías', 'slug' => 'categories.delete', 'group' => 'Categorías'],
            // Usuarios
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'group' => 'Usuarios'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'group' => 'Usuarios'],
            ['name' => 'Editar usuarios', 'slug' => 'users.edit', 'group' => 'Usuarios'],
            ['name' => 'Eliminar usuarios', 'slug' => 'users.delete', 'group' => 'Usuarios'],
            // Entes
            ['name' => 'Ver entes', 'slug' => 'partners.view', 'group' => 'Entes'],
            ['name' => 'Crear entes', 'slug' => 'partners.create', 'group' => 'Entes'],
            ['name' => 'Editar entes', 'slug' => 'partners.edit', 'group' => 'Entes'],
            ['name' => 'Eliminar entes', 'slug' => 'partners.delete', 'group' => 'Entes'],
            // Medios
            ['name' => 'Ver medios', 'slug' => 'media.view', 'group' => 'Medios'],
            ['name' => 'Subir medios', 'slug' => 'media.upload', 'group' => 'Medios'],
            ['name' => 'Eliminar medios', 'slug' => 'media.delete', 'group' => 'Medios'],
            // Sobre Nosotros
            ['name' => 'Editar Sobre Nosotros', 'slug' => 'about.edit', 'group' => 'Sobre Nosotros'],
            // Estructura Org.
            ['name' => 'Ver estructura', 'slug' => 'org.view', 'group' => 'Estructura Org.'],
            ['name' => 'Crear estructura', 'slug' => 'org.create', 'group' => 'Estructura Org.'],
            ['name' => 'Editar estructura', 'slug' => 'org.edit', 'group' => 'Estructura Org.'],
            ['name' => 'Eliminar estructura', 'slug' => 'org.delete', 'group' => 'Estructura Org.'],
            // Roles
            ['name' => 'Gestionar roles', 'slug' => 'roles.manage', 'group' => 'Roles'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // Asignación de permisos por rol (idempotente: upsert contra el índice
        // único role+permission_id, seguro de re-ejecutar sin duplicar ni fallar)
        $assign = function (string $role, $ids): void {
            $rows = $ids->map(fn ($id) => [
                'role' => $role,
                'permission_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            if ($rows !== []) {
                DB::table('role_permission')->upsert(
                    $rows,
                    ['role', 'permission_id'],
                    ['updated_at']
                );
            }
        };

        // Todos los permisos a superadmin
        $assign('superadmin', Permission::pluck('id'));

        // Permisos básicos a admin
        $adminSlugs = ['news.view', 'news.create', 'news.edit', 'categories.view', 'categories.create', 'categories.edit', 'categories.delete', 'partners.view', 'media.view', 'media.upload', 'about.edit', 'org.view', 'org.create', 'org.edit'];
        $assign('admin', Permission::whereIn('slug', $adminSlugs)->pluck('id'));

        // Permisos mínimos a editor
        $editorSlugs = ['news.view', 'news.create', 'news.edit'];
        $assign('editor', Permission::whereIn('slug', $editorSlugs)->pluck('id'));
    }
}
