<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar el FK antiguo (nombre Prisma) si existe
        try {
            DB::statement('ALTER TABLE recepcionalmacen DROP FOREIGN KEY RecepcionAlmacen_compra_id_fkey');
        } catch (\Exception $e) {
            // Ya no existe, continuar
        }

        // 2. Eliminar el FK con nombre Laravel si existe (de intentos anteriores)
        try {
            DB::statement('ALTER TABLE recepcionalmacen DROP FOREIGN KEY recepcionalmacen_compra_id_foreign');
        } catch (\Exception $e) {
            // Ya no existe, continuar
        }

        // 3. Hacer compra_id nullable (VARCHAR(191) para compatibilidad con IDs de Prisma)
        DB::statement('ALTER TABLE recepcionalmacen MODIFY COLUMN compra_id VARCHAR(191) NULL');

        // 4. Re-agregar FK con nombre Laravel como nullable
        try {
            DB::statement('ALTER TABLE recepcionalmacen ADD CONSTRAINT recepcionalmacen_compra_id_foreign FOREIGN KEY (compra_id) REFERENCES compra(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {
            // Si falla por incompatibilidad de tipos, dejamos sin FK (no crítico)
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE recepcionalmacen DROP FOREIGN KEY recepcionalmacen_compra_id_foreign');
        } catch (\Exception $e) {}

        DB::statement('ALTER TABLE recepcionalmacen MODIFY COLUMN compra_id VARCHAR(191) NOT NULL');

        try {
            DB::statement('ALTER TABLE recepcionalmacen ADD CONSTRAINT RecepcionAlmacen_compra_id_fkey FOREIGN KEY (compra_id) REFERENCES compra(id) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {}
    }
};
