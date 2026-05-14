<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eliminar el unique constraint (venta_id, despliegue_de_pago_id) para
     * permitir pagos mixtos con el mismo método de pago en la misma venta
     * (ej: dos pagos parciales con Izipay).
     */
    public function up(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            // Crear índice regular en venta_id para respaldar la FK
            // DespliegueDePagoVenta_venta_id_fkey antes de eliminar el
            // composite unique que era el único índice con venta_id a la izquierda.
            $table->index('venta_id', 'desplieguedepagoventa_venta_id_idx');
        });

        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->dropUnique('DespliegueDePagoVenta_venta_id_despliegue_de_pago_id_key');
        });
    }

    public function down(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->unique(['venta_id', 'despliegue_de_pago_id'], 'DespliegueDePagoVenta_venta_id_despliegue_de_pago_id_key');
        });

        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->dropIndex('desplieguedepagoventa_venta_id_idx');
        });
    }
};
