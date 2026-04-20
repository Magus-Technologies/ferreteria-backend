<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_pago', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('user_id', 191)->comment('Vendedor al que se le pagó la comisión');
            $table->string('pagado_por', 191)->comment('Usuario que registró el pago');
            $table->decimal('monto_pagado', 10, 2);
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->date('fecha_pago');
            $table->string('metodo_pago', 50)->nullable()->comment('efectivo, transferencia, yape, etc.');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user')->onDelete('restrict');
            $table->foreign('pagado_por')->references('id')->on('user')->onDelete('restrict');

            $table->index('user_id');
            $table->index('fecha_pago');
            $table->index(['periodo_desde', 'periodo_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_pago');
    }
};
