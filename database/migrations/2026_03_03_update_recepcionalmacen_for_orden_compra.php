<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing foreign key constraints on compra_id
        $this->dropForeignKeyIfExists('recepcionalmacen', 'recepcionalmacen_compra_id_foreign');
        $this->dropForeignKeyIfExists('recepcionalmacen', 'RecepcionAlmacen_compra_id_fkey');

        Schema::table('recepcionalmacen', function (Blueprint $table) {
            // Convertir compra_id a VARCHAR(191) nullable (mismo tipo que compra.id)
            $table->string('compra_id', 191)->nullable()->change();
            
            // Agregar orden_compra_id si no existe
            if (!Schema::hasColumn('recepcionalmacen', 'orden_compra_id')) {
                $table->unsignedBigInteger('orden_compra_id')->nullable()->after('compra_id');
                $table->index('orden_compra_id');
            }
        });

        // Re-agregar foreign key para compra_id (ahora nullable y string)
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            $table->foreign('compra_id')
                ->references('id')
                ->on('compra')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        
        // Agregar foreign key para orden_compra_id si la tabla ordenes_compra existe
        if (Schema::hasTable('ordenes_compra')) {
            Schema::table('recepcionalmacen', function (Blueprint $table) {
                $table->foreign('orden_compra_id')
                    ->references('id')
                    ->on('ordenes_compra')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            // Remover foreign keys
            $this->dropForeignKeyIfExists('recepcionalmacen', 'recepcionalmacen_orden_compra_id_foreign');
            $this->dropForeignKeyIfExists('recepcionalmacen', 'recepcionalmacen_compra_id_foreign');
            
            // Remover índice
            try {
                $table->dropIndex(['orden_compra_id']);
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            // Remover columna
            if (Schema::hasColumn('recepcionalmacen', 'orden_compra_id')) {
                $table->dropColumn('orden_compra_id');
            }
        });

        Schema::table('recepcionalmacen', function (Blueprint $table) {
            // Revertir compra_id a NOT NULL
            $table->unsignedBigInteger('compra_id')->nullable(false)->change();
            
            // Re-agregar foreign key para compra_id
            $table->foreign('compra_id')
                ->references('id')
                ->on('compra')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    private function dropForeignKeyIfExists($table, $foreignKey)
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$foreignKey}");
        } catch (\Exception $e) {
            // Foreign key doesn't exist, continue
        }
    }
};
