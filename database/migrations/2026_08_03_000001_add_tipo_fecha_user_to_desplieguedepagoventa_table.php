<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convierte desplieguedepagoventa en el historial completo de
     * movimientos de cobro de una venta (cobro inicial, cobro de
     * diferencia por edición, devolución por edición) en vez de solo
     * el desglose de pago de la venta al momento de crearla.
     */
    public function up(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            if (!Schema::hasColumn('desplieguedepagoventa', 'tipo')) {
                $table->string('tipo', 20)->default('inicial')->after('monto');
            }

            if (!Schema::hasColumn('desplieguedepagoventa', 'fecha')) {
                $table->dateTime('fecha')->nullable()->after('recibe_efectivo');
            }

            if (!Schema::hasColumn('desplieguedepagoventa', 'user_id')) {
                $table->string('user_id')->nullable()->after('fecha');
            }
        });

        // Backfill: las filas existentes son todas cobros iniciales (al
        // crear la venta). fecha/user_id se aproximan con los de la venta.
        DB::statement('
            UPDATE desplieguedepagoventa dpv
            INNER JOIN venta v ON v.id = dpv.venta_id
            SET dpv.tipo = "inicial",
                dpv.fecha = COALESCE(dpv.fecha, v.fecha),
                dpv.user_id = COALESCE(dpv.user_id, v.user_id)
        ');
    }

    public function down(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->dropColumnIfExists('tipo');
            $table->dropColumnIfExists('fecha');
            $table->dropColumnIfExists('user_id');
        });
    }
};
