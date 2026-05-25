<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('icono', 50)->nullable();
            $table->string('color', 30)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tipo_despacho', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('estado_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 50);
            $table->string('color', 30)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('es_final')->default(false);
            $table->timestamps();
        });

        Schema::create('quien_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quien_entrega');
        Schema::dropIfExists('estado_entrega');
        Schema::dropIfExists('tipo_despacho');
        Schema::dropIfExists('tipo_entrega');
    }
};
