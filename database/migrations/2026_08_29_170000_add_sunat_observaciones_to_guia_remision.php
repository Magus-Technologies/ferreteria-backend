<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observaciones del CDR de SUNAT (los <cbc:Note> con códigos 4xxx).
 *
 * SUNAT acepta el documento (ResponseCode 0) pero avisa lo que no pudo
 * validar contra las bases del MTC — por ejemplo "El Número de placa no se
 * encuentra en las bases consultadas". Hasta ahora esas notas se perdían y el
 * emisor solo las veía descargando el CDR y abriendo el XML a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->json('sunat_observaciones')->nullable()->after('sunat_mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropColumn('sunat_observaciones');
        });
    }
};
