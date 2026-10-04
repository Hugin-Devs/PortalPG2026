<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gacetas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20);
            $table->date('fecha');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gacetas');
    }
};
