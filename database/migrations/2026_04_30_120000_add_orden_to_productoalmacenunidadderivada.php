<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega la columna `orden` a productoalmacenunidadderivada para preservar
     * el orden visual definido por el usuario (drag-and-drop) en el detalle de
     * precios al editar un producto.
     */
    public function up(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            $table->unsignedInteger('orden')->default(0)->after('factor');
            $table->index(['producto_almacen_id', 'orden']);
        });

        // Inicializar orden con el factor descendente para mantener
        // el orden actual de las unidades existentes.
        DB::statement("
            UPDATE productoalmacenunidadderivada p
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY producto_almacen_id
                           ORDER BY factor DESC, id ASC
                       ) - 1 AS rn
                FROM productoalmacenunidadderivada
            ) r ON r.id = p.id
            SET p.orden = r.rn
        ");
    }

    public function down(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            $table->dropIndex(['producto_almacen_id', 'orden']);
            $table->dropColumn('orden');
        });
    }
};
