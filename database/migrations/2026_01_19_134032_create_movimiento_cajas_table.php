<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_caja', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('apertura_cierre_id', 255)->comment('ID de la apertura/cierre de caja');
            $table->integer('caja_principal_id')->unsigned();
            $table->integer('sub_caja_id')->unsigned()->nullable();
            $table->string('cajero_id', 255)->comment('Usuario que realiza el movimiento');
            $table->timestamp('fecha_hora');
            $table->enum('tipo_movimiento', ['apertura', 'venta', 'gasto', 'ingreso', 'cobro', 'pago', 'transferencia', 'cierre'])->default('venta');
            $table->string('concepto', 500);
            $table->decimal('saldo_inicial', 10, 2)->default(0);
            $table->decimal('ingreso', 10, 2)->default(0);
            $table->decimal('salida', 10, 2)->default(0);
            $table->decimal('saldo_final', 10, 2)->default(0);
            $table->string('registradora', 100)->nullable()->comment('Punto de venta o caja registradora');
            $table->enum('estado_caja', ['abierta', 'cerrada'])->default('abierta');
            
            // Campos adicionales para detalles
            $table->string('tipo_comprobante', 10)->nullable()->comment('01=Factura, 03=Boleta, nv=Nota Venta');
            $table->string('numero_comprobante', 50)->nullable();
            $table->string('metodo_pago_id', 255)->nullable()->comment('ID del método de pago usado');
            $table->string('referencia_id', 255)->nullable()->comment('ID de venta, gasto, etc.');
            $table->string('referencia_tipo', 50)->nullable()->comment('venta, gasto, ingreso, etc.');
            
            // Campos para transferencias entre cajas
            $table->integer('caja_origen_id')->unsigned()->nullable();
            $table->integer('caja_destino_id')->unsigned()->nullable();
            $table->decimal('monto_transferencia', 10, 2)->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('apertura_cierre_id')->references('id')->on('apertura_cierre_caja')->onDelete('cascade');
            $table->index(['apertura_cierre_id', 'fecha_hora']);
            $table->index('caja_principal_id');
            $table->index('cajero_id');
            $table->index('tipo_movimiento');
            $table->index('fecha_hora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_caja');
    }
};
