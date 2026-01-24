<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_internos', function (Blueprint $table) {
            // Agregar columnas origen y destino
            if (!Schema::hasColumn('movimientos_internos', 'despliegue_de_pago_origen_id')) {
                $table->string('despliegue_de_pago_origen_id', 191)->nullable()->after('monto')
                    ->comment('Método de pago origen (efectivo, transferencia, etc)');
            }
            
            if (!Schema::hasColumn('movimientos_internos', 'despliegue_de_pago_destino_id')) {
                $table->string('despliegue_de_pago_destino_id', 191)->nullable()->after('despliegue_de_pago_origen_id')
                    ->comment('Método de pago destino (efectivo, transferencia, etc)');
            }
        });
        
        // Migrar datos existentes si hay
        DB::statement('
            UPDATE movimientos_internos 
            SET despliegue_de_pago_origen_id = despliegue_de_pago_id,
                despliegue_de_pago_destino_id = despliegue_de_pago_id
            WHERE despliegue_de_pago_id IS NOT NULL
        ');
        
        // Eliminar la columna antigua
        Schema::table('movimientos_internos', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_internos', 'despliegue_de_pago_id')) {
                $table->dropColumn('despliegue_de_pago_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_internos', function (Blueprint $table) {
            // Restaurar columna antigua
            if (!Schema::hasColumn('movimientos_internos', 'despliegue_de_pago_id')) {
                $table->string('despliegue_de_pago_id', 191)->nullable()->after('monto')
                    ->comment('Método de pago usado (efectivo, transferencia, etc)');
            }
        });
        
        // Migrar datos de vuelta (usar origen como valor por defecto)
        DB::statement('
            UPDATE movimientos_internos 
            SET despliegue_de_pago_id = despliegue_de_pago_origen_id
            WHERE despliegue_de_pago_origen_id IS NOT NULL
        ');
        
        Schema::table('movimientos_internos', function (Blueprint $table) {
            $table->dropColumn(['despliegue_de_pago_origen_id', 'despliegue_de_pago_destino_id']);
        });
    }
};
