<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar cantidad_pendiente a requerimiento_interno_productos
        if (!Schema::hasColumn('requerimiento_interno_productos', 'cantidad_pendiente')) {
            Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
                $table->decimal('cantidad_pendiente', 9, 3)->after('cantidad')->nullable();
            });

            // Inicializar cantidad_pendiente con el valor de cantidad para registros existentes
            DB::statement('UPDATE requerimiento_interno_productos SET cantidad_pendiente = cantidad');
            
            Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
                $table->decimal('cantidad_pendiente', 9, 3)->nullable(false)->change();
            });
        }

        // 2. Agregar requerimiento_interno_producto_id a orden_compra_productos
        if (!Schema::hasColumn('orden_compra_productos', 'requerimiento_interno_producto_id')) {
            Schema::table('orden_compra_productos', function (Blueprint $table) {
                $table->unsignedBigInteger('requerimiento_interno_producto_id')->nullable()->after('producto_id');
                
                $table->foreign('requerimiento_interno_producto_id', 'fk_ocp_ri_prod')
                    ->references('id')
                    ->on('requerimiento_interno_productos')
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
        Schema::table('orden_compra_productos', function (Blueprint $table) {
            $table->dropForeign('fk_ocp_ri_prod');
            $table->dropColumn('requerimiento_interno_producto_id');
        });

        Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
            $table->dropColumn('cantidad_pendiente');
        });
    }
};
