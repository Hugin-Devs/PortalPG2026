<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_visits', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_visits');
    }
};
