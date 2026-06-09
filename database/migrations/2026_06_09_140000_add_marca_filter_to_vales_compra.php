<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filtro opcional por MARCA, además de la categoría, en dos lugares del vale:
     *  - marca_ids: condición para GANAR el vale (PASO 3, junto a la categoría).
     *  - descuento_marca_ids: destino donde se APLICA el descuento (PASO 4).
     *
     * Vacío/null = todas las marcas (comportamiento actual). Si tiene marcas, el
     * producto califica solo si su marca está en la lista (y además cumple la categoría).
     */
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            if (!Schema::hasColumn('vales_compra', 'marca_ids')) {
                $table->json('marca_ids')->nullable()->after('modalidad');
            }
            if (!Schema::hasColumn('vales_compra', 'descuento_marca_ids')) {
                $table->json('descuento_marca_ids')->nullable()->after('descuento_categoria_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn(['marca_ids', 'descuento_marca_ids']);
        });
    }
};
