<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            // Distingue una venta anulada por una Nota de Crédito (anulación total /
            // devolución total) de una anulada manualmente con la papelera.
            $table->boolean('anulado_por_nota_credito')
                ->default(false)
                ->nullable()
                ->after('descuenta_stock');
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropColumn('anulado_por_nota_credito');
        });
    }
};
