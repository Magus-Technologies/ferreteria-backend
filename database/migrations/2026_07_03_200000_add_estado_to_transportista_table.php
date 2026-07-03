<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el flag de activo/inactivo al catálogo de transportistas,
 * mismo criterio que `choferes.estado` (tinyint(1), default 1 = activo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportista', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->after('nro_mtc');
        });
    }

    public function down(): void
    {
        Schema::table('transportista', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
