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
        // Insertar tipos de movimiento para préstamos
        DB::table('tipoingresosalida')->insert([
            ['name' => 'PRESTAMO', 'tipo' => 'ambos', 'estado' => 1],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tipoingresosalida')->where('name', 'PRESTAMO')->delete();
    }
};