<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de transportistas (empresas de transporte terceras).
 *
 * Se alimenta automáticamente desde `GuiaRemisionService::crear()` cuando
 * se registra un transportista PÚBLICO en una guía de remisión, y puede
 * consultarse/crearse manualmente vía API para autocompletar el formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportista', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nro_mtc', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportista');
    }
};
