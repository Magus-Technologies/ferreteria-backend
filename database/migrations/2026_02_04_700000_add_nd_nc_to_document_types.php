<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modificar enum en tabla seriedocumento para agregar 'nd' y 'nc' solo si existe
        if (Schema::hasTable('seriedocumento')) {
            DB::statement("ALTER TABLE `seriedocumento` MODIFY `tipo_documento` enum('01','03','nv','in','sa','rc','nd','nc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        }

        // Modificar enum en tabla venta para agregar 'nd' y 'nc' solo si existe
        if (Schema::hasTable('venta')) {
            DB::statement("ALTER TABLE `venta` MODIFY `tipo_documento` enum('01','03','nv','in','sa','rc','nd','nc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nv'");
        }
    }

    public function down(): void
    {
        // Revertir cambios
        DB::statement("ALTER TABLE `seriedocumento` MODIFY `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        DB::statement("ALTER TABLE `venta` MODIFY `tipo_documento` enum('01','03','nv','in','sa','rc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nv'");
    }
};
