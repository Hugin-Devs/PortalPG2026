<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para los filtros de la vista de auditoría (F6, ajuste 2026-10-23).
 *
 * El filtro por usuario necesita un índice sobre user_id (PostgreSQL no crea
 * índices automáticamente para claves foráneas). created_at ya está indexado
 * por separado y de forma compuesta con action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
