<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::first();
        $user = User::first() ?? User::firstOrCreate([
            'email' => 'admin@gdc.gob.ve',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $post = Post::updateOrCreate([
                'slug' => "noticia-destacada-$i",
            ], [
                'title' => "Noticia Destacada $i",
                'content' => "Contenido de la noticia $i...",
                'user_id' => $user->id,
                'category_id' => $category->id,
                'status' => 'published',
                'is_featured' => ($i <= 3),
            ]);

            if ($post->published_at === null) {
                $post->forceFill(['published_at' => $post->created_at ?? now()])->save();
            }
        }
    }
}
