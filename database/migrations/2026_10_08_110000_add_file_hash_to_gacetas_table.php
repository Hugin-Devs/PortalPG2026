<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacetas', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('size')->comment('SHA-256 del archivo para integridad');
        });
    }

    public function down(): void
    {
        Schema::table('gacetas', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
