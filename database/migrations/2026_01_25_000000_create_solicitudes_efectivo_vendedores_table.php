<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_efectivo_vendedores', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('apertura_cierre_caja_id', 26);
            $table->unsignedBigInteger('vendedor_solicitante_id');
            $table->unsignedBigInteger('vendedor_prestamista_id');
            $table->decimal('monto_solicitado', 10, 2);
            $table->text('motivo')->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->dateTime('fecha_solicitud');
            $table->dateTime('fecha_respuesta')->nullable();
            $table->text('comentario_respuesta')->nullable();
            $table->timestamps();

            $table->foreign('apertura_cierre_caja_id', 'fk_solicitud_apertura')->references('id')->on('apertura_cierre_caja')->onDelete('cascade');
            $table->foreign('vendedor_solicitante_id', 'fk_solicitud_solicitante')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vendedor_prestamista_id', 'fk_solicitud_prestamista')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['apertura_cierre_caja_id', 'estado'], 'idx_solicitud_apertura_estado');
            $table->index('vendedor_solicitante_id', 'idx_solicitud_solicitante');
            $table->index('vendedor_prestamista_id', 'idx_solicitud_prestamista');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_efectivo_vendedores');
    }
};
