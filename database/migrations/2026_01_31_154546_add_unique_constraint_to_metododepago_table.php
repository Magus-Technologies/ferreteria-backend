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
        Schema::table('metododepago', function (Blueprint $table) {
            // Agregar índice único compuesto para name + cuenta_bancaria
            // Esto permite múltiples bancos con el mismo nombre pero diferentes cuentas
            $table->unique(['name', 'cuenta_bancaria'], 'unique_banco_cuenta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            // Eliminar el índice único
            $table->dropUnique('unique_banco_cuenta');
        });
    }
};
