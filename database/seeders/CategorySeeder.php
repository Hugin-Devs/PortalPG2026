<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Nacionales', 'slug' => 'nacionales'],
            ['name' => 'Política', 'slug' => 'politica'],
            ['name' => 'Economía', 'slug' => 'economia'],
            ['name' => 'Cultura', 'slug' => 'cultura'],
            ['name' => 'Deporte', 'slug' => 'deporte'],
        ];

        foreach ($categories as $cat) {
            \App\Models\Category::create($cat);
        }
    }
}
