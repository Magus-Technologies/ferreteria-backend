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
        // Eliminar la tabla antigua que usa comprobante_id
        // La tabla correcta es comprobante_electronico_detalles que usa comprobante_electronico_id
        Schema::dropIfExists('detalles_comprobante_electronico');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No recrear la tabla antigua - no es necesaria
    }
};
