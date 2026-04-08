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
            // Renombrar área a cargo
            if (Schema::hasColumn('requerimientos_internos', 'area')) {
                $table->renameColumn('area', 'cargo');
            }

            // Añadir duración
            $table->integer('duracion_cantidad')->nullable()->after('fecha_requerida');
            $table->string('duracion_unidad', 20)->nullable()->after('duracion_cantidad')->comment('horas, dias, semanas');
        });
    }

    public function down(): void
    {
        Schema::table('requerimientos_internos', function (Blueprint $table) {
            if (Schema::hasColumn('requerimientos_internos', 'cargo')) {
                $table->renameColumn('cargo', 'area');
            }
            $table->dropColumn(['duracion_cantidad', 'duracion_unidad']);
        });
    }
};
