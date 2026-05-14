<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Regulariza productos legacy para que los buckets PEPS reflejen el
     * stock_fraccion real, incluyendo stocks negativos y filas desincronizadas.
     */
    public function up(): void
    {
        DB::table('productoalmacen')
            ->where(function ($query) {
                $query
                    ->where(function ($q) {
                        $q->where('stock_fraccion', '!=', 0)
                            ->where(function ($sub) {
                                $sub->whereNull('costo_actual')
                                    ->orWhere('costo_actual', 0);
                            })
                            ->where(function ($sub) {
                                $sub->whereNull('stock_costo_actual')
                                    ->orWhere('stock_costo_actual', 0);
                            })
                            ->where(function ($sub) {
                                $sub->whereNull('stock_costo_anterior')
                                    ->orWhere('stock_costo_anterior', 0);
                            });
                    })
                    ->orWhereRaw('COALESCE(stock_costo_anterior, 0) + COALESCE(stock_costo_actual, 0) <> stock_fraccion');
            })
            ->update([
                'costo_actual' => DB::raw('costo'),
                'stock_costo_actual' => DB::raw('stock_fraccion'),
                'costo_anterior' => null,
                'stock_costo_anterior' => 0,
            ]);
    }

    public function down(): void
    {
        // No rollback automático: esta regularización consolida buckets legacy.
    }
};
