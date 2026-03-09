<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_calificaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('cliente_id'); // Match cliente.id type (int)
            $table->enum('estado', ['excelente', 'bueno', 'regular', 'problematico']);
            $table->string('razon')->nullable(); // Ej: "Pago lento", "Devoluciones frecuentes"
            $table->text('observacion')->nullable(); // Ej: "Cliente pide que le lleve su pedido al segundo piso"
            $table->string('created_by')->nullable(); // User ID is string (CUID from Prisma)
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('cliente')->onDelete('cascade');
            $table->index('cliente_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_calificaciones');
    }
};
