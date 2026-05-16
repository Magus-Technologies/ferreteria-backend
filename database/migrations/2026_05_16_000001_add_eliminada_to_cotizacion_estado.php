<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cotizacion MODIFY COLUMN estado_cotizacion ENUM('pe','co','ve','ca','el') NOT NULL DEFAULT 'pe'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cotizacion MODIFY COLUMN estado_cotizacion ENUM('pe','co','ve','ca') NOT NULL DEFAULT 'pe'");
    }
};
