<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehiculo_id')->index();
            $table->string('tipo')->default('mantenimiento');
            $table->text('descripcion')->nullable();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->index();
            $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        // Agregar FK después si la tabla existe
        if (Schema::hasTable('vehiculos')) {
            Schema::table('vehiculo_mantenimientos', function (Blueprint $table) {
                $table->foreign('vehiculo_id')->references('id')->on('vehiculos')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_mantenimientos');
    }
};
