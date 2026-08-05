<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE solicitudes_efectivo_vendedores MODIFY estado ENUM('pendiente', 'aprobada', 'rechazada', 'anulada') NOT NULL DEFAULT 'pendiente'");

        DB::statement('ALTER TABLE solicitudes_efectivo_vendedores ADD COLUMN fecha_anulacion DATETIME NULL AFTER comentario_respuesta');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitudes_efectivo_vendedores DROP COLUMN fecha_anulacion');

        DB::statement("ALTER TABLE solicitudes_efectivo_vendedores MODIFY estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente'");
    }
};
