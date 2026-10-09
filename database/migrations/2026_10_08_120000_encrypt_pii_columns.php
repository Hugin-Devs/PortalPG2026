<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('subject')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('subject')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
