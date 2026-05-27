<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // Agregar campo para indicar si el sorteo incluye un producto específico
            $table->boolean('sorteo_incluye_producto')->default(false)->after('tipo_promocion');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('sorteo_incluye_producto');
        });
    }
};
