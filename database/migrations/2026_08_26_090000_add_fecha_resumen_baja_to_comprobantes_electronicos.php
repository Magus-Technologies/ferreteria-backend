<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El correlativo del Resumen Diario de SUNAT está acotado por su propio
 * `fecha_resumen` (la fecha de las boletas que resume), no por la fecha real
 * en que lo enviamos. Hasta ahora `fecha_resumen` siempre se mandaba como
 * "hoy" — este cambio permite usar la fecha de emisión real de la boleta
 * cuando nunca fue aceptada por SUNAT (ver SunatApiService::generarYEnviarResumenBaja).
 * Esta columna guarda qué `fecha_resumen` se usó realmente en cada baja
 * aceptada, para poder contar el correlativo correcto por esa fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->date('fecha_resumen_baja')->nullable()->after('fecha_respuesta_sunat');
        });

        // Backfill: hasta ahora fecha_resumen SIEMPRE se mandó como "hoy"
        // (fecha_respuesta_sunat), así que para las bajas ya aceptadas es
        // exacto reconstruirla desde ese campo.
        DB::table('comprobantes_electronicos')
            ->where('tipo_comprobante', '03')
            ->where('estado_sunat', 'BAJA_ACEPTADA')
            ->whereNotNull('fecha_respuesta_sunat')
            ->update(['fecha_resumen_baja' => DB::raw('DATE(fecha_respuesta_sunat)')]);
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropColumn('fecha_resumen_baja');
        });
    }
};
