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
            // Eliminar el índice único viejo del campo 'name'
            $table->dropUnique('MetodoDePago_name_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            // Restaurar el índice único en 'name' si se revierte
            $table->unique('name', 'MetodoDePago_name_key');
        });
    }
};
