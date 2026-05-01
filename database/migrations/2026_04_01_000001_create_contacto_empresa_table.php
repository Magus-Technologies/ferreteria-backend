<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_empresa', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id');
            $table->enum('cargo', ['gerente', 'facturacion', 'contabilidad']);
            $table->string('nombre', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('celular', 50)->nullable();
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_empresa');
    }
};
