<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = \App\Models\Category::first();
        $user = \App\Models\User::first() ?? \App\Models\User::firstOrCreate([
            'email' => 'admin@gdc.gob.ve',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Post::updateOrCreate([
                'slug' => "noticia-destacada-$i",
            ], [
                'title' => "Noticia Destacada $i",
                'content' => "Contenido de la noticia $i...",
                'user_id' => $user->id,
                'category_id' => $category->id,
                'status' => 'published',
                'is_featured' => ($i <= 3),
            ]);
        }
    }
}
