<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // DESTINO del descuento (la recompensa, PASO 4), independiente de la
            // condición (PASO 3). Define sobre qué cae el % o S/:
            //  - VENTA: toda la venta (comportamiento por defecto / legacy).
            //  - PRODUCTOS: solo los productos en descuento_producto_ids.
            //  - CATEGORIAS: solo los productos de descuento_categoria_ids.
            $table->enum('descuento_alcance', ['VENTA', 'PRODUCTOS', 'CATEGORIAS'])
                ->nullable()
                ->after('descuento_valor');
            // IDs destino (listas simples; no se consultan por relación, solo para el cálculo).
            $table->json('descuento_producto_ids')->nullable()->after('descuento_alcance');
            $table->json('descuento_categoria_ids')->nullable()->after('descuento_producto_ids');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn(['descuento_alcance', 'descuento_producto_ids', 'descuento_categoria_ids']);
        });
    }
};
