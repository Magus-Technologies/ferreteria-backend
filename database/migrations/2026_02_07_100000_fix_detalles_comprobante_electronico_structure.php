<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla existe y tiene la estructura antigua
        if (Schema::hasTable('detalles_comprobante_electronico')) {
            // Verificar si tiene las columnas antiguas
            if (Schema::hasColumn('detalles_comprobante_electronico', 'unidad_derivada_venta_id')) {
                // Eliminar la tabla antigua y recrearla con la estructura correcta
                Schema::dropIfExists('detalles_comprobante_electronico');
            }
        }

        // Crear la tabla con la estructura correcta
        if (!Schema::hasTable('detalles_comprobante_electronico')) {
            Schema::create('detalles_comprobante_electronico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comprobante_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->unsignedInteger('unidad_derivada_id')->nullable();
                $table->integer('item')->comment('Número de línea del detalle');
                $table->string('codigo_producto', 191)->nullable();
                $table->string('descripcion', 500);
                $table->string('unidad_medida', 20)->default('NIU');
                $table->decimal('cantidad', 12, 3);
                $table->decimal('valor_unitario', 12, 4)->comment('Precio sin IGV');
                $table->decimal('precio_unitario', 12, 4)->comment('Precio con IGV');
                $table->decimal('valor_venta', 12, 2)->comment('Subtotal sin IGV');
                $table->decimal('igv', 12, 2)->default(0);
                $table->string('tipo_afectacion_igv', 2)->default('10')->comment('10=Gravado, 20=Exonerado, 30=Inafecto');
                $table->decimal('total_impuestos', 12, 2)->default(0);
                $table->timestamps();

                $table->index('comprobante_id');
                $table->foreign('comprobante_id')
                    ->references('id')
                    ->on('comprobantes_electronicos')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_comprobante_electronico');
    }
};
