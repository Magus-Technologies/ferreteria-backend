<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de CONCEPTOS de movimiento interno (etiquetas de solo nombre,
     * ej. "EFECTIVO A YAPE"). Reemplaza el uso de despliegues de pago en el
     * modal "Movimiento Interno entre Sub-Cajas": el concepto es descriptivo,
     * no un método de pago real.
     */
    public function up(): void
    {
        Schema::create('conceptos_movimiento_interno', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::table('movimientos_internos', function (Blueprint $table) {
            $table->string('concepto')->nullable()->after('despliegue_de_pago_destino_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_internos', function (Blueprint $table) {
            $table->dropColumn('concepto');
        });

        Schema::dropIfExists('conceptos_movimiento_interno');
    }
};
