<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // entrega.venta_id — sin CASCADE bloqueaba DELETE FROM venta
        Schema::table('entrega', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->foreign('venta_id')
                  ->references('id')->on('venta')
                  ->cascadeOnDelete();
        });

        // entrega_detalle.unidad_derivada_venta_id — bloqueaba la cadena
        // venta → unidadderivadainmutableventa (cascade) → entrega_detalle (sin cascade)
        Schema::table('entrega_detalle', function (Blueprint $table) {
            $table->dropForeign(['unidad_derivada_venta_id']);
            $table->foreign('unidad_derivada_venta_id')
                  ->references('id')->on('unidadderivadainmutableventa')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entrega', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->foreign('venta_id')->references('id')->on('venta');
        });

        Schema::table('entrega_detalle', function (Blueprint $table) {
            $table->dropForeign(['unidad_derivada_venta_id']);
            $table->foreign('unidad_derivada_venta_id')
                  ->references('id')->on('unidadderivadainmutableventa');
        });
    }
};
