<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_calificaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('proveedor_id');
            $table->enum('estado', ['excelente', 'bueno', 'regular', 'problematico']);
            $table->string('razon')->nullable(); // Ej: "Entrega rápida", "Calidad inconsistente"
            $table->text('observacion')->nullable(); // Ej: "Proveedor confiable, siempre cumple plazos"
            $table->string('created_by')->nullable(); // User ID
            $table->timestamps();

            $table->foreign('proveedor_id')->references('id')->on('proveedor')->onDelete('cascade');
            $table->index('proveedor_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_calificaciones');
    }
};
