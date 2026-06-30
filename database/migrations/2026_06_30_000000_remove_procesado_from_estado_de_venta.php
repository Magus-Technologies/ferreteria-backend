<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any existing 'pr' rows to 'cr' before altering the enum.
        DB::table('venta')->where('estado_de_venta', 'pr')->update(['estado_de_venta' => 'cr']);

        // Remove 'pr' from the MySQL enum definition.
        DB::statement("ALTER TABLE venta MODIFY COLUMN estado_de_venta ENUM('cr','ee','an') NOT NULL DEFAULT 'cr'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE venta MODIFY COLUMN estado_de_venta ENUM('cr','ee','an','pr') NOT NULL DEFAULT 'cr'");
    }
};
