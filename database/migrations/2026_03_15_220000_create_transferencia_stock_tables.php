<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla cabecera de transferencia
        Schema::create('transferencia_stock', function (Blueprint $table) {
            $table->id();
            $table->integer('serie')->default(1);
            $table->integer('numero');
            $table->datetime('fecha', 3)->useCurrent();
            $table->integer('almacen_origen_id');
            $table->integer('almacen_destino_id');
            $table->string('user_id', 191);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('estado')->default(true);
            $table->datetime('created_at', 3)->useCurrent();
            $table->datetime('updated_at', 3)->nullable();

            $table->index('almacen_origen_id');
            $table->index('almacen_destino_id');
            $table->index('user_id');
        });

        // Tabla detalle de productos transferidos
        Schema::create('producto_transferencia_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transferencia_stock_id');
            $table->integer('producto_almacen_origen_id');
            $table->integer('producto_almacen_destino_id');
            $table->integer('unidad_derivada_inmutable_id');
            $table->decimal('factor', 9, 3);
            $table->decimal('cantidad', 9, 3);
            $table->decimal('costo', 9, 4);
            $table->decimal('stock_anterior_origen', 9, 3);
            $table->decimal('stock_nuevo_origen', 9, 3);
            $table->decimal('stock_anterior_destino', 9, 3);
            $table->decimal('stock_nuevo_destino', 9, 3);

            $table->foreign('transferencia_stock_id')->references('id')->on('transferencia_stock');
            $table->index('producto_almacen_origen_id');
            $table->index('producto_almacen_destino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_transferencia_stock');
        Schema::dropIfExists('transferencia_stock');
    }
};
