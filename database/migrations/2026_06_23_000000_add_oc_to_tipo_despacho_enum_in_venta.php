<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `venta` MODIFY COLUMN `tipo_despacho` ENUM('et','do','pa','oc') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `venta` MODIFY COLUMN `tipo_despacho` ENUM('et','do','pa') NULL");
    }
};
