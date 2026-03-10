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
        Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
            // Hacer producto_id nullable
            $table->integer('producto_id')->nullable()->change();
            
            // Agregar nombre_adicional para productos manuales
            $table->string('nombre_adicional', 255)->nullable()->after('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
            $table->string('nombre_adicional')->nullable(false)->change(); // This is just to satisfy some DBs, but actually we want to drop it
            $table->dropColumn('nombre_adicional');
            $table->integer('producto_id')->nullable(false)->change();
        });
    }
};
