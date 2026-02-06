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
        Schema::table('pagodecompra', function (Blueprint $table) {
            // Primero eliminar la primary key existente
            $table->dropPrimary(['id']);
        });

        Schema::table('pagodecompra', function (Blueprint $table) {
            // Cambiar id de integer a string (ULID)
            $table->string('id', 26)->change();
            // Agregar la nueva primary key
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->integer('id')->change();
            $table->primary('id');
        });
    }
};
