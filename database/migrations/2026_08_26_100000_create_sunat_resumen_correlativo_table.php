<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correlativo de Resumen Diario por fecha de emisión del resumen.
 *
 * SUNAT exige que el correlativo sea único por (RUC, fecha del resumen):
 * forma parte del nombre del ZIP (RC-{fecha}-{correlativo}) y repetirlo
 * devuelve "[99] nombre del archivo ZIP incorrecto".
 *
 * Antes se derivaba contando las bajas ya aceptadas ese día, lo que alcanzaba
 * mientras cada baja gastara exactamente un correlativo. Dejó de alcanzar
 * cuando una baja puede necesitar DOS envíos (declarar + dar de baja, ver
 * SunatApiService::generarYEnviarResumenBaja): el segundo envío no quedaba
 * contado en ningún lado y el próximo intento reusaba un número ya quemado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunat_resumen_correlativo', function (Blueprint $table) {
            $table->date('fecha')->primary();
            $table->unsignedInteger('ultimo')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_resumen_correlativo');
    }
};
