<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega 'acceso' al enum `accion` para soportar autorización a nivel de
     * vista/elemento (además de crear/editar/eliminar). Aditivo: no quita valores.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE autorizaciones_config MODIFY COLUMN accion ENUM('crear','editar','eliminar','acceso') NOT NULL");
        DB::statement("ALTER TABLE solicitudes_autorizacion MODIFY COLUMN accion ENUM('crear','editar','eliminar','acceso') NOT NULL");
        DB::statement("ALTER TABLE autorizaciones_otorgadas MODIFY COLUMN accion ENUM('crear','editar','eliminar','acceso') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE autorizaciones_config MODIFY COLUMN accion ENUM('crear','editar','eliminar') NOT NULL");
        DB::statement("ALTER TABLE solicitudes_autorizacion MODIFY COLUMN accion ENUM('crear','editar','eliminar') NOT NULL");
        DB::statement("ALTER TABLE autorizaciones_otorgadas MODIFY COLUMN accion ENUM('crear','editar','eliminar') NOT NULL");
    }
};
