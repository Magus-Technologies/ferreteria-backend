<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega 'una_vez' (autorización de uso único) a los enums de tipo.
     * Aditivo: conserva temporal/permanente.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE autorizaciones_otorgadas MODIFY COLUMN tipo ENUM('temporal','permanente','una_vez') NOT NULL");
        DB::statement("ALTER TABLE solicitudes_autorizacion MODIFY COLUMN tipo_aprobacion ENUM('temporal','permanente','una_vez') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE autorizaciones_otorgadas MODIFY COLUMN tipo ENUM('temporal','permanente') NOT NULL");
        DB::statement("ALTER TABLE solicitudes_autorizacion MODIFY COLUMN tipo_aprobacion ENUM('temporal','permanente') NULL");
    }
};
