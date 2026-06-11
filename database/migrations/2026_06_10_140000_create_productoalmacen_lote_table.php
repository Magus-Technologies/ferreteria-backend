<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger PEPS por capas (lotes): una fila por cada entrada de costo de un
     * producto en un almacén. Reemplaza el modelo de 2 buckets fijos
     * (costo_anterior/costo_actual), que solo recordaba 2 costos y perdía los
     * lotes más viejos cuando entraban más de 2 costos distintos.
     *
     * Cada recepción/ingreso crea un lote con su costo REAL (crudo + flete
     * prorrateado). Las ventas/salidas consumen los lotes en orden FIFO
     * (el más viejo primero) descontando `cantidad_restante`.
     *
     * Los campos derivados de `productoalmacen` (costo, stock_fraccion,
     * costo_anterior/actual, stock_costo_anterior/actual, costo_con_flete) se
     * recalculan desde estos lotes para mantener compatibilidad con todo lo que
     * ya los lee.
     */
    public function up(): void
    {
        if (Schema::hasTable('productoalmacen_lote')) {
            return;
        }

        Schema::create('productoalmacen_lote', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_almacen_id');
            // Origen del lote (informativo). Se permite null para lotes de backfill.
            $table->unsignedBigInteger('recepcion_id')->nullable();
            $table->unsignedBigInteger('ingreso_salida_id')->nullable();
            // Costo REAL del lote (crudo + flete prorrateado) por unidad fracción.
            $table->decimal('costo', 16, 4);
            $table->decimal('cantidad_inicial', 16, 3);
            // Lo que queda por consumir. Puede quedar negativo en el último lote
            // si se vende más stock del disponible (igual que el modelo anterior).
            $table->decimal('cantidad_restante', 16, 3);
            // Orden FIFO: menor = más viejo (sale primero). Empata por id.
            $table->unsignedBigInteger('secuencia')->default(0);
            $table->timestamps();

            $table->index(['producto_almacen_id', 'secuencia'], 'idx_lote_pa_secuencia');
            $table->index(['producto_almacen_id', 'cantidad_restante'], 'idx_lote_pa_restante');
            $table->index('recepcion_id');
            $table->index('ingreso_salida_id');
        });

        // Consumo de lotes: registra qué lotes consumió cada venta/salida y a qué
        // costo. Permite (1) anular con exactitud devolviendo el stock al lote
        // correcto y (2) el reporte de pérdidas con N filas reales por venta.
        if (! Schema::hasTable('productoalmacen_lote_consumo')) {
            Schema::create('productoalmacen_lote_consumo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lote_id');
                $table->unsignedBigInteger('producto_almacen_id');
                $table->decimal('cantidad', 16, 3);
                $table->decimal('costo', 16, 4);
                // Origen del consumo: 'venta' | 'salida'. id del documento de origen.
                // String porque la venta usa id ULID (no entero).
                $table->string('origen_tipo', 20);
                $table->string('origen_id', 40);
                $table->timestamps();

                $table->index('lote_id');
                $table->index(['origen_tipo', 'origen_id'], 'idx_consumo_origen');
                $table->index('producto_almacen_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('productoalmacen_lote_consumo');
        Schema::dropIfExists('productoalmacen_lote');
    }
};
