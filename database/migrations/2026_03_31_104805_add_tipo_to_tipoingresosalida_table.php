<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tipoingresosalida', function (Blueprint $table) {
            // Agregar campo tipo: 'ingreso', 'salida', 'ambos'
            $table->enum('tipo', ['ingreso', 'salida', 'ambos'])->default('ambos')->after('name');
        });

        // Actualizar registros existentes según su uso común
        DB::table('tipoingresosalida')->where('name', 'AJUSTE')->update(['tipo' => 'ambos']);
        DB::table('tipoingresosalida')->where('name', 'CUADRE INVENTARIO')->update(['tipo' => 'ambos']);
        DB::table('tipoingresosalida')->where('name', 'MERMA')->update(['tipo' => 'salida']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipoingresosalida', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
