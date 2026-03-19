<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Eliminar constraints únicos que impiden que el mismo producto
 * aparezca en un paquete Y como producto independiente en la misma venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Crear índice normal primero (la FK lo necesita), luego dropear el unique
        DB::statement('ALTER TABLE `productoalmacenventa`
            ADD INDEX `pav_venta_producto_almacen_idx` (`venta_id`, `producto_almacen_id`),
            DROP INDEX `ProductoAlmacenVenta_venta_id_producto_almacen_id_key`');

        DB::statement('ALTER TABLE `unidadderivadainmutableventa`
            ADD INDEX `udiv_pav_unidad_idx` (`producto_almacen_venta_id`, `unidad_derivada_inmutable_id`),
            DROP INDEX `UnidadDerivadaInmutableVenta_producto_almacen_venta_id_unida_key`');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `productoalmacenventa`
            ADD UNIQUE INDEX `ProductoAlmacenVenta_venta_id_producto_almacen_id_key` (`venta_id`, `producto_almacen_id`),
            DROP INDEX `pav_venta_producto_almacen_idx`');

        DB::statement('ALTER TABLE `unidadderivadainmutableventa`
            ADD UNIQUE INDEX `UnidadDerivadaInmutableVenta_producto_almacen_venta_id_unida_key` (`producto_almacen_venta_id`, `unidad_derivada_inmutable_id`),
            DROP INDEX `udiv_pav_unidad_idx`');
    }
};
