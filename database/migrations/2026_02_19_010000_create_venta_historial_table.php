<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_historial', function (Blueprint $table) {
            $table->id();
            $table->string('venta_id', 26);
            $table->string('accion'); // edicion, anulacion, creacion
            $table->text('descripcion')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('user_id');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('venta_id')->references('id')->on('venta')->onDelete('cascade');
            $table->index('user_id');
            $table->index(['venta_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_historial');
    }
};
