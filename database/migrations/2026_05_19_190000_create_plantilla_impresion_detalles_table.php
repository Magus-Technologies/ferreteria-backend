<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plantilla_impresion_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('comprobante')->nullable();
            $table->string('formato')->nullable();
            $table->json('estilos')->nullable();
            $table->json('mensajes_extra')->nullable();
            $table->json('estilos_secciones')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'comprobante', 'formato'], 'plantilla_impresion_detalles_unq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantilla_impresion_detalles');
    }
};
