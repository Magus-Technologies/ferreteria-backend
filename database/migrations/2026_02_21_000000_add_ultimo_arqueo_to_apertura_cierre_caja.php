<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            // Agregar campo para guardar la fecha del último arqueo diario
            // Esto permite hacer múltiples arqueos sin cerrar la caja
            $table->timestamp('fecha_ultimo_arqueo')->nullable()->after('fecha_cierre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropColumn('fecha_ultimo_arqueo');
        });
    }
};
