<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            // Agregar columna venta_id si no existe
            if (!Schema::hasColumn('comprobantes_electronicos', 'venta_id')) {
                $table->string('venta_id', 191)->nullable()->after('id');
                $table->index('venta_id');
            }
        });
        
        // Agregar foreign key si la tabla venta existe
        if (Schema::hasTable('venta')) {
            Schema::table('comprobantes_electronicos', function (Blueprint $table) {
                $table->foreign('venta_id')
                    ->references('id')
                    ->on('venta')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            // Eliminar foreign key si existe
            $foreignKeys = \DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'comprobantes_electronicos' 
                AND CONSTRAINT_NAME LIKE '%venta_id%' 
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            
            foreach ($foreignKeys as $fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            }
            
            // Eliminar columna
            if (Schema::hasColumn('comprobantes_electronicos', 'venta_id')) {
                $table->dropColumn('venta_id');
            }
        });
    }
};
