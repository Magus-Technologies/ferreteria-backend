<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relaciona (opcionalmente) un cargo ocupacional con un rol del sistema.
     * Aditivo: nullable, sin forzar nada.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('catalogo_cargos', 'role_id')) {
            DB::statement('ALTER TABLE catalogo_cargos ADD COLUMN role_id INT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('catalogo_cargos', 'role_id')) {
            DB::statement('ALTER TABLE catalogo_cargos DROP COLUMN role_id');
        }
    }
};
