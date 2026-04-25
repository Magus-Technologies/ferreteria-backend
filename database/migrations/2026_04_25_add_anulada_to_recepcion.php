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
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            // Agregar campo para marcar si la recepción fue anulada
            if (!Schema::hasColumn('recepcionalmacen', 'anulada')) {
                $table->boolean('anulada')->default(false)->after('estado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepcionalmacen', function (Blueprint $table) {
            if (Schema::hasColumn('recepcionalmacen', 'anulada')) {
                $table->dropColumn('anulada');
            }
        });
    }
};
