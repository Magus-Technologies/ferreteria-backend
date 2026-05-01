<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direccion_empresa', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id');
            $table->string('alias', 100)->nullable();
            $table->string('direccion', 191);
            $table->integer('ubigeo_id')->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('distrito', 100)->nullable();
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direccion_empresa');
    }
};
