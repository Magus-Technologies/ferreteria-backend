<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias_efectivo_vendedores', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('solicitud_id', 26);
            $table->char('apertura_cierre_caja_id', 26);
            $table->unsignedBigInteger('vendedor_origen_id');
            $table->unsignedInteger('sub_caja_origen_id')->nullable()->comment('Sub-caja de donde sale el dinero');
            $table->unsignedBigInteger('vendedor_destino_id');
            $table->unsignedInteger('sub_caja_destino_id')->nullable()->comment('Sub-caja donde entra el dinero');
            $table->decimal('monto', 10, 2);
            $table->dateTime('fecha_transferencia');
            $table->timestamps();

            $table->foreign('solicitud_id', 'fk_transf_solicitud')->references('id')->on('solicitudes_efectivo_vendedores')->onDelete('cascade');
            $table->foreign('apertura_cierre_caja_id', 'fk_transf_apertura')->references('id')->on('apertura_cierre_caja')->onDelete('cascade');
            $table->foreign('vendedor_origen_id', 'fk_transf_origen')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('vendedor_destino_id', 'fk_transf_destino')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sub_caja_origen_id')->references('id')->on('sub_cajas')->onDelete('cascade');
            $table->foreign('sub_caja_destino_id')->references('id')->on('sub_cajas')->onDelete('cascade');
            
            $table->index('apertura_cierre_caja_id', 'idx_transferencia_apertura');
            $table->index('vendedor_origen_id', 'idx_transferencia_origen');
            $table->index('vendedor_destino_id', 'idx_transferencia_destino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_efectivo_vendedores');
    }
};
