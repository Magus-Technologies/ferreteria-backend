<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Paso 1: Modificar sub_caja_origen_id a int(11) signed (ya está nullable)
        DB::statement('ALTER TABLE prestamos_entre_cajas MODIFY sub_caja_origen_id INT(11) NULL');
        
        // Paso 2: Modificar caja_principal_origen_id a int(11) signed (ya existe como unsigned)
        DB::statement('ALTER TABLE prestamos_entre_cajas MODIFY caja_principal_origen_id INT(11) NULL');

        // Paso 3: Agregar foreign keys
        Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
            // FK para sub_caja_origen_id
            $table->foreign('sub_caja_origen_id', 'prestamos_sub_caja_origen_fkey')
                ->references('id')
                ->on('sub_cajas')
                ->onDelete('restrict')
                ->onUpdate('cascade');
            
            // FK para caja_principal_origen_id
            $table->foreign('caja_principal_origen_id', 'prestamos_caja_principal_origen_fkey')
                ->references('id')
                ->on('cajas_principales')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });

        // Paso 4: Agregar índice para mejor performance
        Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
            $table->index('caja_principal_origen_id', 'prestamos_caja_principal_origen_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
            // Eliminar índice
            $table->dropIndex('prestamos_caja_principal_origen_idx');
            
            // Eliminar foreign keys
            $table->dropForeign('prestamos_caja_principal_origen_fkey');
            $table->dropForeign('prestamos_sub_caja_origen_fkey');
        });

        // Eliminar columna caja_principal_origen_id
        DB::statement('ALTER TABLE prestamos_entre_cajas DROP COLUMN caja_principal_origen_id');
        
        // Revertir sub_caja_origen_id a NOT NULL int(11)
        DB::statement('ALTER TABLE prestamos_entre_cajas MODIFY sub_caja_origen_id INT(11) NOT NULL');

        // Recrear la foreign key original
        Schema::table('prestamos_entre_cajas', function (Blueprint $table) {
            $table->foreign('sub_caja_origen_id', 'prestamos_sub_caja_origen_fkey')
                ->references('id')
                ->on('sub_cajas')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }
};
