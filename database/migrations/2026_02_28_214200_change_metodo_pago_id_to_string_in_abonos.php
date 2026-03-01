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
        Schema::table('abonos_deuda_personal', function (Blueprint $table) {
            // Cambiar metodo_pago_id de unsignedInteger a string para soportar ULIDs
            $table->string('metodo_pago_id', 191)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('abonos_deuda_personal', function (Blueprint $table) {
            // Revertir a unsignedInteger
            $table->unsignedInteger('metodo_pago_id')->nullable()->change();
        });
    }
};

