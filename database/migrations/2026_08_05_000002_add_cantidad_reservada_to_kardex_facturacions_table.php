<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kardex_facturacions ADD COLUMN cantidad_reservada DOUBLE NOT NULL DEFAULT 0 AFTER cantidad_fraccion');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kardex_facturacions DROP COLUMN cantidad_reservada');
    }
};
