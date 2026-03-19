<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregar producto complementario a cada unidad derivada.
 * Ejemplo: al vender "medio galón" de pintura, descontar también 1 "envase de medio galón".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            $table->integer('producto_complementario_id')->nullable()->after('activador_ultimo');
            $table->decimal('producto_complementario_cantidad', 9, 3)->nullable()->after('producto_complementario_id');

            $table->foreign('producto_complementario_id', 'paud_producto_complementario_fk')
                ->references('id')
                ->on('producto')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('productoalmacenunidadderivada', function (Blueprint $table) {
            $table->dropForeign('paud_producto_complementario_fk');
            $table->dropColumn(['producto_complementario_id', 'producto_complementario_cantidad']);
        });
    }
};
