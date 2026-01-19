<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apertura_cierre_caja', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->integer('caja_principal_id')->unsigned();
            $table->integer('sub_caja_id')->unsigned()->comment('Siempre será la Caja Chica');
            $table->string('user_id', 255)->comment('Usuario que apertura');
            $table->decimal('monto_apertura', 10, 2);
            $table->timestamp('fecha_apertura');
            $table->decimal('monto_cierre', 10, 2)->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            
            // Campos de cierre
            $table->decimal('monto_cierre_efectivo', 10, 2)->nullable();
            $table->decimal('monto_cierre_cuentas', 10, 2)->nullable();
            $table->json('conteo_billetes_monedas')->nullable();
            $table->json('conceptos_adicionales')->nullable();
            $table->text('comentarios')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->decimal('diferencia_efectivo', 10, 2)->nullable();
            $table->decimal('diferencia_total', 10, 2)->nullable();
            $table->boolean('forzar_cierre')->default(false);
            
            $table->timestamps();

            $table->index(['caja_principal_id', 'estado']);
            $table->index('sub_caja_id');
            $table->index('user_id');
            $table->index('fecha_apertura');
            
            $table->foreign('supervisor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apertura_cierre_caja');
    }
};
