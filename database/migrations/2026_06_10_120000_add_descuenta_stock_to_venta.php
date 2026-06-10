<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca si la venta descuenta stock. `false` = venta administrativa
     * (descontar_stock='no': el cliente ya tenía el producto). Estas ventas NO
     * cuentan en el reporte de Ganancias (no hubo costo real de inventario).
     * Las ventas normales y las de entrega pendiente quedan en `true`.
     */
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            if (!Schema::hasColumn('venta', 'descuenta_stock')) {
                $table->boolean('descuenta_stock')->default(true)->after('stock_aplicado');
            }
        });

        // Backfill: ventas existentes se consideran que sí descuentan stock.
        DB::table('venta')->whereNull('descuenta_stock')->update(['descuenta_stock' => true]);
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropColumnIfExists('descuenta_stock');
        });
    }
};
