<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los datos del transportista TERCERO a `guia_remision`.
 *
 * Aplica cuando `modalidad_transporte = 'PUBLICO'` (transporte
 * tercerizado, mod_traslado SUNAT '01'): el Catálogo N° 18 de SUNAT
 * exige informar el RUC (11 dígitos) y la razón social de la empresa
 * de transporte que ejecuta el traslado.
 *
 * Diferencia con GRE-Transportista (`tipo_guia = 'ELECTRONICA_TRANSPORTISTA'`):
 * ahí la empresa EMISORA es el transportista (se arma con los datos de
 * `Empresa`/`config('sunat-api')`), por lo que estos campos no aplican.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->string('transportista_ruc', 11)->nullable()->after('modalidad_transporte');
            $table->string('transportista_razon_social')->nullable()->after('transportista_ruc');
            $table->string('transportista_nro_mtc')->nullable()->after('transportista_razon_social');
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropColumn(['transportista_ruc', 'transportista_razon_social', 'transportista_nro_mtc']);
        });
    }
};
