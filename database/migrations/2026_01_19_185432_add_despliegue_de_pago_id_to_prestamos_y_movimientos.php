<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar a prestamos_entre_cajas
        if (Schema::hasTable('prestamos_entre_cajas')) {
            Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
                if (!Schema::hasColumn('prestamos_entre_cajas', 'despliegue_de_pago_id')) {
                    $table->string('despliegue_de_pago_id', 191)->nullable()->after('monto')
                        ->comment('Método de pago usado (efectivo, transferencia, etc)');
                }
            });
        }

        // Agregar a movimientos_internos
        if (Schema::hasTable('movimientos_internos')) {
            Schema::table('movimientos_internos', function (Blueprint $table) {
                if (!Schema::hasColumn('movimientos_internos', 'despliegue_de_pago_id')) {
                    $table->string('despliegue_de_pago_id', 191)->nullable()->after('monto')
                        ->comment('Método de pago usado (efectivo, transferencia, etc)');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prestamos_entre_cajas')) {
            Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
                $table->dropColumn('despliegue_de_pago_id');
            });
        }

        if (Schema::hasTable('movimientos_internos')) {
            Schema::table('movimientos_internos', function (Blueprint $table) {
                $table->dropColumn('despliegue_de_pago_id');
            });
        }
    }
};
