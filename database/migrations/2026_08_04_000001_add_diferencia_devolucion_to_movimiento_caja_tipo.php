<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `movimiento_caja.tipo_movimiento` es un ENUM que no contemplaba los
     * nuevos tipos de movimiento del cobro diferencial ('venta_diferencia',
     * 'venta_devolucion') — sin esto, el INSERT fallaba con "Data truncated"
     * y el movimiento quedaba silenciosamente sin registrar en movimiento_caja
     * (aunque transacciones_caja sí se creaba bien), haciendo que el cobro no
     * apareciera en el cierre de caja del usuario que cobró la diferencia.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE movimiento_caja MODIFY tipo_movimiento ENUM('apertura','venta','gasto','ingreso','cobro','pago','transferencia','cierre','venta_diferencia','venta_devolucion') NOT NULL DEFAULT 'venta'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimiento_caja MODIFY tipo_movimiento ENUM('apertura','venta','gasto','ingreso','cobro','pago','transferencia','cierre') NOT NULL DEFAULT 'venta'");
    }
};
