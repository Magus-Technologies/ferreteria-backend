<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_extras', function (Blueprint $table) {
            if (!Schema::hasColumn('gastos_extras', 'estado')) {
                // Espeja ingresos_extras. Default 'aprobado' para que los gastos ya
                // existentes (sin estado) sigan visibles con el filtro estado=aprobado.
                $table->enum('estado', ['pendiente', 'aprobado', 'anulado'])
                    ->default('aprobado')
                    ->after('despliegue_pago_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gastos_extras', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
