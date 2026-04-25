<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE guia_remision MODIFY COLUMN fecha_emision DATETIME NOT NULL');
        // Backfill: para guias existentes que tenian solo fecha (sin hora), usar la hora del created_at
        DB::statement('UPDATE guia_remision SET fecha_emision = created_at WHERE TIME(fecha_emision) = "00:00:00" AND created_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE guia_remision MODIFY COLUMN fecha_emision DATE NOT NULL');
    }
};
