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
            $table->enum('estado_cierre', ['pendiente', 'en_proceso', 'aprobado'])
                ->nullable()
                ->default(null)
                ->after('estado')
                ->comment('Estado del cierre: pendiente (sin supervisor), en_proceso, aprobado (con supervisor)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropColumn('estado_cierre');
        });
    }
};
