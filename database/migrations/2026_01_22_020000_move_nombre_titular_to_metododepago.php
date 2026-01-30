<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar de desplieguedepago
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->dropColumn('nombre_titular');
        });
        
        // Agregar a metododepago
        Schema::table('metododepago', function (Blueprint $table) {
            $table->string('nombre_titular', 191)->nullable()->after('cuenta_bancaria');
        });
    }

    public function down(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            $table->dropColumn('nombre_titular');
        });
        
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->string('nombre_titular', 191)->nullable()->after('numero_celular');
        });
    }
};
