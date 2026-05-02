<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `remitente_id` a `guia_remision` para soportar GRE-Transportista.
 *
 * En GRE-Transportista (`tipo_guia = ELECTRONICA_TRANSPORTISTA`, tipoDoc SUNAT
 * '31') la empresa emisora actúa como TRANSPORTISTA y se necesita identificar
 * al cliente que CONTRATA el servicio de transporte (dueño de los bienes).
 * Ese cliente se guarda en `remitente_id` y se mapea a Greenter `setTercero`.
 *
 * En GRE-Remitente (tipoDoc '09') esta columna queda en NULL — la empresa
 * emisora ya es el remitente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->integer('remitente_id')->nullable()->after('comprador_id');
            $table->foreign('remitente_id')->references('id')->on('cliente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropForeign(['remitente_id']);
            $table->dropColumn('remitente_id');
        });
    }
};
