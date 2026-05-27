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
            // Cuándo se aplica el beneficio:
            // - MISMA_COMPRA: el beneficio se aplica al cumplirse las condiciones en la misma venta
            // - PROXIMA_COMPRA: se genera un código que el cliente canjea en una venta posterior
            $table->enum('momento_aplicacion', ['MISMA_COMPRA', 'PROXIMA_COMPRA'])
                ->default('MISMA_COMPRA')
                ->after('tipo_promocion');
        });

        // Backfill: los vales que históricamente eran DESCUENTO_PROXIMA_COMPRA pasan a PROXIMA.
        DB::table('vales_compra')
            ->where('tipo_promocion', 'DESCUENTO_PROXIMA_COMPRA')
            ->update(['momento_aplicacion' => 'PROXIMA_COMPRA']);
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('momento_aplicacion');
        });
    }
};
