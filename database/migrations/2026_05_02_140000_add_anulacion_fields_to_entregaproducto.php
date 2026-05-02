<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de auditoría de anulación a `entregaproducto`.
 *
 * Cuando un usuario marca una entrega como entregada por error, puede
 * anularla — la entrega vuelve a estado 'pe' (pendiente) pero quedan
 * registrados estos 3 campos para rastrear el incidente.
 *
 * Si la entrega vuelve a marcarse como 'en' (entregada de verdad), los
 * 3 campos se limpian (esto se hace en el controller al hacer update).
 *
 * Patrón inspirado en `guia_remision.fecha_anulacion` / `motivo_anulacion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->datetime('fecha_anulacion')->nullable()->after('aceptado_at');
            $table->text('motivo_anulacion')->nullable()->after('fecha_anulacion');
            // user.id es varchar(191) (ULID), igual que en guia_remision.
            $table->string('user_anulacion_id', 191)->nullable()->after('motivo_anulacion');
            $table->foreign('user_anulacion_id')->references('id')->on('user')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropForeign(['user_anulacion_id']);
            $table->dropColumn(['fecha_anulacion', 'motivo_anulacion', 'user_anulacion_id']);
        });
    }
};
