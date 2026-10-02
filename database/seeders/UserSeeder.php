<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@gdc.gob.ve'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'editor@gdc.gob.ve'],
            [
                'name' => 'Editor Principal',
                'password' => Hash::make('password'),
                'role' => 'editor',
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin2@gdc.gob.ve'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }
}