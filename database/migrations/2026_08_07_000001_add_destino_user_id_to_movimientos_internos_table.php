<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marca explícita de "TRASLADO DE EFECTIVO": a qué usuario se le acreditó el
 * dinero en su SESIÓN ABIERTA.
 *
 * Hasta ahora ese dato solo vivía en `transacciones_caja.user_id` de la fila de
 * ingreso, indistinguible de un movimiento normal sub-caja → sub-caja. Sin poder
 * distinguirlos, el cálculo de saldo movible excluía los movimientos internos en
 * ambos lados y el dinero trasladado a la sesión de un usuario seguía figurando
 * como CERRADO/disponible — se podía trasladar el mismo monto infinitas veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN destino_user_id VARCHAR(255) NULL AFTER user_id');

        // Backfill de los traslados ya registrados: el modal "Traslado de Efectivo"
        // siempre manda destino_user_id y graba concepto = 'TRASLADO DE EFECTIVO',
        // y registrarTransacciones() lo dejó como user_id de la fila de INGRESO.
        DB::statement("
            UPDATE movimientos_internos mi
            JOIN transacciones_caja tc
              ON tc.referencia_id = mi.id
             AND tc.referencia_tipo = 'movimiento_interno'
             AND tc.tipo_transaccion = 'ingreso'
            SET mi.destino_user_id = tc.user_id
            WHERE UPPER(mi.concepto) = 'TRASLADO DE EFECTIVO'
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN destino_user_id');
    }
};
