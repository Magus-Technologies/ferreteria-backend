<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // Cómo se interpreta `cantidad_minima` (el umbral de activación):
            // - MONTO: el umbral está en soles (suma de la venta debe superar X)
            // - CANTIDAD: el umbral está en unidades (cantidad de productos)
            // Nullable para vales antiguos: si es null, el backend infiere como antes.
            $table->enum('tipo_umbral', ['MONTO', 'CANTIDAD'])
                ->nullable()
                ->after('cantidad_minima');
        });

        // Backfill de vales existentes según la inferencia actual de esUmbralPorUnidades,
        // para que su comportamiento y su visualización no cambien.
        // CANTIDAD si el beneficio es por unidades (PRODUCTO_GRATIS/DOS_POR_UNO) o la
        // modalidad es POR_PRODUCTOS/MIXTO; en caso contrario, MONTO.
        DB::table('vales_compra')
            ->whereIn('tipo_promocion', ['PRODUCTO_GRATIS', 'DOS_POR_UNO'])
            ->orWhereIn('modalidad', ['POR_PRODUCTOS', 'MIXTO'])
            ->update(['tipo_umbral' => 'CANTIDAD']);

        DB::table('vales_compra')
            ->whereNull('tipo_umbral')
            ->update(['tipo_umbral' => 'MONTO']);
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('tipo_umbral');
        });
    }
};
