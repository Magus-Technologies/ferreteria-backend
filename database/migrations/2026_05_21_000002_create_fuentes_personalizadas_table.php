<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuentes_personalizadas', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id');
            $table->string('nombre', 80);
            $table->string('archivo_original', 255);
            $table->string('archivo_path', 255);
            $table->string('tipo_mime', 80)->default('font/ttf');
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes_personalizadas');
    }
};
