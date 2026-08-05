<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movimientos_internos ADD COLUMN estado ENUM('activo', 'anulado') NOT NULL DEFAULT 'activo' AFTER user_id");

        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN fecha_anulacion DATETIME NULL AFTER estado');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN fecha_anulacion');
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN estado');
    }
};
