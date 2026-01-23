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
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->string('numero_celular', 20)->nullable()->unique()->after('tipo_sobrecargo');
        });
        
        // Agregar índice único a cuenta_bancaria en metododepago si no existe
        Schema::table('metododepago', function (Blueprint $table) {
            // Primero verificar si la columna existe y no tiene índice único
            $table->string('cuenta_bancaria', 191)->nullable()->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->dropUnique(['numero_celular']);
            $table->dropColumn('numero_celular');
        });
        
        Schema::table('metododepago', function (Blueprint $table) {
            $table->dropUnique(['cuenta_bancaria']);
        });
    }
};
