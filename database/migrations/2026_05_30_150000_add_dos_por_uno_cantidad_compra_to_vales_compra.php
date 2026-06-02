<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // "Unidades que debe comprar" del 2x1 (tamaño del grupo), SEPARADO de
            // cantidad_minima. Necesario en PROXIMA_COMPRA, donde cantidad_minima es la
            // CONDICIÓN para ganar el código y el 2x1 es la RECOMPENSA (otro número).
            // Null = vales 2x1 antiguos que usaban cantidad_minima como tamaño de grupo.
            $table->decimal('dos_por_uno_cantidad_compra', 10, 3)->nullable()->after('cantidad_producto_gratis');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('dos_por_uno_cantidad_compra');
        });
    }
};
