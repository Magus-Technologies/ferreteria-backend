<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `cantidad_guiada` a `unidadderivadainmutableventa`.
 *
 * Propósito: rastrear cuántas unidades de cada línea de venta ya fueron
 * incluidas en guías de remisión (BORRADOR, EMITIDA o ACEPTADA — no ANULADA).
 *
 * Separado de `cantidad_pendiente` (que es para entregas) porque son
 * flujos independientes: se puede entregar sin guía y guiar sin entregar.
 *
 * cantidad_disponible_para_guiar = cantidad - cantidad_guiada
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidadderivadainmutableventa', function (Blueprint $table) {
            $table->decimal('cantidad_guiada', 10, 3)->default(0)->after('cantidad_pendiente');
        });
    }

    public function down(): void
    {
        Schema::table('unidadderivadainmutableventa', function (Blueprint $table) {
            $table->dropColumn('cantidad_guiada');
        });
    }
};
