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
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            // Agregar campo para guardar el estado anterior de la compra
            // Esto permite restaurar el estado correcto cuando se deshace una recepción
            if (!Schema::hasColumn('recepcionalmacen', 'estado_compra_anterior')) {
                $table->string('estado_compra_anterior')->nullable()->after('es_finalizacion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            if (Schema::hasColumn('recepcionalmacen', 'estado_compra_anterior')) {
                $table->dropColumn('estado_compra_anterior');
            }
        });
    }
};
