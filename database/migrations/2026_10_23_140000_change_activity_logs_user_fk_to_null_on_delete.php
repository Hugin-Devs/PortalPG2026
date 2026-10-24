<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bases existentes creadas antes del cambio a nullOnDelete: la FK
 * activity_logs_user_id_foreign pasa de cascadeOnDelete a nullOnDelete para
 * que borrar un usuario conserve su historial de auditoría.
 *
 * En SQLite (tests) dropForeign/foreign son no-ops: los tests recién creados
 * migran desde cero y ya reciben nullOnDelete de la migración create.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite no soporta dropForeign (Laravel lanza BadMethodCallException):
        // los tests migran desde cero y ya reciben nullOnDelete de la migración
        // create, así que aquí solo actúan los motores con FK reales (pgsql).
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Antes de restaurar cascadeOnDelete hay que limpiar los user_id
        // huérfanos: cascadeOnDelete sobre filas con user_id NULL ya es
        // inocuo, pero sobre IDs ya inexistentes la FK fallaría.
        DB::table('activity_logs')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
