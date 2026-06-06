<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega `estado` (activo/inactivo) a la tabla role para poder desactivar
     * roles sin eliminarlos. Aditivo y con default true (todos quedan activos).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('role', 'estado')) {
            DB::statement('ALTER TABLE role ADD COLUMN estado TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('role', 'estado')) {
            DB::statement('ALTER TABLE role DROP COLUMN estado');
        }
    }
};
