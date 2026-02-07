<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detalles_comprobante_electronico')) {
            return;
        }
        
        Schema::create('detalles_comprobante_electronico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_id');
            $table->unsignedInteger('unidad_derivada_venta_id')->comment('Referencia a la línea de la venta');
            $table->unsignedInteger('item_numero');
            $table->string('codigo_producto', 191)->nullable();
            $table->string('descripcion_producto', 500);
            $table->decimal('cantidad', 12, 3);
            $table->string('unidad_medida', 20)->nullable();
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('descuento_monto', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv_monto', 12, 2)->default(0);
            $table->decimal('total_linea', 12, 2);

            $table->index('comprobante_id', 'idx_comprobante_id');
        });

        // Agregar foreign keys solo si las tablas existen
        if (Schema::hasTable('comprobantes_electronicos')) {
            Schema::table('detalles_comprobante_electronico', function (Blueprint $table) {
                $table->foreign('comprobante_id')->references('id')->on('comprobantes_electronicos')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('unidadderivadainmutableventa')) {
            Schema::table('detalles_comprobante_electronico', function (Blueprint $table) {
                $table->foreign('unidad_derivada_venta_id')->references('id')->on('unidadderivadainmutableventa')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_comprobante_electronico');
    }
};
