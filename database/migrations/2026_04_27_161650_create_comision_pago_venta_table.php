<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot que vincula cada pago de comisión con las unidades de venta que cubre.
     * Permite saber con precisión qué comisiones ya fueron pagadas y cuáles
     * siguen pendientes (incluso pagos parciales por unidad).
     */
    public function up(): void
    {
        Schema::create('comision_pago_venta', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('comision_pago_id', 255);
            $table->integer('unidad_derivada_venta_id');
            $table->decimal('monto_aplicado', 10, 2)
                ->comment('Monto del pago que cubre esta unidad (puede ser parcial)');
            $table->timestamps();

            $table->foreign('comision_pago_id')
                ->references('id')->on('comision_pago')
                ->onDelete('cascade');
            $table->foreign('unidad_derivada_venta_id')
                ->references('id')->on('unidadderivadainmutableventa')
                ->onDelete('cascade');

            $table->index('comision_pago_id');
            $table->index('unidad_derivada_venta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_pago_venta');
    }
};
