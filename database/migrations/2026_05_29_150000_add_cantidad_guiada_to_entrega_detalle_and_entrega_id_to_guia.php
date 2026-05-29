<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite rastrear cuánto se guió POR ENTREGA (no solo por línea de venta).
     * `entrega_detalle.cantidad_guiada` acumula lo guiado de ese detalle puntual;
     * `guia_remision.entrega_id` registra desde qué entrega se creó la guía, para
     * poder revertir el `cantidad_guiada` de la entrega al anularla.
     */
    public function up(): void
    {
        Schema::table('entrega_detalle', function (Blueprint $table) {
            $table->decimal('cantidad_guiada', 12, 3)->default(0)->after('cantidad');
        });

        Schema::table('guia_remision', function (Blueprint $table) {
            $table->unsignedBigInteger('entrega_id')->nullable()->after('venta_id');
            $table->index('entrega_id');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_detalle', function (Blueprint $table) {
            $table->dropColumn('cantidad_guiada');
        });

        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropIndex(['entrega_id']);
            $table->dropColumn('entrega_id');
        });
    }
};
