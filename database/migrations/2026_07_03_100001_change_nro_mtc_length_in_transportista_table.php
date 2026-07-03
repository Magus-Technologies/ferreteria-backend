<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía nro_mtc de varchar(50) a varchar(255) para que coincida con
     * guia_remision.transportista_nro_mtc y con la validación de
     * StoreGuiaRemisionRequest (max:255), evitando un error SQL en el
     * upsert automático de GuiaRemisionService.
     *
     * Se usa SQL crudo (en vez de Blueprint::change()) porque el proyecto
     * no tiene instalado doctrine/dbal.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transportista MODIFY nro_mtc VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transportista MODIFY nro_mtc VARCHAR(50) NULL');
    }
};
