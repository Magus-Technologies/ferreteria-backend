<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // Máximo de vales distintos que pueden aplicarse en una misma venta.
            // Si varios vales tienen este campo configurado, gana el más restrictivo.
            // Null = sin límite (comportamiento anterior).
            $table->unsignedTinyInteger('max_vales_por_venta')->nullable()->after('tipo_umbral');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('max_vales_por_venta');
        });
    }
};
