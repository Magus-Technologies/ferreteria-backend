<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destino "por rol" para las solicitudes de autorización: cuando la jerarquía
     * resuelve el aprobador por el ROL del cargo padre (organigrama + cargo↔rol),
     * la solicitud se dirige a cualquier usuario con ese rol.
     * Aditivo: nullable.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('solicitudes_autorizacion', 'role_autorizador_id')) {
            DB::statement('ALTER TABLE solicitudes_autorizacion ADD COLUMN role_autorizador_id INT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes_autorizacion', 'role_autorizador_id')) {
            DB::statement('ALTER TABLE solicitudes_autorizacion DROP COLUMN role_autorizador_id');
        }
    }
};
