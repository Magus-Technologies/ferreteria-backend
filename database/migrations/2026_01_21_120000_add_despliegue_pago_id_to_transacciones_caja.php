<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transacciones_caja')) {
            Schema::table('transacciones_caja', function (Blueprint $table) {
                if (!Schema::hasColumn('transacciones_caja', 'despliegue_pago_id')) {
                    $table->string('despliegue_pago_id', 191)->nullable()->after('descripcion')
                        ->comment('Método de pago usado en la transacción');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transacciones_caja')) {
            Schema::table('transacciones_caja', function (Blueprint $table) {
                if (Schema::hasColumn('transacciones_caja', 'despliegue_pago_id')) {
                    $table->dropColumn('despliegue_pago_id');
                }
            });
        }
    }
};
