<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('kardex_inventarios');
        Schema::create('kardex_inventarios', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha');
            $table->string('codigo')->nullable();
            $table->string('producto')->nullable();
            $table->string('tipo')->nullable();
            $table->string('movimiento')->nullable();
            $table->string('documento')->nullable();
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->decimal('precio', 16, 4)->nullable();
            $table->decimal('stock_anterior', 16, 4)->default(0);
            $table->decimal('cant_ingreso', 16, 4)->default(0);
            $table->decimal('cant_salida', 16, 4)->default(0);
            $table->decimal('stock_actual', 16, 4)->default(0);

            // Relaciones para mantener integridad, aunque los textos se preserven
            $table->integer('producto_almacen_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->timestamps();

            $table->foreign('producto_almacen_id')->references('id')->on('productoalmacen')->onDelete('set null');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex_inventarios');
    }
};
