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
        $user = \App\Models\User::first() ?? \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@gdc.gob.ve',
            'password' => bcrypt('password'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Post::create([
                'title' => "Noticia Destacada $i",
                'slug' => "noticia-destacada-$i",
                'content' => "Contenido de la noticia $i...",
                'user_id' => $user->id,
                'category_id' => $category->id,
                'status' => 'published',
                'is_featured' => ($i <= 3),
            ]);
        }
    }
}
