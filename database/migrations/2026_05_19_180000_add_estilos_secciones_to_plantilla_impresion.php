<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->json('estilos_secciones')->nullable()->after('mensajes_extra');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_impresion', function (Blueprint $table) {
            $table->dropColumn('estilos_secciones');
        });
    }
};
