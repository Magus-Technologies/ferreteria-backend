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
        Schema::table('requerimientos_internos', function (Blueprint $table) {
            // Agregar columna vehiculo_id (foreign key a tabla vehiculos)
            if (!Schema::hasColumn('requerimientos_internos', 'vehiculo_id')) {
                $table->unsignedBigInteger('vehiculo_id')->nullable()->after('observaciones');
                $table->foreign('vehiculo_id')->references('id')->on('vehiculos')->onDelete('set null');
            }

            // Agregar columna afecta_calendario (boolean, default true para OS)
            if (!Schema::hasColumn('requerimientos_internos', 'afecta_calendario')) {
                $table->boolean('afecta_calendario')->default(true)->after('vehiculo_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requerimientos_internos', function (Blueprint $table) {
            $table->dropColumn(['vehiculo_id', 'afecta_calendario']);
        });
    }
};
