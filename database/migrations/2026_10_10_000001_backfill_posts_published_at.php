<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(created_at, updated_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Corrección de datos irreversible (backfill): no se revierte.
    }
};
