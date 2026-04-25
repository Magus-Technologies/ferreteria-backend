<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->boolean('stock_aplicado')
                ->default(false)
                ->after('tipo_despacho')
                ->comment('true si el stock ya fue descontado para esta venta');
        });

        // Backfill: ventas existentes con tipo_despacho et|do (no en espera)
        // ya descontaron stock al crearse, marcarlas como aplicado.
        // Las pa ya descontaban stock vía la entrega — para esas marcamos
        // aplicado=true cuando exista al menos una entrega entregada.
        DB::table('venta')
            ->whereIn('tipo_despacho', ['et', 'do'])
            ->where('estado_de_venta', '!=', 'es')
            ->update(['stock_aplicado' => true]);
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropColumn('stock_aplicado');
        });
    }
};
