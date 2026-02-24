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
        // Modificar el enum para agregar 'no_realizado'
        DB::statement("ALTER TABLE apertura_cierre_caja MODIFY COLUMN estado_cierre ENUM('pendiente', 'en_proceso', 'aprobado', 'no_realizado') NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero actualizar los registros con 'no_realizado' a NULL
        DB::table('apertura_cierre_caja')->where('estado_cierre', 'no_realizado')->update(['estado_cierre' => null]);
        DB::statement("ALTER TABLE apertura_cierre_caja MODIFY COLUMN estado_cierre ENUM('pendiente', 'en_proceso', 'aprobado') NULL DEFAULT NULL");
    }
};
