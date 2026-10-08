<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // El seed es autoritativo en name/role: si alguien cambió el rol desde
        // /admin/usuarios, un re-seed restaura el estado canónico.
        // El password solo se asigna al crear (no se pisa en usuarios existentes).
        $users = [
            ['email' => 'admin@gdc.gob.ve', 'name' => 'Super Administrador', 'role' => 'superadmin'],
            ['email' => 'editor@gdc.gob.ve', 'name' => 'Editor Principal', 'role' => 'editor'],
            ['email' => 'admin2@gdc.gob.ve', 'name' => 'Administrador', 'role' => 'admin'],
        ];

        foreach ($users as $attrs) {
            $user = User::firstOrCreate(
                ['email' => $attrs['email']],
                [
                    'name' => $attrs['name'],
                    'password' => Hash::make('password'),
                    'role' => $attrs['role'],
                ]
            );

            if ($user->name !== $attrs['name'] || $user->role !== $attrs['role']) {
                $user->update([
                    'name' => $attrs['name'],
                    'role' => $attrs['role'],
                ]);
            }
        }
    }
}
