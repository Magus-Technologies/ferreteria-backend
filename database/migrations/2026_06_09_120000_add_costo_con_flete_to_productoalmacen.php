<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega costo_con_flete: costo del producto incluyendo el flete prorrateado
     * de la última compra. El campo `costo` se mantiene CRUDO (precio del proveedor);
     * costo_con_flete es el costo real usado para ventas/margen y el informativo "Costo:".
     */
    public function up(): void
    {
        Schema::table('productoalmacen', function (Blueprint $table) {
            if (!Schema::hasColumn('productoalmacen', 'costo_con_flete')) {
                $table->decimal('costo_con_flete', 16, 4)->nullable()->after('costo_actual');
            }
        });

        // Backfill: para filas existentes, costo_con_flete = costo (aún sin flete conocido)
        DB::table('productoalmacen')->whereNull('costo_con_flete')->update([
            'costo_con_flete' => DB::raw('costo'),
        ]);
    }

    public function down(): void
    {
        Schema::table('productoalmacen', function (Blueprint $table) {
            $table->dropColumnIfExists('costo_con_flete');
        });
    }
};
