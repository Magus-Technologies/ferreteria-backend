<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar marca/modelo al vehículo (para guía de remisión)
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->string('marca_modelo', 100)->nullable()->after('tipo');
        });

        // Agregar licencia de conducir al usuario (para guía de remisión)
        Schema::table('user', function (Blueprint $table) {
            $table->string('licencia_conducir', 20)->nullable()->after('vehiculo_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->dropColumn('marca_modelo');
        });

        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('licencia_conducir');
        });
    }
};
