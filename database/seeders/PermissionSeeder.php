<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Dos niveles de agrupación, alineados con el sidebar admin:
        //  - `group`  = categoría (sección del sidebar): Gestión Noticias / Gestión Portal / Configuración Portal
        //  - `module` = módulo/función (ítem del sidebar): Noticias, Medios, Videos, ...
        $permissions = [
            // --- Gestión Noticias ---
            ['name' => 'Ver noticias', 'slug' => 'news.view', 'group' => 'Gestión Noticias', 'module' => 'Noticias'],
            ['name' => 'Crear noticias', 'slug' => 'news.create', 'group' => 'Gestión Noticias', 'module' => 'Noticias'],
            ['name' => 'Editar noticias', 'slug' => 'news.edit', 'group' => 'Gestión Noticias', 'module' => 'Noticias'],
            ['name' => 'Eliminar noticias', 'slug' => 'news.delete', 'group' => 'Gestión Noticias', 'module' => 'Noticias'],
            ['name' => 'Aprobar noticias', 'slug' => 'news.approve', 'group' => 'Gestión Noticias', 'module' => 'Noticias'],
            ['name' => 'Ver medios', 'slug' => 'media.view', 'group' => 'Gestión Noticias', 'module' => 'Medios'],
            ['name' => 'Subir medios', 'slug' => 'media.upload', 'group' => 'Gestión Noticias', 'module' => 'Medios'],
            ['name' => 'Eliminar medios', 'slug' => 'media.delete', 'group' => 'Gestión Noticias', 'module' => 'Medios'],
            ['name' => 'Ver videos', 'slug' => 'videos.view', 'group' => 'Gestión Noticias', 'module' => 'Videos'],
            ['name' => 'Cargar videos', 'slug' => 'videos.upload', 'group' => 'Gestión Noticias', 'module' => 'Videos'],
            ['name' => 'Gestionar portada de videos', 'slug' => 'videos.manage', 'group' => 'Gestión Noticias', 'module' => 'Videos'],
            ['name' => 'Eliminar videos', 'slug' => 'videos.delete', 'group' => 'Gestión Noticias', 'module' => 'Videos'],
            ['name' => 'Ver gacetas', 'slug' => 'gacetas.view', 'group' => 'Gestión Noticias', 'module' => 'Gacetas'],
            ['name' => 'Crear gacetas', 'slug' => 'gacetas.create', 'group' => 'Gestión Noticias', 'module' => 'Gacetas'],
            ['name' => 'Editar gacetas', 'slug' => 'gacetas.edit', 'group' => 'Gestión Noticias', 'module' => 'Gacetas'],
            ['name' => 'Eliminar gacetas', 'slug' => 'gacetas.delete', 'group' => 'Gestión Noticias', 'module' => 'Gacetas'],
            // --- Gestión Portal ---
            ['name' => 'Ver categorías', 'slug' => 'categories.view', 'group' => 'Gestión Portal', 'module' => 'Categorías'],
            ['name' => 'Crear categorías', 'slug' => 'categories.create', 'group' => 'Gestión Portal', 'module' => 'Categorías'],
            ['name' => 'Editar categorías', 'slug' => 'categories.edit', 'group' => 'Gestión Portal', 'module' => 'Categorías'],
            ['name' => 'Eliminar categorías', 'slug' => 'categories.delete', 'group' => 'Gestión Portal', 'module' => 'Categorías'],
            ['name' => 'Ver entes', 'slug' => 'partners.view', 'group' => 'Gestión Portal', 'module' => 'Entes Afiliados'],
            ['name' => 'Crear entes', 'slug' => 'partners.create', 'group' => 'Gestión Portal', 'module' => 'Entes Afiliados'],
            ['name' => 'Editar entes', 'slug' => 'partners.edit', 'group' => 'Gestión Portal', 'module' => 'Entes Afiliados'],
            ['name' => 'Eliminar entes', 'slug' => 'partners.delete', 'group' => 'Gestión Portal', 'module' => 'Entes Afiliados'],
            ['name' => 'Editar Sobre Nosotros', 'slug' => 'about.edit', 'group' => 'Gestión Portal', 'module' => 'Sobre Nosotros'],
            ['name' => 'Editar información de contacto', 'slug' => 'contact.edit', 'group' => 'Gestión Portal', 'module' => 'Contacto'],
            ['name' => 'Ver mensajes de contacto', 'slug' => 'contact.view', 'group' => 'Gestión Portal', 'module' => 'Mensajes'],
            ['name' => 'Eliminar mensajes de contacto', 'slug' => 'contact.delete', 'group' => 'Gestión Portal', 'module' => 'Mensajes'],
            ['name' => 'Ver estructura', 'slug' => 'org.view', 'group' => 'Gestión Portal', 'module' => 'Estructura Org.'],
            ['name' => 'Crear estructura', 'slug' => 'org.create', 'group' => 'Gestión Portal', 'module' => 'Estructura Org.'],
            ['name' => 'Editar estructura', 'slug' => 'org.edit', 'group' => 'Gestión Portal', 'module' => 'Estructura Org.'],
            ['name' => 'Eliminar estructura', 'slug' => 'org.delete', 'group' => 'Gestión Portal', 'module' => 'Estructura Org.'],
            ['name' => 'Gestionar redes sociales', 'slug' => 'rrss.manage', 'group' => 'Gestión Portal', 'module' => 'Redes Sociales'],
            // --- Configuración Portal ---
            ['name' => 'Editar identidad y enlaces', 'slug' => 'settings.manage', 'group' => 'Configuración Portal', 'module' => 'Identidad / Enlaces'],
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'group' => 'Configuración Portal', 'module' => 'Usuarios'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'group' => 'Configuración Portal', 'module' => 'Usuarios'],
            ['name' => 'Editar usuarios', 'slug' => 'users.edit', 'group' => 'Configuración Portal', 'module' => 'Usuarios'],
            ['name' => 'Eliminar usuarios', 'slug' => 'users.delete', 'group' => 'Configuración Portal', 'module' => 'Usuarios'],
            ['name' => 'Gestionar roles', 'slug' => 'roles.manage', 'group' => 'Configuración Portal', 'module' => 'Roles y Permisos'],
            ['name' => 'Ver auditoría', 'slug' => 'audit.view', 'group' => 'Configuración Portal', 'module' => 'Auditoría'],
        ];

        // updateOrCreate: además de crear faltantes, corrige group/module/name
        // de filas existentes (migración de grupos y módulos al estado actual)
        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
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
        $adminSlugs = ['news.view', 'news.create', 'news.edit', 'news.approve', 'categories.view', 'categories.create', 'categories.edit', 'categories.delete', 'partners.view', 'media.view', 'media.upload', 'gacetas.view', 'gacetas.create', 'gacetas.edit', 'gacetas.delete', 'about.edit', 'org.view', 'org.create', 'org.edit', 'contact.view', 'contact.edit', 'contact.delete'];
        $assign('admin', Permission::whereIn('slug', $adminSlugs)->pluck('id'));

        // Permisos mínimos a editor
        $editorSlugs = ['news.view', 'news.create', 'news.edit', 'media.view', 'media.upload'];
        $assign('editor', Permission::whereIn('slug', $editorSlugs)->pluck('id'));
    }
}
