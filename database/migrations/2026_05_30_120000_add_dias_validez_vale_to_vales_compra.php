<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            // Días de validez del CÓDIGO generado al cliente en vales de PROXIMA_COMPRA.
            // El código vence en: fecha de la compra que lo generó + dias_validez_vale.
            // Null = no aplica (vales de MISMA_COMPRA) o sin vencimiento.
            $table->unsignedSmallInteger('dias_validez_vale')->nullable()->after('fecha_validez_vale');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('dias_validez_vale');
        });
    }
};
