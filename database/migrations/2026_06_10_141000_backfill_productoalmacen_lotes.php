<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill inicial del ledger de lotes a partir de los buckets actuales
     * (costo_anterior/stock_costo_anterior y costo_actual/stock_costo_actual).
     *
     * Por cada producto con stock y sin lotes:
     *   - bucket anterior (más viejo) → lote secuencia 1
     *   - bucket actual (o todo el stock si no hay desglose) → lote secuencia 2
     *
     * Es idempotente: salta los productos que ya tengan lotes.
     */
    public function up(): void
    {
        if (! Schema::hasTable('productoalmacen_lote') || ! Schema::hasTable('productoalmacen')) {
            return;
        }

        $ahora = now();

        DB::table('productoalmacen')->orderBy('id')->chunkById(500, function ($filas) use ($ahora) {
            foreach ($filas as $pa) {
                $stockTotal = (float) ($pa->stock_fraccion ?? 0);
                if (abs($stockTotal) < 0.0001) {
                    continue; // sin stock que respaldar
                }

                $yaTieneLotes = DB::table('productoalmacen_lote')
                    ->where('producto_almacen_id', $pa->id)->exists();
                if ($yaTieneLotes) {
                    continue;
                }

                $stockAnterior = (float) ($pa->stock_costo_anterior ?? 0);
                $stockActual = (float) ($pa->stock_costo_actual ?? 0);
                $costoAnterior = $pa->costo_anterior !== null ? (float) $pa->costo_anterior : null;
                $costoActual = $pa->costo_actual !== null ? (float) $pa->costo_actual : (float) ($pa->costo ?? 0);

                $lotes = [];
                $secuencia = 0;

                if ($stockAnterior > 0 && $costoAnterior !== null) {
                    $lotes[] = [
                        'producto_almacen_id' => $pa->id,
                        'recepcion_id' => null,
                        'ingreso_salida_id' => null,
                        'costo' => $costoAnterior,
                        'cantidad_inicial' => $stockAnterior,
                        'cantidad_restante' => $stockAnterior,
                        'secuencia' => ++$secuencia,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                $stockActualLote = $stockActual > 0 ? $stockActual : ($stockTotal - max($stockAnterior, 0));
                if (abs($stockActualLote) >= 0.0001) {
                    $lotes[] = [
                        'producto_almacen_id' => $pa->id,
                        'recepcion_id' => null,
                        'ingreso_salida_id' => null,
                        'costo' => $costoActual,
                        'cantidad_inicial' => $stockActualLote,
                        'cantidad_restante' => $stockActualLote,
                        'secuencia' => ++$secuencia,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if (! empty($lotes)) {
                    DB::table('productoalmacen_lote')->insert($lotes);
                }
            }
        });
    }

    public function down(): void
    {
        // Solo borra los lotes de backfill (sin origen de recepción/ingreso).
        DB::table('productoalmacen_lote')
            ->whereNull('recepcion_id')
            ->whereNull('ingreso_salida_id')
            ->delete();
    }
};
