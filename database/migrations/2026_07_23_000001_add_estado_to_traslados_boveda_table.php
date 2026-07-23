<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traslados_boveda', function (Blueprint $table) {
            $table->string('estado', 20)->default('activo')->after('justificacion');
            $table->timestamp('fecha_anulacion')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('traslados_boveda', function (Blueprint $table) {
            $table->dropColumn(['estado', 'fecha_anulacion']);
        });
    }
};
