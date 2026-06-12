<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regla: las recepciones PARCIALES de la MISMA compra deben sumar al MISMO
     * lote (misma fila de costo), no crear una fila nueva por recepción.
     *
     * 1. Agrega `compra_id` al lote (la compra de origen, vía su recepción).
     * 2. Backfill: toma la compra desde la recepción que creó cada lote.
     * 3. Fusiona lotes duplicados (mismo producto+compra+costo): suma cantidades
     *    en el lote MÁS VIEJO (posición FIFO de la primera recepción), re-apunta
     *    los consumos y elimina los sobrantes. Luego resincroniza los derivados.
     */
    public function up(): void
    {
        if (! Schema::hasTable('productoalmacen_lote')) {
            return;
        }

        if (! Schema::hasColumn('productoalmacen_lote', 'compra_id')) {
            Schema::table('productoalmacen_lote', function (Blueprint $table) {
                $table->string('compra_id', 40)->nullable()->after('recepcion_id')->index();
            });
        }

        // 2. Backfill desde la recepción de origen
        DB::statement('
            UPDATE productoalmacen_lote l
            JOIN recepcionalmacen r ON r.id = l.recepcion_id
            SET l.compra_id = r.compra_id
            WHERE l.recepcion_id IS NOT NULL AND r.compra_id IS NOT NULL AND l.compra_id IS NULL
        ');

        // 3. Fusionar duplicados (mismo producto_almacen + compra + costo)
        $grupos = DB::table('productoalmacen_lote')
            ->select('producto_almacen_id', 'compra_id', 'costo', DB::raw('COUNT(*) as n'))
            ->whereNotNull('compra_id')
            ->groupBy('producto_almacen_id', 'compra_id', 'costo')
            ->having('n', '>', 1)
            ->get();

        $pasAfectados = [];

        foreach ($grupos as $g) {
            $lotes = DB::table('productoalmacen_lote')
                ->where('producto_almacen_id', $g->producto_almacen_id)
                ->where('compra_id', $g->compra_id)
                ->where('costo', $g->costo)
                ->orderBy('secuencia')->orderBy('id')
                ->get();

            $principal = $lotes->first();
            $resto = $lotes->slice(1);

            $sumInicial = (float) $lotes->sum('cantidad_inicial');
            $sumRestante = (float) $lotes->sum('cantidad_restante');

            DB::table('productoalmacen_lote')->where('id', $principal->id)->update([
                'cantidad_inicial' => $sumInicial,
                'cantidad_restante' => $sumRestante,
            ]);

            $idsEliminar = $resto->pluck('id')->all();
            if (! empty($idsEliminar)) {
                // Re-apuntar consumos al lote principal antes de eliminar
                DB::table('productoalmacen_lote_consumo')
                    ->whereIn('lote_id', $idsEliminar)
                    ->update(['lote_id' => $principal->id]);

                DB::table('productoalmacen_lote')->whereIn('id', $idsEliminar)->delete();
            }

            $pasAfectados[$g->producto_almacen_id] = true;
        }

        // Resincronizar derivados de los productos tocados
        foreach (array_keys($pasAfectados) as $paId) {
            $pa = \App\Models\ProductoAlmacen::find($paId);
            if ($pa) {
                app(\App\Services\Producto\ProductoLoteService::class)->resyncDerivados($pa);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productoalmacen_lote', 'compra_id')) {
            Schema::table('productoalmacen_lote', function (Blueprint $table) {
                $table->dropColumn('compra_id');
            });
        }
    }
};
