<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Corrige la inicialización del sistema PEPS para productos existentes
     * que tienen stock pero no tienen costo_actual inicializado
     */
    public function up(): void
    {
        // Actualizar todos los ProductoAlmacen que tienen stock_fraccion > 0
        // pero costo_actual es null o 0
        DB::table('productoalmacen')
            ->where('stock_fraccion', '>', 0)
            ->where(function ($query) {
                $query->whereNull('costo_actual')
                    ->orWhere('costo_actual', 0);
            })
            ->update([
                'costo_actual' => DB::raw('costo'),
                'stock_costo_actual' => DB::raw('stock_fraccion'),
                'costo_anterior' => null,
                'stock_costo_anterior' => 0,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacer nada en el rollback
    }
};
