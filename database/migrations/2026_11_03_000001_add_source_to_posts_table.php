<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('source')->nullable()->after('views');
            $table->string('source_url')->nullable()->after('source');
        });

        // Índice único parcial: solo filas vivas. Permite re-importar la misma
        // URL tras un soft delete sin chocar con la restricción.
        DB::statement(
            'CREATE UNIQUE INDEX posts_source_url_live_unique ON posts (source_url) WHERE source_url IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS posts_source_url_live_unique');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_url']);
        });
    }
};
