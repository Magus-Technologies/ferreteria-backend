<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prestamo_devolucion', function (Blueprint $table) {
            $table->id();
            $table->string('prestamo_id', 191);
            $table->integer('ingreso_salida_id');
            $table->dateTime('fecha_devolucion');
            $table->string('user_id', 191);
            $table->string('numero_devolucion', 50);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('prestamo_id')->references('id')->on('prestamos')->onDelete('cascade');
            $table->foreign('ingreso_salida_id')->references('id')->on('ingresosalida')->onDelete('cascade');
            $table->index('prestamo_id');
            $table->index('ingreso_salida_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamo_devolucion');
    }
};