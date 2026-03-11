<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 250);
            $table->decimal('precio', 10, 4)->default(0);
            $table->string('codigo_sunat', 50)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('servicio_venta', function (Blueprint $table) {
            $table->increments('id');
            $table->string('venta_id', 26);
            $table->unsignedInteger('servicio_id');
            $table->decimal('cantidad', 10, 4)->default(1);
            $table->decimal('precio_unitario', 10, 4);
            $table->decimal('subtotal', 10, 4);
            $table->text('referencia')->nullable();
            $table->timestamps();

            $table->foreign('venta_id')->references('id')->on('venta')->onDelete('cascade');
            $table->foreign('servicio_id')->references('id')->on('servicio')->onDelete('restrict');
            $table->index('venta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_venta');
        Schema::dropIfExists('servicio');
    }
};
