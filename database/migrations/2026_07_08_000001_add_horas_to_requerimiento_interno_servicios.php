<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El formulario de Orden de Servicio siempre envió hora_inicio/hora_fin,
     * pero nunca existieron las columnas y el PDF mostraba la duración como
     * fallback. Además, la duración se guardaba mal: era la hora de inicio
     * convertida a minutos desde medianoche (14:00 → 840), no fin − inicio.
     */
    public function up(): void
    {
        Schema::table('requerimiento_interno_servicios', function (Blueprint $table) {
            $table->string('hora_inicio', 5)->nullable()->after('fecha_inicio_estimada');
            $table->string('hora_fin', 5)->nullable()->after('hora_inicio');
        });

        // Backfill: la hora de inicio quedó embebida en fecha_inicio_estimada
        // (el form la combinaba con la fecha). 00:00 se omite porque es el
        // valor que se usaba cuando la duración era en días (sin horario).
        DB::table('requerimiento_interno_servicios')
            ->whereNotNull('fecha_inicio_estimada')
            ->whereRaw("TIME(fecha_inicio_estimada) != '00:00:00'")
            ->update(['hora_inicio' => DB::raw("DATE_FORMAT(fecha_inicio_estimada, '%H:%i')")]);

        // Anular solo las duraciones probadamente corruptas: las que coinciden
        // exactamente con la hora de inicio expresada en minutos.
        DB::table('requerimiento_interno_servicios')
            ->where('duracion_unidad', 'minutos')
            ->whereNotNull('fecha_inicio_estimada')
            ->whereRaw('duracion_cantidad = HOUR(fecha_inicio_estimada) * 60 + MINUTE(fecha_inicio_estimada)')
            ->update(['duracion_cantidad' => null, 'duracion_unidad' => null]);
    }

    public function down(): void
    {
        Schema::table('requerimiento_interno_servicios', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin']);
        });
    }
};
