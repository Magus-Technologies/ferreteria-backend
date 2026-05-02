<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `user_chofer_id` a `guia_remision` para que el chofer en
 * **transporte privado** (modalidad_transporte = 'PRIVADO') sea un USER
 * de la empresa (despachador interno) en lugar de un chofer externo de
 * la tabla `chofer`.
 *
 * Diferenciación:
 *  - `chofer_id`        (existente) → tabla `chofer`. Choferes externos con
 *      MTC. Usado para transporte PÚBLICO o GRE-Transportista.
 *  - `user_chofer_id`   (nuevo)     → tabla `user`. Despachadores internos
 *      con DNI + licencia_conducir + vehiculo_id. Usado para transporte
 *      PRIVADO o cuando se "convierte a guía" desde mis-entregas (donde
 *      el `chofer_id` de la entrega ya apunta al user despachador).
 *
 * El backend decide cuál usar al armar el payload SUNAT según la modalidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            // user.id es varchar(191) (ULID), NO integer.
            $table->string('user_chofer_id', 191)->nullable()->after('chofer_id');
            $table->foreign('user_chofer_id')->references('id')->on('user')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropForeign(['user_chofer_id']);
            $table->dropColumn('user_chofer_id');
        });
    }
};
