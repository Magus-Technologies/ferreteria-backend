<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rellenar el FK comprobante_id_referencia de las notas existentes a partir
        // del comprobante (factura/boleta) de su venta. Antes de este cambio las
        // notas se creaban sin este campo, por lo que el "Detalle de Nota"
        // (comprobante_referencia.detalles) quedaba vacío.
        if (Schema::hasTable('nota_credito') && Schema::hasColumn('nota_credito', 'comprobante_id_referencia')) {
            DB::statement(
                'UPDATE nota_credito nc
                 JOIN comprobantes_electronicos ce
                   ON ce.venta_id = nc.venta_id AND ce.tipo_comprobante IN ("01","03")
                 SET nc.comprobante_id_referencia = ce.id
                 WHERE nc.comprobante_id_referencia IS NULL'
            );
        }

        if (Schema::hasTable('nota_debito') && Schema::hasColumn('nota_debito', 'comprobante_id_referencia')) {
            DB::statement(
                'UPDATE nota_debito nd
                 JOIN comprobantes_electronicos ce
                   ON ce.venta_id = nd.venta_id AND ce.tipo_comprobante IN ("01","03")
                 SET nd.comprobante_id_referencia = ce.id
                 WHERE nd.comprobante_id_referencia IS NULL'
            );
        }
    }

    public function down(): void
    {
        // No-op: es un backfill de datos, no se revierte.
    }
};
