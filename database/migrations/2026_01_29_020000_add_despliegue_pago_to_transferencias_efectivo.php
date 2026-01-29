<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencias_efectivo_vendedores', function (Blueprint $table) {
            $table->char('despliegue_pago_origen_id', 26)->nullable()->after('vendedor_origen_id')
                ->comment('Método de pago específico usado (ej: Caja Chica/Efectivo/Efectivo)');
            
            $table->foreign('despliegue_pago_origen_id', 'fk_transf_despliegue')
                ->references('id')->on('desplieguedepago')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('transferencias_efectivo_vendedores', function (Blueprint $table) {
            $table->dropForeign('fk_transf_despliegue');
            $table->dropColumn('despliegue_pago_origen_id');
        });
    }
};
