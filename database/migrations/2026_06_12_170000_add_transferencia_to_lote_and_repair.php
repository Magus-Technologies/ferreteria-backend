<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Agrega `transferencia_stock_id` al lote: las transferencias ahora mueven
     *    LOTES (consumen FIFO en el origen y crean en el destino lotes con los
     *    mismos costos). La columna permite anular la transferencia quitando
     *    exactamente los lotes que creó en el destino.
     *
     * 2. REPARA los productos descuadrados por transferencias antiguas (movían
     *    stock_fraccion sin tocar lotes): si la suma de lotes difiere del stock,
     *    ajusta — falta en lotes → lote de ajuste al costo actual; sobra en
     *    lotes → consume FIFO el exceso.
     */
    public function up(): void
    {
        if (! Schema::hasTable('productoalmacen_lote')) {
            return;
        }

        if (! Schema::hasColumn('productoalmacen_lote', 'transferencia_stock_id')) {
            Schema::table('productoalmacen_lote', function (Blueprint $table) {
                $table->unsignedBigInteger('transferencia_stock_id')->nullable()->after('ingreso_salida_id')->index();
            });
        }

        // ── 2. Reparación de desbalances lotes vs stock_fraccion ──
        $sumas = DB::table('productoalmacen_lote')
            ->select('producto_almacen_id', DB::raw('SUM(cantidad_restante) as suma'))
            ->groupBy('producto_almacen_id')
            ->pluck('suma', 'producto_almacen_id');

        $loteService = app(\App\Services\Producto\ProductoLoteService::class);

        DB::table('productoalmacen')->orderBy('id')->chunkById(500, function ($filas) use ($sumas, $loteService) {
            foreach ($filas as $fila) {
                $stock = (float) ($fila->stock_fraccion ?? 0);
                $suma = (float) ($sumas[$fila->id] ?? 0);
                $delta = $stock - $suma;

                if (abs($delta) < 0.001) {
                    continue;
                }

                $pa = \App\Models\ProductoAlmacen::find($fila->id);
                if (! $pa) {
                    continue;
                }

                if ($delta > 0) {
                    // Falta stock en lotes (ej. destino de transferencia vieja):
                    // lote de ajuste al último costo conocido.
                    $loteService->registrarLote(
                        $pa,
                        (float) ($pa->costo_actual ?? $pa->costo ?? 0),
                        $delta
                    );
                } else {
                    // Lotes exceden el stock (ej. origen de transferencia vieja):
                    // consumir FIFO el exceso (sin registrar consumo de documento).
                    $loteService->consumirLotes($pa, abs($delta), null);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('productoalmacen_lote', 'transferencia_stock_id')) {
            Schema::table('productoalmacen_lote', function (Blueprint $table) {
                $table->dropColumn('transferencia_stock_id');
            });
        }
    }
};
