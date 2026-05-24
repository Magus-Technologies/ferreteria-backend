<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra `detalleentregaproducto.cantidad_entregada` → `cantidad_solicitada`.
 *
 * Nuevo significado semántico:
 *   - cantidad_solicitada: total que la orden lógica de entrega debe cubrir
 *   - cantidad_entregada (accessor en el modelo): SUM de los eventos asociados
 *
 * Usamos SQL raw para evitar la dependencia de doctrine/dbal en Laravel 12.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('detalleentregaproducto', 'cantidad_solicitada')) {
            DB::statement('ALTER TABLE `detalleentregaproducto` CHANGE COLUMN `cantidad_entregada` `cantidad_solicitada` DECIMAL(9,3) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('detalleentregaproducto', 'cantidad_solicitada')) {
            DB::statement('ALTER TABLE `detalleentregaproducto` CHANGE COLUMN `cantidad_solicitada` `cantidad_entregada` DECIMAL(9,3) NOT NULL');
        }
    }
};
