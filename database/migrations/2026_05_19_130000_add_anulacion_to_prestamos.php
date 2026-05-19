<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->text('motivo_anulacion')->nullable()->after('estado_prestamo');
            $table->dateTime('fecha_anulacion', 3)->nullable()->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn(['motivo_anulacion', 'fecha_anulacion']);
        });
    }
};
