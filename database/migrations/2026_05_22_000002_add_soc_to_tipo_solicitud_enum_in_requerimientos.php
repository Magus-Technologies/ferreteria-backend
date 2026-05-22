<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE requerimientos_internos MODIFY tipo_solicitud ENUM('OC','OS','SOC') NOT NULL COMMENT 'OC=Orden Compra, OS=Orden Servicio, SOC=Solicitud de Orden de Compra'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE requerimientos_internos MODIFY tipo_solicitud ENUM('OC','OS') NOT NULL COMMENT 'OC=Orden Compra, OS=Orden Servicio'");
    }
};
